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
}
