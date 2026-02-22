<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Entity\Twilio\TwilioCall;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Tests\Trait\EntityFactoryTrait;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\TwiML\VoiceResponse;

class MessageSubscriberTest extends KernelTestCase
{
    use EntityFactoryTrait;

    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
    }

    public function testCallReceivedWithRecentMessagePlaysContent(): void
    {
        $user = $this->createUser('+33600500010');
        $contact = $this->createContact('+33611500010');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Alerte recente.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_INBOUND);
        $call->setFromNumber('+33611500010');
        $call->setToNumber('+33700000000');

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_RECEIVED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('Alerte recente.', $xml);
        $this->assertStringContainsString('fr-FR', $xml);
        $this->assertStringContainsString('Polly.Lea', $xml);
        $this->assertStringContainsString('Polly.Mathieu', $xml);
        $this->assertSame(4, substr_count($xml, 'Je répète.'));
        $this->assertSame(5, substr_count($xml, 'Alerte recente.'));
        $this->assertStringContainsString('<Pause length="2"/>', $xml);

        // Context should be set so events can reference the message
        $context = $call->getContext();
        $this->assertSame($message->getUuid(), $context['message_uuid']);

        // Message should be marked as sent
        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);
        $this->assertSame(Message::STATUS_SENT, $message->getStatus());
    }

    public function testCallReceivedWithNoRecentMessagePlaysError(): void
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_INBOUND);
        $call->setFromNumber('+33611500099');
        $call->setToNumber('+33700000000');

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_RECEIVED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('aucun déclenchement actif', $xml);
    }

    public function testCallReceivedWithOldMessagePlaysError(): void
    {
        $user = $this->createUser('+33600500011');
        $contact = $this->createContact('+33611500011');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Vieux message.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        // Make the message older than 24h
        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $message->setCreatedAt(new \DateTime('-25 hours'));
        $em->flush();

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_INBOUND);
        $call->setFromNumber('+33611500011');
        $call->setToNumber('+33700000000');

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_RECEIVED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('aucun déclenchement actif', $xml);
    }

    public function testCallEstablishedPlaysContentAndMarksDelivered(): void
    {
        $user = $this->createUser('+33600500002');
        $contact = $this->createContact('+33611500003');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Alerte de test.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33611500003');
        $call->setContext(['message_uuid' => $message->getUuid()]);

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('Alerte de test.', $xml);
        $this->assertStringContainsString('fr-FR', $xml);
        $this->assertStringNotContainsString('Gather', $xml);
        $this->assertStringContainsString('Polly.Lea', $xml);
        $this->assertStringContainsString('Polly.Mathieu', $xml);
        $this->assertSame(4, substr_count($xml, 'Je répète.'));
        $this->assertSame(5, substr_count($xml, 'Alerte de test.'));
        $this->assertStringContainsString('<Pause length="2"/>', $xml);

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_SENT, $message->getStatus());
    }

}
