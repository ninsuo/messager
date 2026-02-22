<?php

namespace App\Provider\Call;

use App\Entity\Fake\FakeCall;
use App\Repository\Fake\FakeCallRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Twilio\TwiML\VoiceResponse;

readonly class FakeCallProvider implements CallProvider
{
    private string $defaultFromNumber;

    public function __construct(
        private FakeCallRepository $fakeCallRepository,
        #[Autowire(env: 'TWILIO_PHONE_NUMBER')]
        string $fromNumbers,
    ) {
        $numbers = array_map('trim', explode(',', $fromNumbers));
        $first = $numbers[array_rand($numbers)];
        $this->defaultFromNumber = str_starts_with($first, '+') ? $first : '+' . $first;
    }

    public function send(string $to, string $message, array $context = []): ?string
    {
        $twiml = $this->buildTwiml($message, $context);

        $fakeCall = new FakeCall();
        $fakeCall->setFromNumber($this->defaultFromNumber);
        $fakeCall->setToNumber($to);
        $fakeCall->setContent($twiml);
        $fakeCall->setContext($context ?: null);

        $this->fakeCallRepository->save($fakeCall);

        return sprintf('FAKE-%s', bin2hex(random_bytes(8)));
    }

    /**
     * @param array<string, mixed> $context
     */
    private function buildTwiml(string $message, array $context): string
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
            for ($i = 0; $i < 5; $i++) {
                $response->say($message, ['language' => 'fr-FR', 'voice' => 'Polly.Lea']);

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
