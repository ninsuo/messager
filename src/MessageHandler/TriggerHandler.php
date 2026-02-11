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
        $messageUuids = [];
        $values = [];
        $params = [];
        $now = (new \DateTime())->format('Y-m-d H:i:s');

        foreach ($trigger->getContacts() as $contact) {
            foreach ($types as $type) {
                $uuid = Uuid::v4()->toRfc4122();
                $messageUuids[] = $uuid;

                $values[] = '(?, ?, ?, ?, ?, ?)';
                $params[] = $uuid;
                $params[] = $trigger->getId();
                $params[] = $contact->getId();
                $params[] = $type;
                $params[] = Message::STATUS_PENDING;
                $params[] = $now;
            }
        }

        if (\count($values) > 0) {
            $query = 'INSERT INTO message (uuid, trigger_id, contact_id, type, status, created_at) VALUES '.implode(', ', $values);
            $this->entityManager->getConnection()->executeStatement($query, $params);
        }

        foreach ($messageUuids as $messageUuid) {
            $this->messageBus->dispatch(new SendMessage($messageUuid));
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
