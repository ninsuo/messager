<?php

namespace App\Tests\Repository;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Repository\MessageRepository;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;

class MessageRepositoryTest extends KernelTestCase
{
    use EntityFactoryTrait;

    private MessageRepository $repository;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->repository = self::getContainer()->get(MessageRepository::class);
    }

    public function testGetStatusCountsByUser(): void
    {
        $user = $this->createUser('+33600700001');
        $contact1 = $this->createContact('+33611700001');
        $contact2 = $this->createContact('+33611700002');

        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Status test');
        $trigger->addContact($contact1);
        $trigger->addContact($contact2);

        $m1 = $this->createMessage($trigger, $contact1);
        $m1->setStatus(Message::STATUS_SENT);
        $this->repository->save($m1);

        $m2 = $this->createMessage($trigger, $contact2);
        $m2->setStatus(Message::STATUS_FAILED);
        $m2->setError('Unreachable');
        $this->repository->save($m2);

        $counts = $this->repository->getStatusCountsByUser($user);

        $this->assertArrayHasKey($trigger->getId(), $counts);
        $triggerCounts = $counts[$trigger->getId()];
        $this->assertSame(0, $triggerCounts['pending']);
        $this->assertSame(1, $triggerCounts['sent']);
        $this->assertSame(1, $triggerCounts['failed']);
        $this->assertSame(2, $triggerCounts['total']);
    }

    public function testGetStatusCountsByUserReturnsEmptyForNoTriggers(): void
    {
        $user = $this->createUser('+33600700002');

        $counts = $this->repository->getStatusCountsByUser($user);

        $this->assertSame([], $counts);
    }

    public function testGetStatusCountsByTrigger(): void
    {
        $user = $this->createUser('+33600700003');
        $contact1 = $this->createContact('+33611700003');
        $contact2 = $this->createContact('+33611700004');
        $contact3 = $this->createContact('+33611700005');

        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Trigger status test');

        $m1 = $this->createMessage($trigger, $contact1);
        $m1->setStatus(Message::STATUS_SENT);
        $this->repository->save($m1);

        $m2 = $this->createMessage($trigger, $contact2);
        $m2->setStatus(Message::STATUS_SENT);
        $this->repository->save($m2);

        $m3 = $this->createMessage($trigger, $contact3);
        // stays pending (default)

        $counts = $this->repository->getStatusCountsByTrigger($trigger);

        $this->assertSame(1, $counts['pending']);
        $this->assertSame(2, $counts['sent']);
        $this->assertSame(0, $counts['failed']);
        $this->assertSame(3, $counts['total']);
    }

    public function testGetStatusCountsByTriggerReturnsZerosForNoMessages(): void
    {
        $user = $this->createUser('+33600700004');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Empty trigger');

        $counts = $this->repository->getStatusCountsByTrigger($trigger);

        $this->assertSame(0, $counts['pending']);
        $this->assertSame(0, $counts['sent']);
        $this->assertSame(0, $counts['failed']);
        $this->assertSame(0, $counts['total']);
    }
}
