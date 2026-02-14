<?php

namespace App\Tests\MessageHandler;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Message\SendMessage;
use App\Repository\Fake\FakeCallRepository;
use App\Repository\Fake\FakeSmsRepository;
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
        $this->assertNotNull($message->getSentAt());
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
        $this->assertNotNull($message->getSentAt());
        $this->assertNull($message->getError());
    }

    public function testBothTriggerSmsMessageSendsSms(): void
    {
        $user = $this->createUser('+33600400010');
        $contact = $this->createContact('+33611400010');
        $trigger = $this->createTrigger($user, Trigger::TYPE_BOTH, 'Both SMS content');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);
        $message->setType(Trigger::TYPE_SMS);
        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->flush();

        $smsCountBefore = count(self::getContainer()->get(FakeSmsRepository::class)->findAll());
        $callCountBefore = count(self::getContainer()->get(FakeCallRepository::class)->findAll());

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendMessage($message->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_SENT, $message->getStatus());

        $smsCountAfter = count(self::getContainer()->get(FakeSmsRepository::class)->findAll());
        $callCountAfter = count(self::getContainer()->get(FakeCallRepository::class)->findAll());

        $this->assertSame($smsCountBefore + 1, $smsCountAfter, 'Should have sent one SMS');
        $this->assertSame($callCountBefore, $callCountAfter, 'Should not have sent a call');
    }

    public function testBothTriggerCallMessageSendsCall(): void
    {
        $user = $this->createUser('+33600400011');
        $contact = $this->createContact('+33611400011');
        $trigger = $this->createTrigger($user, Trigger::TYPE_BOTH, 'Both call content');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);
        $message->setType(Trigger::TYPE_CALL);
        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->flush();

        $smsCountBefore = count(self::getContainer()->get(FakeSmsRepository::class)->findAll());
        $callCountBefore = count(self::getContainer()->get(FakeCallRepository::class)->findAll());

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendMessage($message->getUuid()));

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_SENT, $message->getStatus());

        $smsCountAfter = count(self::getContainer()->get(FakeSmsRepository::class)->findAll());
        $callCountAfter = count(self::getContainer()->get(FakeCallRepository::class)->findAll());

        $this->assertSame($smsCountBefore, $smsCountAfter, 'Should not have sent an SMS');
        $this->assertSame($callCountBefore + 1, $callCountAfter, 'Should have sent one call');
    }

    public function testMessageTypeOverridesTriggerType(): void
    {
        $user = $this->createUser('+33600400012');
        $contact = $this->createContact('+33611400012');
        // Trigger is SMS, but message type overrides to call
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Override test');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);
        $message->setType(Trigger::TYPE_CALL);
        self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class)->flush();

        $callCountBefore = count(self::getContainer()->get(FakeCallRepository::class)->findAll());

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendMessage($message->getUuid()));

        $callCountAfter = count(self::getContainer()->get(FakeCallRepository::class)->findAll());

        $this->assertSame($callCountBefore + 1, $callCountAfter, 'Message type should override trigger type');
    }

    public function testNullMessageTypeFallsBackToTriggerType(): void
    {
        $user = $this->createUser('+33600400013');
        $contact = $this->createContact('+33611400013');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Fallback test');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);
        // message.type is null — should fall back to trigger.type (sms)

        $smsCountBefore = count(self::getContainer()->get(FakeSmsRepository::class)->findAll());

        $bus = self::getContainer()->get(MessageBusInterface::class);
        $bus->dispatch(new SendMessage($message->getUuid()));

        $smsCountAfter = count(self::getContainer()->get(FakeSmsRepository::class)->findAll());

        $this->assertSame($smsCountBefore + 1, $smsCountAfter, 'Null message type should fall back to trigger type');
    }

    public function testIgnoresUnknownMessageUuid(): void
    {
        $bus = self::getContainer()->get(MessageBusInterface::class);

        // Should not throw
        $bus->dispatch(new SendMessage('00000000-0000-0000-0000-000000000000'));

        $this->assertTrue(true);
    }
}
