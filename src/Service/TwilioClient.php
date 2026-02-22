<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twilio\Http\CurlClient;
use Twilio\Rest\Api\V2010\Account\CallInstance;
use Twilio\Rest\Api\V2010\Account\MessageInstance;
use Twilio\Rest\Client;

class TwilioClient
{
    private ?Client $client = null;

    public function __construct(
        #[Autowire(env: 'TWILIO_ACCOUNT_SID')]
        private readonly string $accountSid,
        #[Autowire(env: 'TWILIO_AUTH_TOKEN')]
        private readonly string $authToken,
        #[Autowire(env: 'HTTP_PROXY')]
        private readonly string $httpProxy,
    ) {
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendMessage(string $to, array $options = []): MessageInstance
    {
        return $this->getClient()->messages->create($to, $options);
    }

    /**
     * @param array<string, mixed> $options
     */
    public function sendCall(string $to, string $from, array $options = []): CallInstance
    {
        return $this->getClient()->calls->create($to, $from, $options);
    }

    private function getClient(): Client
    {
        if (null === $this->client) {
            $httpClient = 'none' !== $this->httpProxy
                ? new CurlClient([\CURLOPT_PROXY => $this->httpProxy])
                : null;

            $this->client = new Client($this->accountSid, $this->authToken, httpClient: $httpClient);
        }

        return $this->client;
    }
}
