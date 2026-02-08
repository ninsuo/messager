<?php

namespace App\Repository;

use App\Entity\User;
use App\Tool\Encryption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<User>
 */
class UserRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly Encryption $encryption,
    ) {
        parent::__construct($registry, User::class);
    }

    public function save(User $user): void
    {
        $this->getEntityManager()->persist($user);
        $this->getEntityManager()->flush();
    }

    public function remove(User $user): void
    {
        $this->getEntityManager()->remove($user);
        $this->getEntityManager()->flush();
    }

    public function findByPhoneNumber(string $phoneNumber): ?User
    {
        return $this->findOneBy([
            'blindPhoneNumber' => $this->encryption->blindHash($phoneNumber, User::class),
        ]);
    }
}
