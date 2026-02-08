<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twilio\Rest\Client;

class TwilioClient
{
    private ?Client $client = null;

    public function __construct(
        #[Autowire(env: 'TWILIO_ACCOUNT_SID')]
        private readonly string $accountSid,
        #[Autowire(env: 'TWILIO_AUTH_TOKEN')]
        private readonly string $authToken,
    ) {
    }

    public function getClient(): Client
    {
        return $this->client ??= new Client($this->accountSid, $this->authToken);
    }
}
