<?php

namespace App\Provider\Call;

use App\Manager\TwilioCallManager;

readonly class TwilioCallProvider implements CallProvider
{
    public function __construct(
        private readonly TwilioCallManager $callManager,
    ) {
    }

    public function send(string $from, string $to, array $context = []): ?string
    {
        $call = $this->callManager->sendCall($from, $to, true, $context);

        return $call->getSid();
    }
}
