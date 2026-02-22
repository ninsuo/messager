<?php

namespace App\Provider\SMS;

use App\Service\TwilioClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TwilioSmsProvider implements SmsProvider
{
    public function __construct(
        private TwilioClient $twilio,
        #[Autowire(env: 'TWILIO_SENDER_ID')]
        private string $senderId,
    ) {
    }

    public function send(string $to, string $message, array $context = []): ?string
    {
        $outbound = $this->twilio->sendMessage($to, [
            'from' => $this->senderId,
            'body' => $message,
        ]);

        return $outbound->sid;
    }
}
