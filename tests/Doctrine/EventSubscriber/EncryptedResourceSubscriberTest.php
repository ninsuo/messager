<?php

namespace App\Tests\Doctrine\EventSubscriber;

use App\Doctrine\EventSubscriber\EncryptedResourceSubscriber;
use App\Entity\Contact;
use App\Repository\ContactRepository;
use App\Tool\Encryption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class EncryptedResourceSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private ContactRepository $repository;
    private Encryption $encryption;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(ContactRepository::class);
        $this->encryption = self::getContainer()->get(Encryption::class);
    }

    public function testSubscriberIsRegistered(): void
    {
        $subscriber = self::getContainer()->get(EncryptedResourceSubscriber::class);
        $this->assertInstanceOf(EncryptedResourceSubscriber::class, $subscriber);
    }

    public function testEncryptOnCreate(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        $this->assertEncryptedInDb($entity, 'phone_number');
    }

    public function testDecryptOnLoad(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        $this->em->clear();
        $loaded = $this->repository->find($entity->getId());

        $this->assertNotNull($loaded);
        $this->assertSame('+33612345678', $loaded->getPhoneNumber());
    }

    public function testEncryptOnUpdate(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        $entity->setPhoneNumber('+33600000000');
        $this->repository->save($entity);

        $this->em->clear();
        $loaded = $this->repository->find($entity->getId());

        $this->assertNotNull($loaded);
        $this->assertSame('+33600000000', $loaded->getPhoneNumber());
    }

    public function testEncryptClear(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);
        $id = $entity->getId();

        // Change value but don't flush, then clear
        $entity->setPhoneNumber('+33600000000');
        $this->em->clear();

        // The DB should still have the original value
        $loaded = $this->repository->find($id);
        $this->assertNotNull($loaded);
        $this->assertSame('+33612345678', $loaded->getPhoneNumber());
    }

    public function testBlindIndexOnCreate(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        $found = $this->repository->findOneBy([
            'blindPhoneNumber' => $this->encryption->blindHash('+33612345678', Contact::class),
        ]);

        $this->assertNotNull($found);
        $this->assertSame($entity->getId(), $found->getId());
    }

    public function testBlindIndexOnUpdate(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        $entity->setPhoneNumber('+33600000000');
        $this->repository->save($entity);

        $found = $this->repository->findOneBy([
            'blindPhoneNumber' => $this->encryption->blindHash('+33600000000', Contact::class),
        ]);

        $this->assertNotNull($found);
        $this->assertSame($entity->getId(), $found->getId());

        // Old blind index should no longer match
        $old = $this->repository->findOneBy([
            'blindPhoneNumber' => $this->encryption->blindHash('+33612345678', Contact::class),
        ]);

        $this->assertNull($old);
    }

    public function testBlindIndexClear(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        // Change but don't flush, then clear
        $entity->setPhoneNumber('+33600000000');
        $this->em->clear();

        // Old blind index should still work
        $found = $this->repository->findOneBy([
            'blindPhoneNumber' => $this->encryption->blindHash('+33612345678', Contact::class),
        ]);

        $this->assertNotNull($found);
        $this->assertSame($entity->getId(), $found->getId());
    }

    public function testCleartextNotStoredInDb(): void
    {
        $entity = $this->createContact('+33612345678');
        $this->repository->save($entity);

        $rawPhone = $this->getRawColumnValue($entity, 'phone_number');

        $this->assertNotSame('+33612345678', $rawPhone);
        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $rawPhone);
    }

    private function createContact(string $phoneNumber): Contact
    {
        $entity = new Contact();
        $entity->setUuid(Uuid::v4()->toRfc4122());
        $entity->setPhoneNumber($phoneNumber);

        return $entity;
    }

    private function assertEncryptedInDb(Contact $entity, string $column): void
    {
        $raw = $this->getRawColumnValue($entity, $column);
        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $raw);
    }

    private function getRawColumnValue(Contact $entity, string $column): string
    {
        $conn = $this->em->getConnection();
        $result = $conn->executeQuery(
            sprintf('SELECT %s FROM contact WHERE id = ?', $column),
            [$entity->getId()]
        );

        return $result->fetchOne();
    }
}
