<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Tool\Encryption;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Contact>
 */
class ContactRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly Encryption $encryption,
    ) {
        parent::__construct($registry, Contact::class);
    }

    public function save(Contact $contact): void
    {
        $this->getEntityManager()->persist($contact);
        $this->getEntityManager()->flush();
    }

    public function remove(Contact $contact): void
    {
        $this->getEntityManager()->remove($contact);
        $this->getEntityManager()->flush();
    }

    public function findByPhoneNumber(string $phoneNumber): ?Contact
    {
        return $this->findOneBy([
            'blindPhoneNumber' => $this->encryption->blindHash($phoneNumber, Contact::class),
        ]);
    }
}
