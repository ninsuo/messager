<?php

namespace App\Tests\Entity;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Repository\MessageRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageTest extends KernelTestCase
{
    use EntityFactoryTrait;

    public function testCreateMessage(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger();
        $contact = $this->createContact('+33611111111');
        $message = $this->createMessage($trigger, $contact);

        $this->assertNotNull($message->getId());
        $this->assertNotEmpty($message->getUuid());
        $this->assertSame($trigger, $message->getTrigger());
        $this->assertSame($contact, $message->getContact());
        $this->assertNull($message->getError());
        $this->assertNotNull($message->getCreatedAt());
    }

    public function testMessageWithError(): void
    {
        self::bootKernel();

        $message = $this->createMessage();
        $message->setError('Twilio API timeout');

        $repo = self::getContainer()->get(MessageRepository::class);
        $repo->save($message);

        $this->assertSame('Twilio API timeout', $message->getError());
    }

    public function testMessageErrorNullable(): void
    {
        self::bootKernel();

        $message = $this->createMessage();
        $message->setError('error');
        $message->setError(null);

        $repo = self::getContainer()->get(MessageRepository::class);
        $repo->save($message);

        $this->assertNull($message->getError());
    }

    public function testRemoveMessage(): void
    {
        self::bootKernel();

        $message = $this->createMessage();
        $id = $message->getId();

        $repo = self::getContainer()->get(MessageRepository::class);
        $repo->remove($message);

        $this->assertNull($repo->find($id));
    }

    public function testMessageBelongsToTrigger(): void
    {
        self::bootKernel();

        $trigger = $this->createTrigger(type: Trigger::TYPE_CALL);
        $message = $this->createMessage($trigger);

        $this->assertSame(Trigger::TYPE_CALL, $message->getTrigger()->getType());
    }

    public function testFluentSetters(): void
    {
        $message = new Message();

        $result = $message->setUuid('test-uuid');
        $this->assertSame($message, $result);

        $result = $message->setError('error');
        $this->assertSame($message, $result);
    }
}
