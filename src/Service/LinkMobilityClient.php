<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class LinkMobilityClient
{
    private readonly HttpClientInterface $httpClient;

    public function __construct(
        HttpClientInterface $httpClient,
        #[Autowire(env: 'LINK_MOBILITY_API_KEY')]
        private readonly string $apiKey,
        #[Autowire(env: 'LINK_MOBILITY_API_SECRET')]
        private readonly string $apiSecret,
        #[Autowire(env: 'LINK_MOBILITY_API_URL')]
        private readonly string $apiUrl,
        #[Autowire(env: 'LINK_MOBILITY_SENDER')]
        private readonly string $sender,
        #[Autowire(env: 'HTTP_PROXY')]
        string $httpProxy,
    ) {
        $this->httpClient = 'none' !== $httpProxy
            ? $httpClient->withOptions(['proxy' => $httpProxy])
            : $httpClient;
    }

    /**
     * @param array<string, mixed> $extra
     *
     * @return array<string, mixed>
     */
    public function send(string $msisdn, string $text, int $serviceId, array $extra = []): array
    {
        $body = array_merge([
            'msisdn' => $msisdn,
            'sc' => $this->sender,
            'text' => $text,
            'service_id' => $serviceId,
            'registered_delivery' => true,
        ], $extra);

        $json = json_encode($body, \JSON_THROW_ON_ERROR);
        $signature = hash_hmac('sha512', $json, $this->apiSecret);

        $response = $this->httpClient->request('POST', rtrim($this->apiUrl, '/') . '/send', [
            'headers' => [
                'Content-Type' => 'application/json',
                'x-api-key' => $this->apiKey,
                'x-api-sign' => $signature,
                'Expect' => '',
            ],
            'body' => $json,
        ]);

        /** @var array{meta?: array<string, mixed>, data?: array<string, mixed>} $result */
        $result = $response->toArray();

        $code = $result['meta']['code'] ?? 0;
        if (200 !== $code) {
            throw new \RuntimeException(sprintf(
                'LINK Mobility API error %d: %s',
                $code,
                $result['meta']['text'] ?? 'Unknown error',
            ));
        }

        return $result['data'] ?? [];
    }
}
