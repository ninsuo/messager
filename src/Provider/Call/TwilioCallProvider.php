<?php

namespace App\Provider\Call;

use App\Service\TwilioClient;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twilio\TwiML\VoiceResponse;

readonly class TwilioCallProvider implements CallProvider
{
    /** @var list<string> */
    private array $fromNumbers;

    public function __construct(
        private TwilioClient $twilio,
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
        $twiml = $this->buildTwiml($context, $content);

        $call = $this->twilio->createCall($to, $from, [
            'Twiml' => $twiml,
        ]);

        return $call->sid;
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildTwiml(array $context, ?string $content): string
    {
        $response = new VoiceResponse();

        $authCode = $context['auth_code'] ?? null;

        if (null !== $authCode) {
            $digits = str_split(str_replace(' ', '', (string) $authCode));
            $spelled = implode(', ', $digits);

            for ($i = 0; $i < 3; $i++) {
                $response->say(
                    sprintf('Votre code Messager est : %s.', $spelled),
                    ['language' => 'fr-FR', 'voice' => 'Polly.Lea'],
                );

                if ($i < 2) {
                    $response->pause(['length' => 2]);
                    $response->say('Je répète.', ['language' => 'fr-FR', 'voice' => 'Polly.Lea']);
                    $response->pause(['length' => 2]);
                }
            }
        } else {
            $text = $content ?? '';

            for ($i = 0; $i < 5; $i++) {
                $response->say($text, ['language' => 'fr-FR', 'voice' => 'Polly.Lea']);

                if ($i < 4) {
                    $response->pause(['length' => 2]);
                    $response->say('Je répète.', ['language' => 'fr-FR', 'voice' => 'Polly.Mathieu']);
                    $response->pause(['length' => 2]);
                }
            }
        }

        return $response->asXML();
    }
}
