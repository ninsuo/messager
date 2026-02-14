<?php

namespace App\Repository\Fake;

use App\Entity\Fake\FakeSms;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FakeSms>
 */
class FakeSmsRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FakeSms::class);
    }

    public function save(FakeSms $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function truncate(): void
    {
        $this->createQueryBuilder('s')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
