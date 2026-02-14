<?php

namespace App\Controller\Twilio;

use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Twilio\Security\RequestValidator;

abstract class AbstractTwilioController extends AbstractController
{
    public function __construct(
        private readonly RequestStack $requestStack,
        #[Autowire(env: 'TWILIO_AUTH_TOKEN')]
        private readonly string $twilioAuthToken,
        #[Autowire('%kernel.environment%')]
        private readonly string $environment = 'prod',
    ) {
    }

    protected function validateRequestSignature(Request $request): void
    {
        if ('test' === $this->environment) {
            return;
        }

        $validator = new RequestValidator($this->twilioAuthToken);

        $isMainRequest = $this->requestStack->getMainRequest() === $request;

        $validated = $validator->validate(
            $request->headers->get('X-Twilio-Signature', ''),
            $isMainRequest ? $request->getUri() : $request->query->get('absoluteUri', ''),
            $request->request->all(),
        );

        if (!$validated) {
            throw new BadRequestHttpException();
        }
    }
}
