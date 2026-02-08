<?php

namespace App\Repository;

use App\Entity\UnguessableCode;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<UnguessableCode>
 */
class UnguessableCodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, UnguessableCode::class);
    }

    public function get(string $code): ?UnguessableCode
    {
        return $this->findOneBy(['code' => $code]);
    }

    public function save(UnguessableCode $entity): void
    {
        $this->getEntityManager()->persist($entity);
        $this->getEntityManager()->flush();
    }

    public function remove(UnguessableCode $entity): void
    {
        $this->getEntityManager()->remove($entity);
        $this->getEntityManager()->flush();
    }
}
