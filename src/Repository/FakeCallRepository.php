<?php

namespace App\Repository;

use App\Entity\FakeCall;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<FakeCall>
 */
class FakeCallRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, FakeCall::class);
    }

    public function save(FakeCall $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    /**
     * @return array<string, int> Map of toNumber => max(id) for calls with content
     */
    public function findLatestIdsByPhone(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.toNumber, MAX(c.id) as latestId')
            ->where('c.content IS NOT NULL')
            ->groupBy('c.toNumber')
            ->getQuery()
            ->getArrayResult();

        $map = [];
        foreach ($rows as $row) {
            $map[$row['toNumber']] = (int) $row['latestId'];
        }

        return $map;
    }

    public function truncate(): void
    {
        $this->createQueryBuilder('c')
            ->delete()
            ->getQuery()
            ->execute();
    }
}
