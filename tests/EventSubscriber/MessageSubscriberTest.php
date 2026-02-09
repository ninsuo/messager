<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Message;
use App\Entity\Trigger;
use App\Entity\TwilioCall;
use App\Entity\TwilioMessage;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Event\TwilioMessageEvent;
use App\Repository\FakeSmsRepository;
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

    public function testMessageErrorSetsStatusFailed(): void
    {
        $user = $this->createUser('+33600500001');
        $contact = $this->createContact('+33611500001');
        $trigger = $this->createTrigger($user, Trigger::TYPE_SMS, 'Test SMS');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $twilioMessage = new TwilioMessage();
        $twilioMessage->setUuid(Uuid::v4()->toRfc4122());
        $twilioMessage->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $twilioMessage->setFromNumber('+33700000000');
        $twilioMessage->setToNumber('+33611500001');
        $twilioMessage->setContext(['message_uuid' => $message->getUuid()]);
        $twilioMessage->setError('Carrier rejected');

        $event = new TwilioMessageEvent($twilioMessage);
        $this->eventDispatcher->dispatch($event, TwilioEvent::MESSAGE_ERROR);

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_FAILED, $message->getStatus());
        $this->assertSame('Carrier rejected', $message->getError());
    }

    public function testMessageErrorWithoutContextIsIgnored(): void
    {
        $twilioMessage = new TwilioMessage();
        $twilioMessage->setUuid(Uuid::v4()->toRfc4122());
        $twilioMessage->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $twilioMessage->setFromNumber('+33700000000');
        $twilioMessage->setToNumber('+33611500002');
        $twilioMessage->setError('Some error');

        $event = new TwilioMessageEvent($twilioMessage);

        // Should not throw
        $this->eventDispatcher->dispatch($event, TwilioEvent::MESSAGE_ERROR);

        $this->assertTrue(true);
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
        $this->assertStringContainsString('Gather', $xml);

        // Context should be set so key-press events can reference the message
        $context = $call->getContext();
        $this->assertSame($message->getUuid(), $context['message_uuid']);
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

    public function testCallEstablishedReturnsVoiceResponse(): void
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
        $this->assertStringContainsString('Gather', $xml);
    }

    public function testCallKeyPressedZeroRepeatsMessage(): void
    {
        $user = $this->createUser('+33600500003');
        $contact = $this->createContact('+33611500004');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Message a repeter.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33611500004');
        $call->setContext(['message_uuid' => $message->getUuid()]);

        $event = new TwilioCallEvent($call, '0');
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_KEY_PRESSED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('Message a repeter.', $xml);
        $this->assertStringContainsString('Gather', $xml);
    }

    public function testCallKeyPressedOtherKeyMarksDelivered(): void
    {
        $user = $this->createUser('+33600500004');
        $contact = $this->createContact('+33611500005');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Test delivered.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33611500005');
        $call->setContext(['message_uuid' => $message->getUuid()]);

        $event = new TwilioCallEvent($call, '1');
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_KEY_PRESSED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('Bonne journée', $xml);

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_DELIVERED, $message->getStatus());
    }

    public function testCallErrorSetsStatusFailed(): void
    {
        $user = $this->createUser('+33600500005');
        $contact = $this->createContact('+33611500006');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Test error.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33611500006');
        $call->setContext(['message_uuid' => $message->getUuid()]);
        $call->setError('Call failed: busy');

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ERROR);

        $em = self::getContainer()->get(\Doctrine\ORM\EntityManagerInterface::class);
        $em->refresh($message);

        $this->assertSame(Message::STATUS_FAILED, $message->getStatus());
        $this->assertSame('Call failed: busy', $message->getError());
    }

    public function testCallAnsweringMachineFallsBackToSms(): void
    {
        $user = $this->createUser('+33600500006');
        $contact = $this->createContact('+33611500007');
        $trigger = $this->createTrigger($user, Trigger::TYPE_CALL, 'Message vocal test.');
        $trigger->addContact($contact);
        $message = $this->createMessage($trigger, $contact);

        $smsCountBefore = count(
            self::getContainer()->get(FakeSmsRepository::class)->findAll()
        );

        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33611500007');
        $call->setContext(['message_uuid' => $message->getUuid()]);

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ANSWERING_MACHINE);

        $smsCountAfter = count(
            self::getContainer()->get(FakeSmsRepository::class)->findAll()
        );

        $this->assertSame($smsCountBefore + 1, $smsCountAfter);

        // Check the SMS was sent with the right content
        $sms = self::getContainer()->get(FakeSmsRepository::class)->findOneBy([
            'toNumber' => '+33611500007',
        ]);
        $this->assertNotNull($sms);
        $this->assertSame('Message vocal test.', $sms->getMessage());
    }

    public function testCallAnsweringMachineWithoutContextIsIgnored(): void
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33611500008');

        $smsCountBefore = count(
            self::getContainer()->get(FakeSmsRepository::class)->findAll()
        );

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ANSWERING_MACHINE);

        $smsCountAfter = count(
            self::getContainer()->get(FakeSmsRepository::class)->findAll()
        );

        $this->assertSame($smsCountBefore, $smsCountAfter);
    }
}
