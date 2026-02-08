<?php

namespace App\Repository;

use App\Entity\Trigger;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Trigger>
 */
class TriggerRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Trigger::class);
    }

    public function save(Trigger $trigger): void
    {
        $this->getEntityManager()->persist($trigger);
        $this->getEntityManager()->flush();
    }

    public function remove(Trigger $trigger): void
    {
        $this->getEntityManager()->remove($trigger);
        $this->getEntityManager()->flush();
    }

    /**
     * @return Trigger[]
     */
    public function findByUser(User $user): array
    {
        return $this->findBy(['user' => $user], ['createdAt' => 'DESC']);
    }
}
