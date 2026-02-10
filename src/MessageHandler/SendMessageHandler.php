<?php

namespace App\MessageHandler;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Message\SendMessage;
use App\Provider\Call\CallProvider;
use App\Provider\SMS\SmsProvider;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;

#[AsMessageHandler]
class SendMessageHandler
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsProvider $smsProvider,
        private readonly CallProvider $callProvider,
        #[Autowire(env: 'TWILIO_SENDER_ID')]
        private readonly string $smsSenderId,
        #[Autowire(env: 'TWILIO_PHONE_NUMBER')]
        private readonly string $fromNumber,
    ) {
    }

    public function __invoke(SendMessage $sendMessage): void
    {
        $message = $this->messageRepository->findOneBy([
            'uuid' => $sendMessage->getMessageUuid(),
        ]);

        if (null === $message) {
            return;
        }

        $trigger = $message->getTrigger();
        $contact = $message->getContact();

        if (null === $trigger || null === $contact) {
            return;
        }

        $toNumber = $contact->getPhoneNumber();

        if (null === $toNumber) {
            $message->setStatus(Message::STATUS_FAILED);
            $message->setError('Missing phone number');
            $this->entityManager->flush();

            return;
        }

        $context = ['message_uuid' => $message->getUuid()];
        $messageType = $message->getType() ?? $trigger->getType();

        try {
            if (Trigger::TYPE_SMS === $messageType) {
                $this->smsProvider->send(
                    $this->smsSenderId,
                    $toNumber,
                    $trigger->getContent() ?? '',
                    $context,
                );
            } elseif (Trigger::TYPE_CALL === $messageType) {
                $this->callProvider->send(
                    $this->fromNumber,
                    $toNumber,
                    $context,
                    $trigger->getContent(),
                );
            } else {
                throw new \RuntimeException('Unsupported message type: ' . $messageType);
            }

            $message->setStatus(Message::STATUS_SENT);
        } catch (\Throwable $e) {
            $message->setStatus(Message::STATUS_FAILED);
            $message->setError(mb_substr($e->getMessage(), 0, 255));
        }

        $this->entityManager->flush();
    }
}
