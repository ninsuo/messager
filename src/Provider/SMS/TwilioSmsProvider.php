<?php

namespace App\Provider\SMS;

use App\Manager\TwilioMessageManager;

readonly class TwilioSmsProvider implements SmsProvider
{
    public function __construct(
        private TwilioMessageManager $messageManager,
    ) {
    }

    public function send(string $from, string $to, string $message, array $context = []): ?string
    {
        $twilioMessage = $this->messageManager->sendMessage($from, $to, $message, $context);

        return $twilioMessage->getSid();
    }
}
