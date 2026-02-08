<?php

namespace App\Tests\MessageHandler;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Message\SendMessage;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Messenger\MessageBusInterface;

class SendMessageHandlerTest extends KernelTestCase
{
    use EntityFactoryTrait;

    protected function setUp(): void
    {
        self::bootKernel();
    }

    public function testSmsTypeSetsStatusSent(): void
    {
        $user = $this->createUser('+33600400001');
        $contact = $this->createContact('+33611400001');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Test SMS content');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendMessage($message->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_SENT, $message->getStatus());
        $this->assertNull($message->getError());
    }

    public function testCallTypeSetsStatusSent(): void
    {
        $user = $this->createUser('+33600400002');
        $contact = $this->createContact('+33611400002');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Test call content');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendMessage($message->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_SENT, $message->getStatus());
        $this->assertNull($message->getError());
    }

    public function testIgnoresUnknownMessageUuid(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);

        // Should not throw
        $bus->dispatch(new SendMessage('00000000-0000-0000-0000-000000000000'));

        $this->assertTrue(true);
    }
}
