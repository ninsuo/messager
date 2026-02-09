<?php

namespace App\EventSubscriber;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Event\TwilioMessageEvent;
use App\Provider\SMS\SmsProvider;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\Routing\RouterInterface;
use Twilio\TwiML\VoiceResponse;

class MessageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ContactRepository $contactRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsProvider $smsProvider,
        private readonly RouterInterface $router,
        #[Autowire(env: 'WEBSITE_URL')]
        private readonly string $websiteUrl,
        #[Autowire(env: 'TWILIO_PHONE_NUMBER')]
        private readonly string $fromNumber,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwilioEvent::MESSAGE_ERROR => 'onMessageError',
            TwilioEvent::CALL_RECEIVED => 'onCallReceived',
            TwilioEvent::CALL_ESTABLISHED => 'onCallEstablished',
            TwilioEvent::CALL_KEY_PRESSED => 'onCallKeyPressed',
            TwilioEvent::CALL_ERROR => 'onCallError',
            TwilioEvent::CALL_ANSWERING_MACHINE => 'onCallAnsweringMachine',
        ];
    }

    public function onMessageError(TwilioMessageEvent $event): void
    {
        $message = $this->getMessageFromSmsEvent($event);

        if (null === $message) {
            return;
        }

        $message->setStatus(Message::STATUS_FAILED);
        $message->setError($event->getMessage()->getError());
        $this->entityManager->flush();
    }

    public function onCallReceived(TwilioCallEvent $event): void
    {
        $callerPhone = $event->getCall()->getFromNumber();

        if (null === $callerPhone) {
            $this->setNoActiveTriggersResponse($event);

            return;
        }

        $contact = $this->contactRepository->findByPhoneNumber($callerPhone);

        if (null === $contact) {
            $this->setNoActiveTriggersResponse($event);

            return;
        }

        $since = new \DateTime('-24 hours');
        $message = $this->messageRepository->findLatestByContact($contact, $since);

        if (null === $message) {
            $this->setNoActiveTriggersResponse($event);

            return;
        }

        // Attach message context so subsequent events (key press) can reference it
        $event->getCall()->setContext(['message_uuid' => $message->getUuid()]);

        // Reuse the same voice response logic as outbound calls
        $this->onCallEstablished($event);
    }

    public function onCallEstablished(TwilioCallEvent $event): void
    {
        $message = $this->getMessageFromCallEvent($event);

        if (null === $message) {
            return;
        }

        $trigger = $message->getTrigger();
        $content = $trigger?->getContent() ?? '';

        $response = new VoiceResponse();
        $response->say($content, [
            'language' => 'fr-FR',
        ]);

        $response->pause(['length' => 1]);

        $callUuid = $event->getCall()->getUuid();
        $url = sprintf(
            '%s%s',
            rtrim($this->websiteUrl, '/'),
            $this->router->generate('twilio_outgoing_call', ['uuid' => $callUuid]),
        );

        $gather = $response->gather([
            'numDigits' => 1,
            'action' => $url,
        ]);

        $gather->say('Pour réécouter le message, appuyez sur 0.', [
            'language' => 'fr-FR',
        ]);

        $event->setResponse($response);
    }

    public function onCallKeyPressed(TwilioCallEvent $event): void
    {
        $message = $this->getMessageFromCallEvent($event);

        if (null === $message) {
            return;
        }

        $key = $event->getKeyPressed();

        if ('0' === $key) {
            // Repeat the message
            $this->onCallEstablished($event);

            return;
        }

        // Any other key: acknowledge and hang up
        $message->setStatus(Message::STATUS_DELIVERED);
        $this->entityManager->flush();

        $response = new VoiceResponse();
        $response->say('Bonne journée.', [
            'language' => 'fr-FR',
        ]);

        $event->setResponse($response);
    }

    public function onCallError(TwilioCallEvent $event): void
    {
        $message = $this->getMessageFromCallEvent($event);

        if (null === $message) {
            return;
        }

        $message->setStatus(Message::STATUS_FAILED);
        $message->setError($event->getCall()->getError());
        $this->entityManager->flush();
    }

    public function onCallAnsweringMachine(TwilioCallEvent $event): void
    {
        $message = $this->getMessageFromCallEvent($event);

        if (null === $message) {
            return;
        }

        $trigger = $message->getTrigger();
        $contact = $message->getContact();

        if (null === $trigger || null === $contact) {
            return;
        }

        // Skip SMS fallback for BOTH triggers — an SMS was already sent separately
        if (Trigger::TYPE_BOTH === $trigger->getType()) {
            return;
        }

        $toNumber = $contact->getPhoneNumber();
        if (null === $toNumber) {
            return;
        }

        // Fall back to SMS
        $this->smsProvider->send(
            $this->fromNumber,
            $toNumber,
            $trigger->getContent() ?? '',
            ['message_uuid' => $message->getUuid()],
        );
    }

    private function getMessageFromSmsEvent(TwilioMessageEvent $event): ?Message
    {
        $context = $event->getMessage()->getContext();
        $messageUuid = $context['message_uuid'] ?? null;

        if (null === $messageUuid) {
            return null;
        }

        return $this->messageRepository->findOneBy(['uuid' => $messageUuid]);
    }

    private function setNoActiveTriggersResponse(TwilioCallEvent $event): void
    {
        $response = new VoiceResponse();

        $response->say(
            'Votre numéro de téléphone n\'est sur aucun déclenchement actif pour le moment.',
            ['language' => 'fr-FR'],
        );

        $event->setResponse($response);
    }

    private function getMessageFromCallEvent(TwilioCallEvent $event): ?Message
    {
        $context = $event->getCall()->getContext();
        $messageUuid = $context['message_uuid'] ?? null;

        if (null === $messageUuid) {
            return null;
        }

        return $this->messageRepository->findOneBy(['uuid' => $messageUuid]);
    }
}
