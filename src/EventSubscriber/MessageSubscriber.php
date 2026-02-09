<?php

namespace App\EventSubscriber;

use App\Entity\Message;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Event\TwilioMessageEvent;
use App\Repository\ContactRepository;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Twilio\TwiML\VoiceResponse;

class MessageSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly ContactRepository $contactRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            TwilioEvent::MESSAGE_ERROR => 'onMessageError',
            TwilioEvent::CALL_RECEIVED => 'onCallReceived',
            TwilioEvent::CALL_ESTABLISHED => 'onCallEstablished',
            TwilioEvent::CALL_ERROR => 'onCallError',
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

        for ($i = 0; $i < 5; $i++) {
            $response->say($content, [
                'language' => 'fr-FR',
                'voice' => 'Polly.Lea',
            ]);

            if ($i < 4) {
                $response->pause(['length' => 2]);
                $response->say('Je répète.', [
                    'language' => 'fr-FR',
                    'voice' => 'Polly.Mathieu',
                ]);
                $response->pause(['length' => 2]);
            }
        }

        $message->setStatus(Message::STATUS_DELIVERED);
        $this->entityManager->flush();

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
