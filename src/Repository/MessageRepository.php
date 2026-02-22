<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Message;
use App\Entity\Trigger;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Message>
 */
class MessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Message::class);
    }

    public function save(Message $message): void
    {
        $this->getEntityManager()->persist($message);
        $this->getEntityManager()->flush();
    }

    public function remove(Message $message): void
    {
        $this->getEntityManager()->remove($message);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array<int, array{pending: int, sent: int, failed: int, total: int}>
     */
    public function getStatusCountsByUser(User $user): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.trigger) AS trigger_id, m.status, COUNT(m.id) AS cnt')
            ->join('m.trigger', 't')
            ->where('t.user = :user')
            ->setParameter('user', $user)
            ->groupBy('trigger_id, m.status')
            ->getQuery()
            ->getArrayResult();

        return $this->buildStatusCounts($rows);
    }

    /**
     * @return array<int, array{pending: int, sent: int, failed: int, total: int}>
     */
    public function getStatusCountsAll(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.trigger) AS trigger_id, m.status, COUNT(m.id) AS cnt')
            ->groupBy('trigger_id, m.status')
            ->getQuery()
            ->getArrayResult();

        return $this->buildStatusCounts($rows);
    }

    /**
     * @return array{pending: int, sent: int, failed: int, total: int}
     */
    public function getStatusCountsByTrigger(Trigger $trigger): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.trigger) AS trigger_id, m.status, COUNT(m.id) AS cnt')
            ->where('m.trigger = :trigger')
            ->setParameter('trigger', $trigger)
            ->groupBy('trigger_id, m.status')
            ->getQuery()
            ->getArrayResult();

        $counts = $this->buildStatusCounts($rows);

        return $counts[$trigger->getId()] ?? ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0];
    }

    /**
     * @param array<int, array{trigger_id: int, status: string, cnt: int|string}> $rows
     * @return array<int, array{pending: int, sent: int, failed: int, total: int}>
     */
    private function buildStatusCounts(array $rows): array
    {
        $counts = [];

        foreach ($rows as $row) {
            $triggerId = (int) $row['trigger_id'];
            if (!isset($counts[$triggerId])) {
                $counts[$triggerId] = ['pending' => 0, 'sent' => 0, 'failed' => 0, 'total' => 0];
            }
            $status = $row['status'];
            $cnt = (int) $row['cnt'];
            if (isset($counts[$triggerId][$status])) {
                $counts[$triggerId][$status] = $cnt;
            }
            $counts[$triggerId]['total'] += $cnt;
        }

        return $counts;
    }

    /**
     * @return Message[]
     */
    public function findByTrigger(Trigger $trigger): array
    {
        return $this->createQueryBuilder('m')
            ->join('m.contact', 'c')
            ->addSelect('c')
            ->where('m.trigger = :trigger')
            ->setParameter('trigger', $trigger)
            ->orderBy('m.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function removeByTrigger(Trigger $trigger): void
    {
        $this->createQueryBuilder('m')
            ->delete()
            ->where('m.trigger = :trigger')
            ->setParameter('trigger', $trigger)
            ->getQuery()
            ->execute();
    }

    public function findLatestByContact(Contact $contact, \DateTimeInterface $since): ?Message
    {
        return $this->createQueryBuilder('m')
            ->where('m.contact = :contact')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('contact', $contact)
            ->setParameter('since', $since)
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
