<?php

namespace App\Manager;

use App\Entity\TwilioMessage;
use App\Event\TwilioEvent;
use App\Event\TwilioMessageEvent;
use App\Repository\TwilioMessageRepository;
use App\Service\TwilioClient;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Routing\RouterInterface;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\Rest\Client;
use Twilio\TwiML\MessagingResponse;

class TwilioMessageManager
{
    private readonly LoggerInterface $logger;

    public function __construct(
        private readonly TwilioMessageRepository $messageRepository,
        private readonly TwilioClient $twilio,
        private readonly EventDispatcherInterface $eventDispatcher,
        private readonly RouterInterface $router,
        #[Autowire(env: 'WEBSITE_URL')]
        private readonly string $websiteUrl,
        ?LoggerInterface $logger = null,
    ) {
        $this->logger = $logger ?? new NullLogger();
    }

    public function get(string $uuid): ?TwilioMessage
    {
        return $this->messageRepository->findOneBy(['uuid' => $uuid]);
    }

    public function getBySid(string $sid): ?TwilioMessage
    {
        return $this->messageRepository->findOneBy(['sid' => $sid]);
    }

    public function save(TwilioMessage $outbound): void
    {
        $this->messageRepository->save($outbound);
    }

    /**
     * @param array<string, string> $parameters
     */
    public function handleInboundMessage(array $parameters): ?MessagingResponse
    {
        $entity = new TwilioMessage();
        $entity->setUuid(Uuid::v4()->toRfc4122());
        $entity->setDirection(TwilioMessage::DIRECTION_INBOUND);
        $entity->setMessage($parameters['Body']);
        $entity->setFromNumber($parameters['From']);
        $entity->setToNumber($parameters['To']);
        $entity->setSid($parameters['MessageSid']);

        $this->messageRepository->save($entity);

        $event = new TwilioMessageEvent($entity);
        $this->eventDispatcher->dispatch($event, TwilioEvent::MESSAGE_RECEIVED);
        $this->messageRepository->save($entity);

        return $event->getResponse();
    }

    /**
     * @param array<string, mixed> $context
     * @param array<string, mixed> $options
     */
    public function sendMessage(
        string $from,
        string $to,
        string $message,
        array $context = [],
        array $options = [],
    ): TwilioMessage {
        if (!array_key_exists('messageUuid', $options)) {
            $options['messageUuid'] = Uuid::v4()->toRfc4122();
        }

        if (!array_key_exists('statusCallback', $options)) {
            $options['statusCallback'] = sprintf(
                '%s%s',
                rtrim($this->websiteUrl, '/'),
                $this->router->generate('twilio_status', ['uuid' => $options['messageUuid']]),
            );
        }

        $entity = new TwilioMessage();
        $entity->setUuid($options['messageUuid']);
        $entity->setDirection(TwilioMessage::DIRECTION_OUTBOUND);
        $entity->setMessage($message);
        $entity->setFromNumber($from);
        $entity->setToNumber($to);
        $entity->setContext($context);

        try {
            $outbound = $this->getClient()->messages->create($to, [
                'from' => $from,
                'body' => $message,
                'statusCallback' => $options['statusCallback'],
            ]);

            $entity->setSid($outbound->sid);
            $entity->setStatus($outbound->status);

            $this->eventDispatcher->dispatch(new TwilioMessageEvent($entity), TwilioEvent::MESSAGE_SENT);
        } catch (\Exception $e) {
            $entity->setStatus('error');
            $entity->setError($e->getMessage());

            $this->eventDispatcher->dispatch(new TwilioMessageEvent($entity), TwilioEvent::MESSAGE_ERROR);

            $this->logger->error('Unable to send SMS', [
                'phoneNumber' => $entity->getToNumber(),
                'context' => $context,
                'exception' => $e->getMessage(),
            ]);
        }

        $this->messageRepository->save($entity);

        return $entity;
    }

    public function fetchPrices(int $retries): void
    {
        $entities = $this->messageRepository->findEntitiesWithoutPrice($retries);

        foreach ($entities as $entity) {
            try {
                /** @phpstan-ignore method.notFound (Twilio SDK magic method) */
                $message = $this->getClient()->messages($entity->getSid())->fetch();
            } catch (\Exception $e) {
                $this->logger->error('Unable to fetch Twilio message', [
                    'id' => $entity->getId(),
                    'sid' => $entity->getSid(),
                    'exception' => $e->getMessage(),
                ]);

                $this->messageRepository->save($entity);

                continue;
            }

            if ($message->status) {
                $entity->setStatus($message->status);
            }

            if ($message->price) {
                $entity->setPrice($message->price);
                $entity->setUnit($message->priceUnit);
                $this->eventDispatcher->dispatch(new TwilioMessageEvent($entity), TwilioEvent::MESSAGE_PRICE_UPDATED);
            } else {
                $entity->setRetry($entity->getRetry() + 1);
            }

            $this->messageRepository->save($entity);
        }
    }

    public function iterate(callable $callback): void
    {
        $this->messageRepository->iterate($callback);
    }

    private function getClient(): Client
    {
        return $this->twilio->getClient();
    }
}
