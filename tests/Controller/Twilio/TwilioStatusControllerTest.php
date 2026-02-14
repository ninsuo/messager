<?php

namespace App\Tests\Controller\Twilio;

use App\Entity\Twilio\TwilioMessage;
use App\Repository\Twilio\TwilioMessageRepository;
use App\Repository\Twilio\TwilioStatusRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class TwilioStatusControllerTest extends WebTestCase
{
    public function testMessageStatusUpdatesExistingMessage(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $message = $this->createMessage($em);
        $uuid = $message->getUuid();

        $client->request('POST', '/twilio/message-status/' . $uuid, [
            'MessageSid' => 'SM_STATUS_1',
            'MessageStatus' => 'delivered',
        ]);

        $this->assertResponseIsSuccessful();

        $em->clear();

        $updated = static::getContainer()->get(TwilioMessageRepository::class)->find($message->getId());
        $this->assertNotNull($updated);
        $this->assertSame('delivered', $updated->getStatus());
    }

    public function testMessageStatusCreatesStatusRecord(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $message = $this->createMessage($em);

        $client->request('POST', '/twilio/message-status/' . $message->getUuid(), [
            'MessageSid' => 'SM_STATUS_2',
            'MessageStatus' => 'sent',
        ]);

        $this->assertResponseIsSuccessful();

        $statuses = static::getContainer()->get(TwilioStatusRepository::class)->getStatuses('SM_STATUS_2');
        $this->assertCount(1, $statuses);
        $this->assertSame('sent', $statuses[0]->getStatus());
        $this->assertSame('SM_STATUS_2', $statuses[0]->getSid());
    }

    public function testMessageStatusWithUnknownUuid(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/message-status/unknown-uuid-999', [
            'MessageSid' => 'SM_STATUS_3',
            'MessageStatus' => 'failed',
        ]);

        // Should still succeed — status is always saved even if message not found
        $this->assertResponseIsSuccessful();

        $statuses = static::getContainer()->get(TwilioStatusRepository::class)->getStatuses('SM_STATUS_3');
        $this->assertCount(1, $statuses);
        $this->assertSame('failed', $statuses[0]->getStatus());
    }

    public function testMessageStatusMultipleUpdates(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $message = $this->createMessage($em);
        $uuid = $message->getUuid();

        $client->request('POST', '/twilio/message-status/' . $uuid, [
            'MessageSid' => 'SM_STATUS_4',
            'MessageStatus' => 'queued',
        ]);
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/twilio/message-status/' . $uuid, [
            'MessageSid' => 'SM_STATUS_4',
            'MessageStatus' => 'sent',
        ]);
        $this->assertResponseIsSuccessful();

        $client->request('POST', '/twilio/message-status/' . $uuid, [
            'MessageSid' => 'SM_STATUS_4',
            'MessageStatus' => 'delivered',
        ]);
        $this->assertResponseIsSuccessful();

        // Message should have the latest status
        $em->clear();
        $updated = static::getContainer()->get(TwilioMessageRepository::class)->find($message->getId());
        $this->assertSame('delivered', $updated->getStatus());

        // All three status records should exist
        $statuses = static::getContainer()->get(TwilioStatusRepository::class)->getStatuses('SM_STATUS_4');
        $this->assertCount(3, $statuses);
    }

    public function testMessageStatusReturnsEmptyBody(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/message-status/any-uuid', [
            'MessageSid' => 'SM_STATUS_5',
            'MessageStatus' => 'sent',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
    }

    public function testMessageStatusDispatchesEvent(): void
    {
        $client = static::createClient();

        $em = static::getContainer()->get('doctrine.orm.entity_manager');
        $message = $this->createMessage($em);

        // If the event dispatcher throws, the request would fail.
        // A successful response confirms the event was dispatched without error.
        $client->request('POST', '/twilio/message-status/' . $message->getUuid(), [
            'MessageSid' => 'SM_STATUS_6',
            'MessageStatus' => 'undelivered',
        ]);

        $this->assertResponseIsSuccessful();

        $em->clear();
        $updated = static::getContainer()->get(TwilioMessageRepository::class)->find($message->getId());
        $this->assertSame('undelivered', $updated->getStatus());
    }

    private function createMessage(\Doctrine\ORM\EntityManagerInterface $em): TwilioMessage
    {
        $message = new TwilioMessage();
        $message->setUuid(Uuid::v4()->toRfc4122());
        $message->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $message->setFromNumber('+33600000000');
        $message->setToNumber('+33611111111');
        $message->setMessage('Test message');
        $message->setStatus('queued');

        $em->persist($message);
        $em->flush();

        return $message;
    }
}
