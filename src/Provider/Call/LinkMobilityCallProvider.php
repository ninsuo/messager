<?php

namespace App\Provider\Call;

use App\Service\LinkMobilityClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class LinkMobilityCallProvider implements CallProvider
{
    public function __construct(
        private LinkMobilityClient $client,
        #[Autowire(env: 'int:LINK_MOBILITY_VOICE_SERVICE_ID')]
        private int $serviceId,
    ) {
    }

    public function send(string $from, string $to, array $context = [], ?string $content = null): ?string
    {
        $data = $this->client->send($to, $content ?? '', $this->serviceId);

        return $data['msg_id'] ?? null;
    }
}
