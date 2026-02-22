<?php

namespace App\MessageHandler;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Message\SendMessage;
use App\Provider\Call\CallProvider;
use App\Provider\SMS\SmsProvider;
use App\Repository\MessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Twilio\Exceptions\RestException;

#[AsMessageHandler]
class SendMessageHandler
{
    public function __construct(
        private readonly MessageRepository $messageRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly SmsProvider $smsProvider,
        private readonly CallProvider $callProvider,
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
                    $toNumber,
                    $trigger->getContent() ?? '',
                    $context,
                );
            } elseif (Trigger::TYPE_CALL === $messageType) {
                $this->callProvider->send(
                    $toNumber,
                    $trigger->getContent() ?? '',
                    $context,
                );
            } else {
                throw new \RuntimeException('Unsupported message type: ' . $messageType);
            }

            $message->setStatus(Message::STATUS_SENT);
            $message->setSentAt(new \DateTime());
        } catch (\Throwable $e) {
            if ($this->isRetryable($e)) {
                $this->entityManager->flush();

                throw $e;
            }

            $message->setStatus(Message::STATUS_FAILED);
            $message->setError(mb_substr($e->getMessage(), 0, 255));
        }

        $this->entityManager->flush();
    }

    private function isRetryable(\Throwable $e): bool
    {
        if ($e instanceof RestException) {
            $status = $e->getStatusCode();

            // 429 Too Many Requests or 5xx server errors are transient
            return 429 === $status || $status >= 500;
        }

        // Network errors (curl timeouts, connection refused) are retryable
        if ($e instanceof \Twilio\Exceptions\TwilioException) {
            return true;
        }

        return false;
    }
}
