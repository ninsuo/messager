<?php

namespace App\Tests\MessageHandler;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Message\TriggerMessage;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class TriggerHandlerTest extends KernelTestCase
{
    use EntityFactoryTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testCreatesPendingMessagesForAllContacts(): void
    {
        $user = $this->createUser('+33600300001');
        $contact1 = $this->createContact('+33611300001');
        $contact2 = $this->createContact('+33611300002');
        $contact3 = $this->createContact('+33611300003');

        $trigger = $this->createTrigger($user);
        $trigger->addContact($contact1);
        $trigger->addContact($contact2);
        $trigger->addContact($contact3);
        self::getContainer()->get(\App\Repository\TriggerRepository::class)->save($trigger);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new TriggerMessage($trigger->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($trigger);

        $messages = $trigger->getMessages();
        $this->assertCount(3, $messages);

        foreach ($messages as $message) {
            $this->assertNotEmpty($message->getUuid());
        }
    }

    public function testIgnoresUnknownTriggerUuid(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);

        // Should not throw
        $bus->dispatch(new TriggerMessage('00000000-0000-0000-0000-000000000000'));

        $this->assertTrue(true);
    }

    public function testDispatchesSendMessageForEachContact(): void
    {
        $user = $this->createUser('+33600300002');
        $contact1 = $this->createContact('+33611300004');
        $contact2 = $this->createContact('+33611300005');

        $trigger = $this->createTrigger($user);
        $trigger->addContact($contact1);
        $trigger->addContact($contact2);
        self::getContainer()->get(\App\Repository\TriggerRepository::class)->save($trigger);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new TriggerMessage($trigger->getUuid()));

        // In sync mode, SendMessage handlers run immediately
        // Check that messages were created and processed (sent status from FakeProvider)
        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($trigger);

        $messages = $trigger->getMessages();
        $this->assertCount(2, $messages);

        foreach ($messages as $message) {
            $this->assertSame(Message::STATUS_SENT, $message->getStatus());
        }
    }

    public function testBothTypeCreatesTwoMessagesPerContact(): void
    {
        $user = $this->createUser('+33600300010');
        $contact1 = $this->createContact('+33611300010');
        $contact2 = $this->createContact('+33611300011');

        $trigger = $this->createTrigger($user, Trigger::TYPE_BOTH, 'Both test content');
        $trigger->addContact($contact1);
        $trigger->addContact($contact2);
        self::getContainer()->get(\App\Repository\TriggerRepository::class)->save($trigger);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new TriggerMessage($trigger->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($trigger);

        $messages = $trigger->getMessages();
        $this->assertCount(4, $messages, 'BOTH trigger should create 2 messages per contact');

        $types = [];
        foreach ($messages as $message) {
            $this->assertSame(Message::STATUS_SENT, $message->getStatus());
            $types[] = $message->getType();
        }

        sort($types);
        $this->assertSame([Trigger::TYPE_CALL, Trigger::TYPE_CALL, Trigger::TYPE_SMS, Trigger::TYPE_SMS], $types);
    }

    public function testBothTypeSetsMessageTypes(): void
    {
        $user = $this->createUser('+33600300012');
        $contact = $this->createContact('+33611300012');

        $trigger = $this->createTrigger($user, Trigger::TYPE_BOTH, 'Type check');
        $trigger->addContact($contact);
        self::getContainer()->get(\App\Repository\TriggerRepository::class)->save($trigger);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new TriggerMessage($trigger->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($trigger);

        $messages = $trigger->getMessages();
        $this->assertCount(2, $messages);

        $types = [];
        foreach ($messages as $message) {
            $this->assertNotNull($message->getType(), 'Message type should be set');
            $types[] = $message->getType();
        }

        sort($types);
        $this->assertSame([Trigger::TYPE_CALL, Trigger::TYPE_SMS], $types);
    }

    public function testSmsTypeSetsSmsMessageType(): void
    {
        $user = $this->createUser('+33600300013');
        $contact = $this->createContact('+33611300013');

        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'SMS type check');
        $trigger->addContact($contact);
        self::getContainer()->get(\App\Repository\TriggerRepository::class)->save($trigger);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new TriggerMessage($trigger->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($trigger);

        $messages = $trigger->getMessages();
        $this->assertCount(1, $messages);
        $this->assertSame(Trigger::TYPE_SMS, $messages->first()->getType());
    }
}
