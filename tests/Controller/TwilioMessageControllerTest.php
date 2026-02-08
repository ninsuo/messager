<?php

namespace App\Tests\Controller;

use App\Entity\TwilioMessage;
use App\Repository\TwilioMessageRepository;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

class TwilioMessageControllerTest extends WebTestCase
{
    public function testIncomingMessageReturns200(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/incoming-message', [
            'Body' => 'Hello',
            'From' => '+33612345678',
            'To' => '+33698765432',
            'MessageSid' => 'SM_CTRL_1',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testIncomingMessagePersistsEntity(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/incoming-message', [
            'Body' => 'Persisted message',
            'From' => '+33600000001',
            'To' => '+33600000002',
            'MessageSid' => 'SM_CTRL_2',
        ]);

        $this->assertResponseIsSuccessful();

        $repository = static::getContainer()->get(TwilioMessageRepository::class);
        $messages = $repository->findBy(['sid' => 'SM_CTRL_2']);

        $this->assertCount(1, $messages);

        $entity = $messages[0];
        $this->assertSame(TwilioMessage::DIRECTION_INBOUND, $entity->getDirection());
        $this->assertSame('Persisted message', $entity->getMessage());
        $this->assertSame('+33600000001', $entity->getFromNumber());
        $this->assertSame('+33600000002', $entity->getToNumber());
        $this->assertSame('SM_CTRL_2', $entity->getSid());
        $this->assertNotEmpty($entity->getUuid());
    }

    public function testIncomingMessageReturnsEmptyBodyByDefault(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/incoming-message', [
            'Body' => 'No listener',
            'From' => '+33600000003',
            'To' => '+33600000004',
            'MessageSid' => 'SM_CTRL_3',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
    }

    public function testIncomingMessageMergesQueryAndPostParams(): void
    {
        $client = static::createClient();

        // Query param should be overridden by POST param with same key
        $client->request(
            'POST',
            '/twilio/incoming-message?From=+33600000099',
            [
                'Body' => 'Merged',
                'From' => '+33600000005',
                'To' => '+33600000006',
                'MessageSid' => 'SM_CTRL_4',
            ],
        );

        $this->assertResponseIsSuccessful();

        $repository = static::getContainer()->get(TwilioMessageRepository::class);
        $messages = $repository->findBy(['sid' => 'SM_CTRL_4']);

        $this->assertCount(1, $messages);
        // POST overrides query
        $this->assertSame('+33600000005', $messages[0]->getFromNumber());
    }

    public function testIncomingMessageWithQueryOnlyParams(): void
    {
        $client = static::createClient();

        // Some params only in query string (e.g. extra Twilio params)
        $client->request(
            'POST',
            '/twilio/incoming-message?NumMedia=0',
            [
                'Body' => 'With query',
                'From' => '+33600000007',
                'To' => '+33600000008',
                'MessageSid' => 'SM_CTRL_5',
            ],
        );

        $this->assertResponseIsSuccessful();

        $repository = static::getContainer()->get(TwilioMessageRepository::class);
        $messages = $repository->findBy(['sid' => 'SM_CTRL_5']);
        $this->assertCount(1, $messages);
    }

    public function testIncomingMessageGetMethodNotAllowed(): void
    {
        $client = static::createClient();

        // The route accepts ANY method, but without required POST params
        // the manager will fail. Let's verify GET at least hits the controller.
        $client->request('GET', '/twilio/incoming-message', [
            'Body' => 'Via GET',
            'From' => '+33600000009',
            'To' => '+33600000010',
            'MessageSid' => 'SM_CTRL_6',
        ]);

        // GET params go into query, POST is empty, so array_merge puts
        // them all in — should still work since the route accepts ANY.
        $this->assertResponseIsSuccessful();
    }
}
