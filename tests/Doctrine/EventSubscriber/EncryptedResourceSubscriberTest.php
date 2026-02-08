<?php

namespace App\Tests\Doctrine\EventSubscriber;

use App\Doctrine\EventSubscriber\EncryptedResourceSubscriber;
use App\Entity\TwilioMessage;
use App\Repository\TwilioMessageRepository;
use App\Tool\Encryption;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;

class EncryptedResourceSubscriberTest extends KernelTestCase
{
    private EntityManagerInterface $em;
    private TwilioMessageRepository $repository;
    private Encryption $encryption;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->em = self::getContainer()->get(EntityManagerInterface::class);
        $this->repository = self::getContainer()->get(TwilioMessageRepository::class);
        $this->encryption = self::getContainer()->get(Encryption::class);
    }

    public function testSubscriberIsRegistered(): void
    {
        $subscriber = self::getContainer()->get(EncryptedResourceSubscriber::class);
        $this->assertInstanceOf(EncryptedResourceSubscriber::class, $subscriber);
    }

    public function testEncryptOnCreate(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Hello');
        $this->repository->save($entity);

        $this->assertEncryptedInDb($entity, 'from_number');
        $this->assertEncryptedInDb($entity, 'to_number');
        $this->assertEncryptedInDb($entity, 'message');
    }

    public function testDecryptOnLoad(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Secret message');
        $this->repository->save($entity);

        $this->em->clear();
        $loaded = $this->repository->find($entity->getId());

        $this->assertNotNull($loaded);
        $this->assertSame('+33612345678', $loaded->getFromNumber());
        $this->assertSame('+33698765432', $loaded->getToNumber());
        $this->assertSame('Secret message', $loaded->getMessage());
    }

    public function testEncryptOnUpdate(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Original');
        $this->repository->save($entity);

        $entity->setMessage('Updated');
        $entity->setFromNumber('+33600000000');
        $this->repository->save($entity);

        $this->em->clear();
        $loaded = $this->repository->find($entity->getId());

        $this->assertNotNull($loaded);
        $this->assertSame('Updated', $loaded->getMessage());
        $this->assertSame('+33600000000', $loaded->getFromNumber());
    }

    public function testEncryptClear(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Paris');
        $this->repository->save($entity);
        $id = $entity->getId();

        // Change value but don't flush, then clear
        $entity->setMessage('Warsaw');
        $this->em->clear();

        // The DB should still have the original value
        $loaded = $this->repository->find($id);
        $this->assertNotNull($loaded);
        $this->assertSame('Paris', $loaded->getMessage());
    }

    public function testBlindIndexOnCreate(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Hello');
        $this->repository->save($entity);

        $found = $this->repository->findOneBy([
            'blindFromNumber' => $this->encryption->blindHash('+33612345678', TwilioMessage::class),
        ]);

        $this->assertNotNull($found);
        $this->assertSame($entity->getId(), $found->getId());
    }

    public function testBlindIndexOnUpdate(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Hello');
        $this->repository->save($entity);

        $entity->setFromNumber('+33600000000');
        $this->repository->save($entity);

        $found = $this->repository->findOneBy([
            'blindFromNumber' => $this->encryption->blindHash('+33600000000', TwilioMessage::class),
        ]);

        $this->assertNotNull($found);
        $this->assertSame($entity->getId(), $found->getId());

        // Old blind index should no longer match
        $old = $this->repository->findOneBy([
            'blindFromNumber' => $this->encryption->blindHash('+33612345678', TwilioMessage::class),
        ]);

        $this->assertNull($old);
    }

    public function testBlindIndexNull(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Hello');
        $this->repository->save($entity);
        $id = $entity->getId();

        $entity->setFromNumber(null);
        $this->repository->save($entity);

        $this->em->clear();
        $loaded = $this->repository->find($id);

        $this->assertNotNull($loaded);
        $this->assertNull($loaded->getFromNumber());
        $this->assertNull($loaded->getBlindFromNumber());
    }

    public function testBlindIndexClear(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Hello');
        $this->repository->save($entity);

        // Change but don't flush, then clear
        $entity->setFromNumber('+33600000000');
        $this->em->clear();

        // Old blind index should still work
        $found = $this->repository->findOneBy([
            'blindFromNumber' => $this->encryption->blindHash('+33612345678', TwilioMessage::class),
        ]);

        $this->assertNotNull($found);
        $this->assertSame($entity->getId(), $found->getId());
    }

    public function testBothBlindIndexesPopulated(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Hello');
        $this->repository->save($entity);

        $fromHash = $this->encryption->blindHash('+33612345678', TwilioMessage::class);
        $toHash = $this->encryption->blindHash('+33698765432', TwilioMessage::class);

        $foundByFrom = $this->repository->findOneBy(['blindFromNumber' => $fromHash]);
        $foundByTo = $this->repository->findOneBy(['blindToNumber' => $toHash]);

        $this->assertNotNull($foundByFrom);
        $this->assertNotNull($foundByTo);
        $this->assertSame($entity->getId(), $foundByFrom->getId());
        $this->assertSame($entity->getId(), $foundByTo->getId());
    }

    public function testCleartextNotStoredInDb(): void
    {
        $entity = $this->createMessage('+33612345678', '+33698765432', 'Top secret');
        $this->repository->save($entity);

        $rawFrom = $this->getRawColumnValue($entity, 'from_number');
        $rawTo = $this->getRawColumnValue($entity, 'to_number');
        $rawMessage = $this->getRawColumnValue($entity, 'message');

        $this->assertNotSame('+33612345678', $rawFrom);
        $this->assertNotSame('+33698765432', $rawTo);
        $this->assertNotSame('Top secret', $rawMessage);

        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $rawFrom);
        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $rawTo);
        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $rawMessage);
    }

    private function createMessage(string $from, string $to, string $message): TwilioMessage
    {
        $entity = new TwilioMessage();
        $entity->setUuid(Uuid::v4()->toRfc4122());
        $entity->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $entity->setFromNumber($from);
        $entity->setToNumber($to);
        $entity->setMessage($message);

        return $entity;
    }

    private function assertEncryptedInDb(TwilioMessage $entity, string $column): void
    {
        $raw = $this->getRawColumnValue($entity, $column);
        $this->assertStringEndsWith(EncryptedResourceSubscriber::ENCRYPTION_MARKER, $raw);
    }

    private function getRawColumnValue(TwilioMessage $entity, string $column): string
    {
        $conn = $this->em->getConnection();
        $result = $conn->executeQuery(
            sprintf('SELECT %s FROM twilio_message WHERE id = ?', $column),
            [$entity->getId()]
        );

        return $result->fetchOne();
    }
}
