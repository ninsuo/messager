<?php

namespace App\Manager;

use App\Entity\TwilioCall;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Repository\TwilioCallRepository;
use App\Service\TwilioClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\TwiML\VoiceResponse;

class TwilioCallManager
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly TwilioCallRepository $callRepository,
        private readonly TwilioClient $twilio,
        private readonly EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function get(string $uuid): ?TwilioCall
    {
        return $this->callRepository->findOneBy(['uuid' => $uuid]);
    }

    public function getBySid(string $sid): ?TwilioCall
    {
        return $this->callRepository->findOneBy(['sid' => $sid]);
    }

    public function save(TwilioCall $call): void
    {
        $this->callRepository->save($call);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function handleIncomingCall(array $parameters): VoiceResponse|Response|null
    {
        $entity = new TwilioCall();
        $entity->setUuid(Uuid::v4()->toRfc4122());
        $entity->setDirection(TwilioCall::DIRECTION_INBOUND);
        $entity->setFromNumber($parameters['From']);
        $entity->setToNumber($parameters['To']);
        $entity->setSid($parameters['CallSid']);
        $entity->setStatus($parameters['CallStatus']);

        $this->callRepository->save($entity);

        $event = new TwilioCallEvent($entity);
        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_RECEIVED);

        if ($event->getResponse()) {
            $response = $event->getResponse();
            if ($response instanceof VoiceResponse) {
                $entity->setMessage($response->asXML());
            }
        }

        $this->callRepository->save($entity);

        return $event->getResponse();
    }

    /**
     * @param array<string, mixed> $context
     */
    public function sendCall(
        string $from,
        string $to,
        array $context = [],
    ): TwilioCall {
        $entity = new TwilioCall();
        $entity->setUuid(Uuid::v4()->toRfc4122());
        $entity->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $entity->setFromNumber($from);
        $entity->setToNumber($to);
        $entity->setContext($context);

        try {
            $event = new TwilioCallEvent($entity);
            $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

            $options = [];
            $response = $event->getResponse();
            if ($response instanceof RedirectResponse) {
                $options['url'] = $response->getTargetUrl();
            } elseif ($response instanceof VoiceResponse) {
                $options['Twiml'] = $response->asXML();
            } else {
                throw new \LogicException('Can\'t establish call, no responses were provided.');
            }

            $outbound = $this->twilio->createCall($to, $from, $options);

            $entity->setSid($outbound->sid);
            $entity->setStatus($outbound->status);
        } catch (\Exception $e) {
            $entity->setStatus('error');
            $entity->setError($e->getMessage());

            $this->eventDispatcher->dispatch(new TwilioCallEvent($entity), TwilioEvent::CALL_ERROR);

            $this->logger->error('Unable to send call', [
                'phoneNumber' => $entity->getToNumber(),
                'context' => $context,
                'exception' => $e->getMessage(),
            ]);
        }

        $this->callRepository->save($entity);

        return $entity;
    }

    public function handleCallEstablished(TwilioCall $call): VoiceResponse|Response|null
    {
        $event = new TwilioCallEvent($call);

        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ESTABLISHED);

        $this->storeMessage($event, $call);

        $this->callRepository->save($call);

        return $event->getResponse();
    }

    public function handleKeyPressed(TwilioCall $call, string $keyPressed): VoiceResponse|Response|null
    {
        $event = new TwilioCallEvent($call, $keyPressed);

        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_KEY_PRESSED);

        $this->storeMessage($event, $call);

        $this->callRepository->save($call);

        return $event->getResponse();
    }

    public function handleAnsweringMachine(TwilioCall $call): void
    {
        $event = new TwilioCallEvent($call);

        $this->eventDispatcher->dispatch($event, TwilioEvent::CALL_ANSWERING_MACHINE);

        $this->callRepository->save($call);
    }

    private function storeMessage(TwilioCallEvent $event, TwilioCall $call): void
    {
        $response = $event->getResponse();

        if ($response instanceof VoiceResponse) {
            $call->setMessage($response->asXML());
        } elseif ($response instanceof RedirectResponse) {
            $call->setMessage($response->getTargetUrl());
        }
    }
}
