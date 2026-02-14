<?php

namespace App\Controller\Twilio;

use App\Http\TwilioXmlResponse;
use App\Manager\Twilio\TwilioCallManager;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Twilio\TwiML\TwiML;

#[Route(path: '/twilio', name: 'twilio_')]
class TwilioCallController extends AbstractTwilioController
{
    private readonly LoggerInterface $logger;

    public function __construct(
        RequestStack $requestStack,
        #[Autowire(env: 'TWILIO_AUTH_TOKEN')]
        string $twilioAuthToken,
        #[Autowire('%kernel.environment%')]
        string $environment,
        private readonly TwilioCallManager $callManager,
        ?LoggerInterface $logger = null,
    ) {
        parent::__construct($requestStack, $twilioAuthToken, $environment);

        $this->logger = $logger ?? new NullLogger();
    }

    #[Route(path: '/incoming-call', name: 'incoming_call')]
    public function incoming(Request $request): Response
    {
        $this->validateRequestSignature($request);

        $this->logger->info('Twilio webhooks - incoming call', [
            'payload' => $request->request->all(),
        ]);

        $response = $this->callManager->handleIncomingCall(
            $request->request->all(),
        );

        if (!$response) {
            return new Response();
        }

        if ($response instanceof Response) {
            return $response;
        }

        return new TwilioXmlResponse($response->asXml());
    }

    #[Route(path: '/outgoing-call/{uuid}', name: 'outgoing_call')]
    public function outgoing(Request $request, string $uuid): Response
    {
        $this->validateRequestSignature($request);

        $this->logger->info('Twilio webhooks - outgoing call', [
            'payload' => $request->request->all(),
        ]);

        $call = $this->callManager->get($uuid);
        if (!$call) {
            throw $this->createNotFoundException();
        }

        $keys = $request->request->get('Digits');
        if (null === $keys) {
            $response = $this->callManager->handleCallEstablished($call);
        } else {
            $response = $this->callManager->handleKeyPressed($call, $keys);
        }

        if (!$response) {
            return new Response();
        }

        if ($response instanceof TwiML) {
            return new TwilioXmlResponse($response->asXml());
        }

        return $response;
    }

    #[Route(path: '/answering-machine/{uuid}', name: 'answering_machine')]
    public function answeringMachine(Request $request, string $uuid): Response
    {
        $this->validateRequestSignature($request);

        $this->logger->info('Twilio webhooks - answering machine hook', [
            'payload' => $request->request->all(),
        ]);

        $call = $this->callManager->get($uuid);
        if (!$call) {
            throw $this->createNotFoundException();
        }

        if ('machine_start' === $request->request->get('AnsweredBy')) {
            $this->callManager->handleAnsweringMachine($call);
        }

        return new Response();
    }
}
