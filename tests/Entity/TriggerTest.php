<?php

namespace App\Tests\Entity;

use App\Entity\Trigger;
use App\Repository\TriggerRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class TriggerTest extends KernelTestCase
{
    use EntityFactoryTrait;

    public function testCreateSmsTrigger(): void
    {
        self::bootKernel();

        $user = $this->createUser('+33600000001');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Need a driver');

        $this->assertNotNull($trigger->getId());
        $this->assertNotEmpty($trigger->getUuid());
        $this->assertSame($user, $trigger->getUser());
        $this->assertSame(Trigger::TYPE_SMS, $trigger->getType());
        $this->assertSame('Need a driver', $trigger->getContent());
        $this->assertNotNull($trigger->getCreatedAt());
    }

    public function testCreateCallTrigger(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger(type: Trigger::TYPE_CALL, content: 'Call content');

        $this->assertSame(Trigger::TYPE_CALL, $trigger->getType());
        $this->assertSame('Call content', $trigger->getContent());
    }

    public function testContentIsEncryptedInDatabase(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger(content: 'Secret message');

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $raw = $em->getConnection()->executeQuery(
            'SELECT content FROM `trigger` WHERE id = ?',
            [$trigger->getId()]
        )->fetchOne();

        $this->assertNotSame('Secret message', $raw);
    }

    public function testAddContacts(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger();
        $contact1 = $this->createContact('+33611111111');
        $contact2 = $this->createContact('+33622222222');

        $trigger->addContact($contact1);
        $trigger->addContact($contact2);

        $repo = self::getContainer()->get(TriggerRepository::class);
        $repo->save($trigger);

        $this->assertCount(2, $trigger->getContacts());
    }

    public function testAddContactIsIdempotent(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger();
        $contact = $this->createContact('+33611111111');

        $trigger->addContact($contact);
        $trigger->addContact($contact);

        $this->assertCount(1, $trigger->getContacts());
    }

    public function testAddMessage(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger();
        $contact = $this->createContact('+33611111111');

        $message = new \App\Entity\Message();
        $message->setUuid(\Symfony\Component\Uid\Uuid::v4()->toRfc4122());
        $message->setContact($contact);

        $trigger->addMessage($message);

        $repo = self::getContainer()->get(TriggerRepository::class);
        $repo->save($trigger);

        $this->assertCount(1, $trigger->getMessages());
        $this->assertSame($trigger, $message->getTrigger());
    }

    public function testRemoveTriggerCascadesMessages(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger();
        $contact = $this->createContact('+33611111111');

        $message = new \App\Entity\Message();
        $message->setUuid(\Symfony\Component\Uid\Uuid::v4()->toRfc4122());
        $message->setContact($contact);

        $trigger->addMessage($message);

        $repo = self::getContainer()->get(TriggerRepository::class);
        $repo->save($trigger);

        $messageId = $message->getId();
        $this->assertNotNull($messageId);

        $repo->remove($trigger);

        $em = self::getContainer()->get(EntityManagerInterface::class);
        $count = $em->getConnection()->executeQuery(
            'SELECT COUNT(*) FROM message WHERE id = ?',
            [$messageId]
        )->fetchOne();

        $this->assertSame(0, (int) $count);
    }

    public function testTypeConstants(): void
    {
        $this->assertSame('sms', Trigger::TYPE_SMS);
        $this->assertSame('call', Trigger::TYPE_CALL);
    }

    public function testFluentSetters(): void
    {
        $trigger = new Trigger();

        $result = $trigger->setUuid('test-uuid');
        $this->assertSame($trigger, $result);

        $result = $trigger->setType(Trigger::TYPE_SMS);
        $this->assertSame($trigger, $result);

        $result = $trigger->setContent('content');
        $this->assertSame($trigger, $result);
    }
}
