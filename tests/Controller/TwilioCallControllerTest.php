<?php

namespace App\Tests\Controller;

use App\Entity\TwilioCall;
use App\Repository\TwilioCallRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Uid\Uuid;

class TwilioCallControllerTest extends WebTestCase
{
    // ── incoming ───────────────────────────────────────────────────────

    public function testIncomingCallReturns200(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/incoming-call', [
            'From' => '+33612345678',
            'To' => '+33698765432',
            'CallSid' => 'CA_CTRL_1',
            'CallStatus' => 'ringing',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testIncomingCallPersistsEntity(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/incoming-call', [
            'From' => '+33600000001',
            'To' => '+33600000002',
            'CallSid' => 'CA_CTRL_2',
            'CallStatus' => 'ringing',
        ]);

        $this->assertResponseIsSuccessful();

        $repository = static::getContainer()->get(TwilioCallRepository::class);
        $calls = $repository->findBy(['sid' => 'CA_CTRL_2']);

        $this->assertCount(1, $calls);

        $entity = $calls[0];
        $this->assertSame(TwilioCall::DIRECTION_INBOUND, $entity->getDirection());
        $this->assertSame('+33600000001', $entity->getFromNumber());
        $this->assertSame('+33600000002', $entity->getToNumber());
        $this->assertSame('CA_CTRL_2', $entity->getSid());
        $this->assertSame('ringing', $entity->getStatus());
        $this->assertNotEmpty($entity->getUuid());
    }

    public function testIncomingCallReturnsNoActiveTriggersMessage(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/incoming-call', [
            'From' => '+33600000003',
            'To' => '+33600000004',
            'CallSid' => 'CA_CTRL_3',
            'CallStatus' => 'ringing',
        ]);

        $this->assertResponseIsSuccessful();

        $body = $client->getResponse()->getContent();
        $this->assertStringContainsString('aucun déclenchement actif', $body);
    }

    // ── outgoing (established) ────────────────────────────────────────

    public function testOutgoingCallEstablished(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);

        $client->request('POST', '/twilio/outgoing-call/' . $call->getUuid());

        $this->assertResponseIsSuccessful();
    }

    public function testOutgoingCallEstablishedReturnsEmptyBodyByDefault(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);

        $client->request('POST', '/twilio/outgoing-call/' . $call->getUuid());

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
    }

    public function testOutgoingCallNotFound(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/outgoing-call/nonexistent-uuid');

        $this->assertResponseStatusCodeSame(404);
    }

    // ── outgoing (key pressed) ────────────────────────────────────────

    public function testOutgoingCallKeyPressed(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);

        $client->request('POST', '/twilio/outgoing-call/' . $call->getUuid(), [
            'Digits' => '1',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testOutgoingCallKeyPressedSavesEntity(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);
        $id = $call->getId();

        $client->request('POST', '/twilio/outgoing-call/' . $call->getUuid(), [
            'Digits' => '5',
        ]);

        $this->assertResponseIsSuccessful();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $updated = static::getContainer()->get(TwilioCallRepository::class)->find($id);
        $this->assertNotNull($updated);
    }

    public function testOutgoingCallKeyPressedNotFound(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/outgoing-call/missing-uuid', [
            'Digits' => '3',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    // ── answering machine ─────────────────────────────────────────────

    public function testAnsweringMachineReturns200(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);

        $client->request('POST', '/twilio/answering-machine/' . $call->getUuid(), [
            'AnsweredBy' => 'machine_start',
        ]);

        $this->assertResponseIsSuccessful();
        $this->assertSame('', $client->getResponse()->getContent());
    }

    public function testAnsweringMachineNotFound(): void
    {
        $client = static::createClient();

        $client->request('POST', '/twilio/answering-machine/missing-uuid', [
            'AnsweredBy' => 'machine_start',
        ]);

        $this->assertResponseStatusCodeSame(404);
    }

    public function testAnsweringMachineIgnoresHumanAnswer(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);

        // "human" should not trigger handleAnsweringMachine
        $client->request('POST', '/twilio/answering-machine/' . $call->getUuid(), [
            'AnsweredBy' => 'human',
        ]);

        $this->assertResponseIsSuccessful();
    }

    public function testAnsweringMachineSavesEntity(): void
    {
        $client = static::createClient();
        $call = $this->createCall($client);
        $id = $call->getId();

        $client->request('POST', '/twilio/answering-machine/' . $call->getUuid(), [
            'AnsweredBy' => 'machine_start',
        ]);

        $this->assertResponseIsSuccessful();

        $em = static::getContainer()->get(EntityManagerInterface::class);
        $em->clear();

        $updated = static::getContainer()->get(TwilioCallRepository::class)->find($id);
        $this->assertNotNull($updated);
    }

    private function createCall($client): TwilioCall
    {
        $em = static::getContainer()->get(EntityManagerInterface::class);

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33600000000');
        $call->setToNumber('+33611111111');
        $call->setSid('CA_FIXTURE_' . bin2hex(random_bytes(4)));
        $call->setStatus('in-progress');

        $em->persist($call);
        $em->flush();

        return $call;
    }
}
