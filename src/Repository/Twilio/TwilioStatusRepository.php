<?php

namespace App\Repository\Twilio;

use App\Entity\Twilio\TwilioStatus;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<TwilioStatus>
 */
class TwilioStatusRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, TwilioStatus::class);
    }

    public function save(TwilioStatus $status): void
    {
        $this->getEntityManager()->persist($status);
        $this->getEntityManager()->flush();
    }

    /**
     * @return TwilioStatus[]
     */
    public function getStatuses(string $sid): array
    {
        return $this->createQueryBuilder('s')
            ->where('s.sid = :sid')
            ->setParameter('sid', $sid)
            ->orderBy('s.receivedAt', 'ASC')
            ->getQuery()
            ->getResult();
    }
}
