<?php

namespace App\Repository;

use App\Entity\AbstractTwilioEntity;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;

/**
 * @template T of AbstractTwilioEntity
 *
 * @extends ServiceEntityRepository<T>
 */
abstract class AbstractTwilioRepository extends ServiceEntityRepository
{
    public function save(AbstractTwilioEntity $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    /**
     * @return T[]
     */
    public function findEntitiesWithoutPrice(int $retries): array
    {
        return $this->createQueryBuilder('t')
            ->where('t.price IS NULL')
            ->andWhere('t.sid IS NOT NULL')
            ->andWhere('t.retry < :retries')
            ->andWhere('t.updatedAt < :one_day_ago')
            ->setParameter('retries', $retries)
            ->setParameter('one_day_ago', date('Y-m-d 00:00:00', strtotime('-1 day')))
            ->setMaxResults(10000)
            ->getQuery()
            ->getResult();
    }

    public function iterate(callable $callback): void
    {
        $count = $this->createQueryBuilder('t')
            ->select('COUNT(t.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $em = $this->getEntityManager();
        $offset = 0;

        while ($offset < $count) {
            $qb = $this->createQueryBuilder('t')
                ->setFirstResult($offset)
                ->setMaxResults(100);

            foreach ($qb->getQuery()->toIterable() as $entity) {
                if (false === $callback($entity)) {
                    break;
                }

                $em->persist($entity);
            }

            $em->flush();
            $em->clear();

            $offset += 100;
        }
    }
}
