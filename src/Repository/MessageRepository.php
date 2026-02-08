<?php

namespace App\Repository;

use App\Entity\Contact;
use App\Entity\Message;
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
