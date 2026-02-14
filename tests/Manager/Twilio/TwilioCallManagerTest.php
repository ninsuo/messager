<?php

namespace App\Tests\Manager\Twilio;

use App\Entity\Twilio\TwilioCall;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Manager\Twilio\TwilioCallManager;
use App\Repository\Twilio\TwilioCallRepository;
use App\Service\TwilioClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\Rest\Api\V2010\Account\CallInstance;
use Twilio\TwiML\VoiceResponse;

class TwilioCallManagerTest extends TestCase
{
    private function createManager(
        TwilioCallRepository $repository,
        TwilioClient $twilioClient,
        EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ): TwilioCallManager {
        return new TwilioCallManager(
            $repository,
            $twilioClient,
            $eventDispatcher,
            $logger ?? new NullLogger(),
        );
    }

    // ── get / getBySid / save ──────────────────────────────────────────

    public function testGet(): void
    {
        $entity = new TwilioCall();
        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'some-uuid'])
            ->willReturn($entity);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->assertSame($entity, $manager->get('some-uuid'));
    }

    public function testGetReturnsNull(): void
    {
        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'unknown'])
            ->willReturn(null);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->assertNull($manager->get('unknown'));
    }

    public function testGetBySid(): void
    {
        $entity = new TwilioCall();
        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['sid' => 'CA123'])
            ->willReturn($entity);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        $this->assertSame($entity, $manager->getBySid('CA123'));
    }

    public function testSave(): void
    {
        $entity = new TwilioCall();
        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($entity);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
        );

        $manager->save($entity);
    }

    // ── handleIncomingCall ─────────────────────────────────────────────

    public function testHandleIncomingCall(): void
    {
        $parameters = [
            'From' => '+33612345678',
            'To' => '+33698765432',
            'CallSid' => 'CA_IN_1',
            'CallStatus' => 'ringing',
        ];

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(TwilioCall::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TwilioCallEvent::class),
                TwilioEvent::CALL_RECEIVED,
            )
            ->willReturnCallback(function (TwilioCallEvent $event) {
                $entity = $event->getCall();
                $this->assertSame('+33612345678', $entity->getFromNumber());
                $this->assertSame('+33698765432', $entity->getToNumber());
                $this->assertSame('CA_IN_1', $entity->getSid());
                $this->assertSame('ringing', $entity->getStatus());
                $this->assertSame(TwilioCall::DIRECTION_INBOUND, $entity->getDirection());
                $this->assertNotEmpty($entity->getUuid());

                return $event;
            });

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleIncomingCall($parameters);

        $this->assertNull($response);
    }

    public function testHandleIncomingCallWithVoiceResponse(): void
    {
        $parameters = [
            'From' => '+33600000000',
            'To' => '+33611111111',
            'CallSid' => 'CA_IN_2',
            'CallStatus' => 'ringing',
        ];

        $voiceResponse = new VoiceResponse();
        $voiceResponse->say('Hello');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (TwilioCallEvent $event) use ($voiceResponse) {
                $event->setResponse($voiceResponse);

                return $event;
            });

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->callback(function (TwilioCall $entity) use ($voiceResponse) {
                // On the second save, message should contain the XML
                static $callCount = 0;
                $callCount++;
                if ($callCount === 2) {
                    return $entity->getMessage() === $voiceResponse->asXML();
                }

                return true;
            }));

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleIncomingCall($parameters);

        $this->assertSame($voiceResponse, $response);
    }

    public function testHandleIncomingCallWithNonVoiceResponse(): void
    {
        $parameters = [
            'From' => '+33600000000',
            'To' => '+33611111111',
            'CallSid' => 'CA_IN_3',
            'CallStatus' => 'ringing',
        ];

        $redirectResponse = new RedirectResponse('/some-url');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (TwilioCallEvent $event) use ($redirectResponse) {
                $event->setResponse($redirectResponse);

                return $event;
            });

        $manager = $this->createManager(
            $this->createStub(TwilioCallRepository::class),
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleIncomingCall($parameters);

        // Non-VoiceResponse is returned but message is not stored
        $this->assertSame($redirectResponse, $response);
    }

    // ── sendCall ───────────────────────────────────────────────────────

    public function testSendCallWithVoiceResponse(): void
    {
        $voiceResponse = new VoiceResponse();
        $voiceResponse->say('Outbound call');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TwilioCallEvent::class),
                TwilioEvent::CALL_ESTABLISHED,
            )
            ->willReturnCallback(function (TwilioCallEvent $event) use ($voiceResponse) {
                $event->setResponse($voiceResponse);

                return $event;
            });

        $callInstance = $this->createStub(CallInstance::class);
        $callInstance->sid = 'CA_OUT_1';
        $callInstance->status = 'queued';

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('createCall')
            ->with(
                '+33698765432',
                '+33612345678',
                $this->callback(function (array $opts) use ($voiceResponse) {
                    return $opts['Twiml'] === $voiceResponse->asXML()
                        && !isset($opts['url']);
                }),
            )
            ->willReturn($callInstance);

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TwilioCall $entity) {
                return $entity->getDirection() === TwilioCall::DIRECTION_OUTBOUND
                    && $entity->getFromNumber() === '+33612345678'
                    && $entity->getToNumber() === '+33698765432'
                    && $entity->getSid() === 'CA_OUT_1'
                    && $entity->getStatus() === 'queued';
            }));

        $manager = $this->createManager($repository, $twilioClient, $eventDispatcher);

        $entity = $manager->sendCall('+33612345678', '+33698765432');

        $this->assertSame(TwilioCall::DIRECTION_OUTBOUND, $entity->getDirection());
        $this->assertSame('CA_OUT_1', $entity->getSid());
        $this->assertSame('queued', $entity->getStatus());
        $this->assertNotEmpty($entity->getUuid());
    }

    public function testSendCallWithRedirectResponse(): void
    {
        $redirectResponse = new RedirectResponse('https://example.com/twiml');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (TwilioCallEvent $event) use ($redirectResponse) {
                $event->setResponse($redirectResponse);

                return $event;
            });

        $callInstance = $this->createStub(CallInstance::class);
        $callInstance->sid = 'CA_OUT_2';
        $callInstance->status = 'queued';

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('createCall')
            ->with(
                '+33698765432',
                '+33612345678',
                $this->callback(function (array $opts) {
                    return $opts['url'] === 'https://example.com/twiml'
                        && !isset($opts['Twiml']);
                }),
            )
            ->willReturn($callInstance);

        $manager = $this->createManager(
            $this->createStub(TwilioCallRepository::class),
            $twilioClient,
            $eventDispatcher,
        );

        $entity = $manager->sendCall('+33612345678', '+33698765432');

        $this->assertSame('CA_OUT_2', $entity->getSid());
    }

    public function testSendCallWithContext(): void
    {
        $voiceResponse = new VoiceResponse();
        $voiceResponse->say('ctx');

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnCallback(function (TwilioCallEvent $event) use ($voiceResponse) {
            $event->setResponse($voiceResponse);

            return $event;
        });

        $callInstance = $this->createStub(CallInstance::class);
        $callInstance->sid = 'CA_CTX';
        $callInstance->status = 'queued';

        $twilioClient = $this->createStub(TwilioClient::class);
        $twilioClient->method('createCall')->willReturn($callInstance);

        $manager = $this->createManager(
            $this->createStub(TwilioCallRepository::class),
            $twilioClient,
            $eventDispatcher,
        );

        $context = ['campaign' => 'emergency', 'priority' => 1];
        $entity = $manager->sendCall('+33600000000', '+33611111111', $context);

        $this->assertSame($context, $entity->getContext());
    }

    public function testSendCallNoResponseThrows(): void
    {
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (TwilioCallEvent $event, string $eventName) {
                // First dispatch: CALL_ESTABLISHED — no response set → triggers LogicException
                // Second dispatch: CALL_ERROR — from the catch block
                if ($eventName === TwilioEvent::CALL_ERROR) {
                    $this->assertSame('error', $event->getCall()->getStatus());
                }

                return $event;
            });

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Unable to send call', $this->callback(function (array $ctx) {
                return str_contains($ctx['exception'], 'no responses were provided');
            }));

        $manager = $this->createManager(
            $this->createStub(TwilioCallRepository::class),
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
            $logger,
        );

        $entity = $manager->sendCall('+33612345678', '+33698765432');

        $this->assertSame('error', $entity->getStatus());
        $this->assertStringContainsString('no responses were provided', $entity->getError());
    }

    public function testSendCallTwilioError(): void
    {
        $voiceResponse = new VoiceResponse();
        $voiceResponse->say('Will fail');

        $dispatchCount = 0;
        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->exactly(2))
            ->method('dispatch')
            ->willReturnCallback(function (TwilioCallEvent $event, string $eventName) use ($voiceResponse, &$dispatchCount) {
                $dispatchCount++;
                if ($dispatchCount === 1) {
                    $this->assertSame(TwilioEvent::CALL_ESTABLISHED, $eventName);
                    $event->setResponse($voiceResponse);
                } else {
                    $this->assertSame(TwilioEvent::CALL_ERROR, $eventName);
                }

                return $event;
            });

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('createCall')
            ->willThrowException(new \RuntimeException('Twilio API error'));

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Unable to send call', $this->callback(function (array $ctx) {
                return $ctx['phoneNumber'] === '+33698765432'
                    && $ctx['exception'] === 'Twilio API error';
            }));

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TwilioCall $entity) {
                return $entity->getStatus() === 'error'
                    && $entity->getError() === 'Twilio API error';
            }));

        $manager = $this->createManager($repository, $twilioClient, $eventDispatcher, $logger);

        $entity = $manager->sendCall('+33612345678', '+33698765432');

        $this->assertSame('error', $entity->getStatus());
        $this->assertSame('Twilio API error', $entity->getError());
        $this->assertNull($entity->getSid());
    }

    public function testSendCallAlwaysSavesEvenOnError(): void
    {
        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(TwilioCall::class));

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $manager->sendCall('+33600000000', '+33611111111');
    }

    // ── handleCallEstablished ─────────────────────────────────────────

    public function testHandleCallEstablished(): void
    {
        $call = new TwilioCall();
        $call->setUuid('est-uuid');

        $voiceResponse = new VoiceResponse();
        $voiceResponse->say('Welcome');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn (TwilioCallEvent $e) => $e->getCall() === $call),
                TwilioEvent::CALL_ESTABLISHED,
            )
            ->willReturnCallback(function (TwilioCallEvent $event) use ($voiceResponse) {
                $event->setResponse($voiceResponse);

                return $event;
            });

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($call);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleCallEstablished($call);

        $this->assertSame($voiceResponse, $response);
        $this->assertSame($voiceResponse->asXML(), $call->getMessage());
    }

    public function testHandleCallEstablishedWithRedirect(): void
    {
        $call = new TwilioCall();
        $call->setUuid('est-redirect');

        $redirect = new RedirectResponse('https://example.com/twiml');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (TwilioCallEvent $event) use ($redirect) {
                $event->setResponse($redirect);

                return $event;
            });

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository->expects($this->once())->method('save')->with($call);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleCallEstablished($call);

        $this->assertSame($redirect, $response);
        $this->assertSame('https://example.com/twiml', $call->getMessage());
    }

    public function testHandleCallEstablishedNoResponse(): void
    {
        $call = new TwilioCall();
        $call->setUuid('est-null');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository->expects($this->once())->method('save')->with($call);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleCallEstablished($call);

        $this->assertNull($response);
        $this->assertNull($call->getMessage());
    }

    // ── handleKeyPressed ──────────────────────────────────────────────

    public function testHandleKeyPressed(): void
    {
        $call = new TwilioCall();
        $call->setUuid('key-uuid');

        $voiceResponse = new VoiceResponse();
        $voiceResponse->say('You pressed 1');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(function (TwilioCallEvent $e) use ($call) {
                    return $e->getCall() === $call && $e->getKeyPressed() === '1';
                }),
                TwilioEvent::CALL_KEY_PRESSED,
            )
            ->willReturnCallback(function (TwilioCallEvent $event) use ($voiceResponse) {
                $event->setResponse($voiceResponse);

                return $event;
            });

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository->expects($this->once())->method('save')->with($call);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleKeyPressed($call, '1');

        $this->assertSame($voiceResponse, $response);
        $this->assertSame($voiceResponse->asXML(), $call->getMessage());
    }

    public function testHandleKeyPressedNoResponse(): void
    {
        $call = new TwilioCall();
        $call->setUuid('key-null');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnArgument(0);

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository->expects($this->once())->method('save')->with($call);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $response = $manager->handleKeyPressed($call, '5');

        $this->assertNull($response);
    }

    // ── handleAnsweringMachine ────────────────────────────────────────

    public function testHandleAnsweringMachine(): void
    {
        $call = new TwilioCall();
        $call->setUuid('amd-uuid');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->callback(fn (TwilioCallEvent $e) => $e->getCall() === $call),
                TwilioEvent::CALL_ANSWERING_MACHINE,
            )
            ->willReturnArgument(0);

        $repository = $this->createMock(TwilioCallRepository::class);
        $repository->expects($this->once())->method('save')->with($call);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
        );

        $manager->handleAnsweringMachine($call);
    }
}
