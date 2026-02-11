<?php

namespace App\Provider\SMS;

use App\Manager\TwilioMessageManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TwilioSmsProvider implements SmsProvider
{
    public function __construct(
        private TwilioMessageManager $messageManager,
        #[Autowire(env: 'TWILIO_SENDER_ID')]
        private string $senderId,
    ) {
    }

    public function send(string $to, string $message, array $context = []): ?string
    {
        $twilioMessage = $this->messageManager->sendMessage($this->senderId, $to, $message, $context);

        return $twilioMessage->getSid();
    }
}
