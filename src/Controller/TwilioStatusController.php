<?php

namespace App\Controller;

use App\Entity\TwilioStatus;
use App\Event\TwilioEvent;
use App\Event\TwilioMessageEvent;
use App\Manager\TwilioMessageManager;
use App\Manager\TwilioStatusManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;

#[Route(path: '/twilio', name: 'twilio_')]
class TwilioStatusController extends AbstractTwilioController
{
    private readonly LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        #[Autowire(env: 'TWILIO_AUTH_TOKEN')]
        string $twilioAuthToken,
        private readonly TwilioMessageManager $messageManager,
        private readonly TwilioStatusManager $statusManager,
        private readonly EventDispatcherInterface $eventDispatcher,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($requestStack, $twilioAuthToken);

        $this->logger = $logger ?? new NullLogger();
    }

    #[Route(path: '/message-status/{uuid}', name: 'status')]
    public function messageStatus(Request $request, string $uuid): Response
    {
        $this->validateRequestSignature($request);

        $this->logger->info('Twilio webhooks - message delivery status', [
            'headers' => $request->headers->all(),
            'query' => $request->query->all(),
            'request' => $request->request->all(),
        ]);

        $outbound = $this->messageManager->get($uuid);

        if ($outbound) {
            $outbound->setStatus($request->request->get('MessageStatus'));
            $this->eventDispatcher->dispatch(new TwilioMessageEvent($outbound), TwilioEvent::STATUS_UPDATED);
            $this->messageManager->save($outbound);
        }

        $status = new TwilioStatus();
        $status->setSid($request->request->get('MessageSid'));
        $status->setStatus($request->request->get('MessageStatus'));
        $this->statusManager->save($status);

        return new Response();
    }
}
