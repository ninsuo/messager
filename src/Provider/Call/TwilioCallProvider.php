<?php

namespace App\Provider\Call;

use App\Manager\TwilioCallManager;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

readonly class TwilioCallProvider implements CallProvider
{
    /** @var list<string> */
    private array $fromNumbers;

    public function __construct(
        private readonly TwilioCallManager $callManager,
        #[Autowire(env: 'TWILIO_PHONE_NUMBER')]
        string $fromNumbers,
    ) {
        $this->fromNumbers = array_map(
            static fn (string $n): string => str_starts_with($n, '+') ? $n : '+' . $n,
            array_map('trim', explode(',', $fromNumbers)),
        );
    }

    public function send(string $to, array $context = [], ?string $content = null): ?string
    {
        $from = $this->fromNumbers[array_rand($this->fromNumbers)];
        $call = $this->callManager->sendCall($from, $to, $context);

        return $call->getSid();
    }
}
