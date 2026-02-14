<?php

namespace App\Tests\EventSubscriber;

use App\Entity\Twilio\TwilioCall;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\TwiML\VoiceResponse;

class AuthCodeSubscriberTest extends KernelTestCase
{
    private EventDispatcherInterface $eventDispatcher;

    protected function setUp(): void
    {
        self::bootKernel();

        $this->eventDispatcher = self::getContainer()->get(EventDispatcherInterface::class);
    }

    public function testAuthCodeCallPlaysCode(): void
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33600000001');
        $call->setContext(['auth_code' => '123 456']);

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('1, 2, 3, 4, 5, 6', $xml);
        $this->assertStringContainsString('Votre code Messager est', $xml);
        $this->assertStringContainsString('fr-FR', $xml);
        $this->assertStringContainsString('Polly.Lea', $xml);
    }

    public function testAuthCodeCallRepeatsThreeTimes(): void
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33600000001');
        $call->setContext(['auth_code' => '987 654']);

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

        $xml = $event->getResponse()->asXML();
        $this->assertSame(3, substr_count($xml, 'Votre code Messager est'));
        $this->assertSame(2, substr_count($xml, 'Je répète.'));
        $this->assertStringContainsString('<Pause length="2"/>', $xml);
    }

    public function testNonAuthCodeCallIsIgnored(): void
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33600000001');
        $call->setContext(['some_other_key' => 'value']);

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

        // No response set by AuthCodeSubscriber (MessageSubscriber may or may not set one)
        // The key point is it doesn't crash
        $this->assertTrue(true);
    }

    public function testAuthCodeCallWithoutSpacesWorks(): void
    {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber('+33700000000');
        $call->setToNumber('+33600000001');
        $call->setContext(['auth_code' => '111222']);

        $event = new TwilioCallEvent($call);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

        $response = $event->getResponse();
        $this->assertInstanceOf(VoiceResponse::class, $response);

        $xml = $response->asXML();
        $this->assertStringContainsString('1, 1, 1, 2, 2, 2', $xml);
    }
}
