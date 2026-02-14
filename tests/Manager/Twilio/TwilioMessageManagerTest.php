<?php

namespace App\Tests\Manager\Twilio;

use App\Entity\Twilio\TwilioMessage;
use App\Event\TwilioEvent;
use App\Event\TwilioMessageEvent;
use App\Manager\Twilio\TwilioMessageManager;
use App\Repository\Twilio\TwilioMessageRepository;
use App\Service\TwilioClient;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\Rest\Api\V2010\Account\MessageInstance;

class TwilioMessageManagerTest extends TestCase
{
    private function createManager(
        TwilioMessageRepository $repository,
        TwilioClient $twilioClient,
        EventDispatcherInterface $eventDispatcher,
        RouterInterface $router,
        ?LoggerInterface $logger = null,
    ): TwilioMessageManager {
        return new TwilioMessageManager(
            $repository,
            $twilioClient,
            $eventDispatcher,
            $router,
            'https://www.messager.org',
            $logger ?? new NullLogger(),
        );
    }

    public function testGet(): void
    {
        $entity = new TwilioMessage();
        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'some-uuid'])
            ->willReturn($entity);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(RouterInterface::class),
        );

        $this->assertSame($entity, $manager->get('some-uuid'));
    }

    public function testGetReturnsNull(): void
    {
        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('findOneBy')
            ->with(['uuid' => 'unknown'])
            ->willReturn(null);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(RouterInterface::class),
        );

        $this->assertNull($manager->get('unknown'));
    }

    public function testSave(): void
    {
        $entity = new TwilioMessage();
        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($entity);

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $this->createStub(EventDispatcherInterface::class),
            $this->createStub(RouterInterface::class),
        );

        $manager->save($entity);
    }

    public function testHandleInboundMessage(): void
    {
        $parameters = [
            'Body' => 'Hello world',
            'From' => '+33612345678',
            'To' => '+33698765432',
            'MessageSid' => 'SM123456',
        ];

        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->exactly(2))
            ->method('save')
            ->with($this->isInstanceOf(TwilioMessage::class));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TwilioMessageEvent::class),
                TwilioEvent::MESSAGE_RECEIVED,
            )
            ->willReturnCallback(function (TwilioMessageEvent $event, string $eventName) {
                $entity = $event->getMessage();
                $this->assertSame('Hello world', $entity->getMessage());
                $this->assertSame('+33612345678', $entity->getFromNumber());
                $this->assertSame('+33698765432', $entity->getToNumber());
                $this->assertSame('SM123456', $entity->getSid());
                $this->assertSame(TwilioMessage::DIRECTION_INBOUND, $entity->getDirection());
                $this->assertNotEmpty($entity->getUuid());

                return $event;
            });

        $manager = $this->createManager(
            $repository,
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
            $this->createStub(RouterInterface::class),
        );

        $response = $manager->handleInboundMessage($parameters);

        $this->assertNull($response);
    }

    public function testHandleInboundMessageWithResponse(): void
    {
        $parameters = [
            'Body' => 'Hi',
            'From' => '+33600000000',
            'To' => '+33611111111',
            'MessageSid' => 'SM999',
        ];

        $messagingResponse = new \Twilio\TwiML\MessagingResponse();
        $messagingResponse->message('Auto-reply');

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->willReturnCallback(function (TwilioMessageEvent $event) use ($messagingResponse) {
                $event->setResponse($messagingResponse);

                return $event;
            });

        $manager = $this->createManager(
            $this->createStub(TwilioMessageRepository::class),
            $this->createStub(TwilioClient::class),
            $eventDispatcher,
            $this->createStub(RouterInterface::class),
        );

        $response = $manager->handleInboundMessage($parameters);

        $this->assertSame($messagingResponse, $response);
    }

    public function testSendMessageSuccess(): void
    {
        $router = $this->createMock(RouterInterface::class);
        $router
            ->expects($this->once())
            ->method('generate')
            ->with('twilio_status', $this->callback(fn (array $params) => isset($params['uuid'])))
            ->willReturn('/twilio/status/test-uuid');

        $messageInstance = $this->createStub(MessageInstance::class);
        $messageInstance->sid = 'SM_OUT_123';
        $messageInstance->status = 'queued';

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with(
                '+33698765432',
                $this->callback(function (array $opts) {
                    return $opts['from'] === '+33612345678'
                        && $opts['body'] === 'Test message'
                        && str_contains($opts['statusCallback'], '/twilio/status/');
                }),
            )
            ->willReturn($messageInstance);

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TwilioMessageEvent::class),
                TwilioEvent::MESSAGE_SENT,
            )
            ->willReturnArgument(0);

        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TwilioMessage $entity) {
                return $entity->getDirection() === TwilioMessage::DIRECTION_OUTBOUND
                    && $entity->getFromNumber() === '+33612345678'
                    && $entity->getToNumber() === '+33698765432'
                    && $entity->getMessage() === 'Test message'
                    && $entity->getSid() === 'SM_OUT_123'
                    && $entity->getStatus() === 'queued';
            }));

        $manager = $this->createManager($repository, $twilioClient, $eventDispatcher, $router);

        $entity = $manager->sendMessage('+33612345678', '+33698765432', 'Test message');

        $this->assertSame(TwilioMessage::DIRECTION_OUTBOUND, $entity->getDirection());
        $this->assertSame('+33612345678', $entity->getFromNumber());
        $this->assertSame('+33698765432', $entity->getToNumber());
        $this->assertSame('Test message', $entity->getMessage());
        $this->assertSame('SM_OUT_123', $entity->getSid());
        $this->assertSame('queued', $entity->getStatus());
        $this->assertNotEmpty($entity->getUuid());
    }

    public function testSendMessageWithContext(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/twilio/status/uuid');

        $messageInstance = $this->createStub(MessageInstance::class);
        $messageInstance->sid = 'SM_CTX';
        $messageInstance->status = 'queued';

        $twilioClient = $this->createStub(TwilioClient::class);
        $twilioClient->method('sendMessage')->willReturn($messageInstance);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = $this->createManager(
            $this->createStub(TwilioMessageRepository::class),
            $twilioClient,
            $eventDispatcher,
            $router,
        );

        $context = ['campaign' => 'test', 'user_id' => 42];
        $entity = $manager->sendMessage('+33600000000', '+33611111111', 'ctx msg', $context);

        $this->assertSame($context, $entity->getContext());
    }

    public function testSendMessageWithCustomUuid(): void
    {
        $customUuid = 'custom-uuid-1234';

        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/twilio/status/' . $customUuid);

        $messageInstance = $this->createStub(MessageInstance::class);
        $messageInstance->sid = 'SM_CUSTOM';
        $messageInstance->status = 'queued';

        $twilioClient = $this->createStub(TwilioClient::class);
        $twilioClient->method('sendMessage')->willReturn($messageInstance);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = $this->createManager(
            $this->createStub(TwilioMessageRepository::class),
            $twilioClient,
            $eventDispatcher,
            $router,
        );

        $entity = $manager->sendMessage(
            '+33600000000',
            '+33611111111',
            'custom uuid msg',
            [],
            ['messageUuid' => $customUuid],
        );

        $this->assertSame($customUuid, $entity->getUuid());
    }

    public function testSendMessageWithCustomStatusCallback(): void
    {
        $customCallback = 'https://example.com/webhook';

        $router = $this->createMock(RouterInterface::class);
        $router->expects($this->never())->method('generate');

        $messageInstance = $this->createStub(MessageInstance::class);
        $messageInstance->sid = 'SM_CB';
        $messageInstance->status = 'queued';

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with(
                '+33611111111',
                $this->callback(fn (array $opts) => $opts['statusCallback'] === $customCallback),
            )
            ->willReturn($messageInstance);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = $this->createManager(
            $this->createStub(TwilioMessageRepository::class),
            $twilioClient,
            $eventDispatcher,
            $router,
        );

        $manager->sendMessage(
            '+33600000000',
            '+33611111111',
            'callback msg',
            [],
            ['statusCallback' => $customCallback, 'messageUuid' => 'cb-uuid'],
        );
    }

    public function testSendMessageStatusCallbackUrl(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/twilio/status/some-uuid');

        $messageInstance = $this->createStub(MessageInstance::class);
        $messageInstance->sid = 'SM_URL';
        $messageInstance->status = 'queued';

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('sendMessage')
            ->with(
                $this->anything(),
                $this->callback(function (array $opts) {
                    return $opts['statusCallback'] === 'https://www.messager.org/twilio/status/some-uuid';
                }),
            )
            ->willReturn($messageInstance);

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = $this->createManager(
            $this->createStub(TwilioMessageRepository::class),
            $twilioClient,
            $eventDispatcher,
            $router,
        );

        $manager->sendMessage('+33600000000', '+33611111111', 'url test');
    }

    public function testSendMessageError(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/twilio/status/uuid');

        $twilioClient = $this->createMock(TwilioClient::class);
        $twilioClient
            ->expects($this->once())
            ->method('sendMessage')
            ->willThrowException(new \RuntimeException('Twilio API error'));

        $eventDispatcher = $this->createMock(EventDispatcherInterface::class);
        $eventDispatcher
            ->expects($this->once())
            ->method('dispatch')
            ->with(
                $this->isInstanceOf(TwilioMessageEvent::class),
                TwilioEvent::MESSAGE_ERROR,
            )
            ->willReturnArgument(0);

        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('error')
            ->with('Unable to send SMS', $this->callback(function (array $ctx) {
                return $ctx['phoneNumber'] === '+33698765432'
                    && $ctx['exception'] === 'Twilio API error';
            }));

        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->callback(function (TwilioMessage $entity) {
                return $entity->getStatus() === 'error'
                    && $entity->getError() === 'Twilio API error';
            }));

        $manager = $this->createManager($repository, $twilioClient, $eventDispatcher, $router, $logger);

        $entity = $manager->sendMessage('+33612345678', '+33698765432', 'This will fail');

        $this->assertSame('error', $entity->getStatus());
        $this->assertSame('Twilio API error', $entity->getError());
        $this->assertNull($entity->getSid());
    }

    public function testSendMessageErrorTruncatesLongError(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/twilio/status/uuid');

        $longError = str_repeat('x', 500);

        $twilioClient = $this->createStub(TwilioClient::class);
        $twilioClient->method('sendMessage')->willThrowException(new \RuntimeException($longError));

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $manager = $this->createManager(
            $this->createStub(TwilioMessageRepository::class),
            $twilioClient,
            $eventDispatcher,
            $router,
        );

        $entity = $manager->sendMessage('+33600000000', '+33611111111', 'long error');

        $this->assertSame(255, mb_strlen($entity->getError()));
    }

    public function testSendMessageAlwaysSavesEvenOnError(): void
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('generate')->willReturn('/twilio/status/uuid');

        $twilioClient = $this->createStub(TwilioClient::class);
        $twilioClient->method('sendMessage')->willThrowException(new \RuntimeException('fail'));

        $eventDispatcher = $this->createStub(EventDispatcherInterface::class);
        $eventDispatcher->method('dispatch')->willReturnArgument(0);

        $repository = $this->createMock(TwilioMessageRepository::class);
        $repository
            ->expects($this->once())
            ->method('save')
            ->with($this->isInstanceOf(TwilioMessage::class));

        $manager = $this->createManager($repository, $twilioClient, $eventDispatcher, $router);

        $manager->sendMessage('+33600000000', '+33611111111', 'must save');
    }
}
