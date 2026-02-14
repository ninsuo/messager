<?php

namespace App\Controller\Twilio;

use App\Http\TwilioXmlResponse;
use App\Manager\Twilio\TwilioMessageManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route(path: '/twilio', name: 'twilio_')]
class TwilioMessageController extends AbstractTwilioController
{
    private readonly LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        #[Autowire(env: 'TWILIO_AUTH_TOKEN')]
        string $twilioAuthToken,
        #[Autowire('%kernel.environment%')]
        string $environment,
        private readonly TwilioMessageManager $messageManager,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($requestStack, $twilioAuthToken, $environment);

        $this->logger = $logger ?? new NullLogger();
    }

    #[Route(path: '/incoming-message', name: 'incoming_message')]
    public function incoming(Request $request): Response
    {
        $this->validateRequestSignature($request);

        $this->logger->info('Twilio webhooks - incoming message', [
            'payload' => $request->request->all(),
        ]);

        $response = $this->messageManager->handleInboundMessage(
            array_merge(
                $request->query->all(),
                $request->request->all(),
            ),
        );

        if (!$response) {
            return new Response();
        }

        return new TwilioXmlResponse($response->asXml());
    }
}
