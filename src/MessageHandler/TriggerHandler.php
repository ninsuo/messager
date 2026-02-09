<?php

namespace App\MessageHandler;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Message\SendMessage;
use App\Message\TriggerMessage;
use App\Repository\TriggerRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Messenger\Attribute\AsMessageHandler;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Uid\Uuid;

#[AsMessageHandler]
class TriggerHandler
{
    public function __construct(
        private readonly TriggerRepository $triggerRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly MessageBusInterface $messageBus,
    ) {
    }

    public function __invoke(TriggerMessage $triggerMessage): void
    {
        $trigger = $this->triggerRepository->findOneBy([
            'uuid' => $triggerMessage->getTriggerUuid(),
        ]);

        if (null === $trigger) {
            return;
        }

        $types = $this->getMessageTypes($trigger);

        foreach ($trigger->getContacts() as $contact) {
            foreach ($types as $type) {
                $message = new Message();
                $message->setUuid(Uuid::v4()->toRfc4122());
                $message->setContact($contact);
                $message->setType($type);
                $message->setStatus(Message::STATUS_PENDING);
                $trigger->addMessage($message);
            }
        }

        $this->entityManager->flush();

        foreach ($trigger->getMessages() as $message) {
            if (Message::STATUS_PENDING === $message->getStatus()) {
                $this->messageBus->dispatch(new SendMessage($message->getUuid()));
            }
        }
    }

    /**
     * @return string[]
     */
    private function getMessageTypes(Trigger $trigger): array
    {
        return match ($trigger->getType()) {
            Trigger::TYPE_BOTH => [Trigger::TYPE_SMS, Trigger::TYPE_CALL],
            default => [$trigger->getType() ?? Trigger::TYPE_SMS],
        };
    }
}
