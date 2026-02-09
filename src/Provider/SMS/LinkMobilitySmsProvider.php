<?php

namespace App\Provider\SMS;

use App\Service\LinkMobilityClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class LinkMobilitySmsProvider implements SmsProvider
{
    public function __construct(
        private LinkMobilityClient $client,
        #[Autowire(env: 'int:LINK_MOBILITY_SMS_SERVICE_ID')]
        private int $serviceId,
    ) {
    }

    public function send(string $from, string $to, string $message, array $context = []): ?string
    {
        $data = $this->client->send($to, $message, $this->serviceId);

        return $data['msg_id'] ?? null;
    }
}
