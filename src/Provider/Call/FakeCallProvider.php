<?php

namespace App\Provider\Call;

use App\Entity\Fake\FakeCall;
use App\Entity\Twilio\TwilioCall;
use App\Event\TwilioCallEvent;
use App\Event\TwilioEvent;
use App\Repository\Fake\FakeCallRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Uid\Uuid;
use Symfony\Contracts\EventDispatcher\EventDispatcherInterface;
use Twilio\TwiML\VoiceResponse;

readonly class FakeCallProvider implements CallProvider
{
    private string $defaultFromNumber;

    public function __construct(
        private FakeCallRepository $fakeCallRepository,
        private EventDispatcherInterface $eventDispatcher,
        #[Autowire(env: 'TWILIO_PHONE_NUMBER')]
        string $fromNumbers,
    ) {
        $numbers = array_map('trim', explode(',', $fromNumbers));
        $first = $numbers[array_rand($numbers)];
        $this->defaultFromNumber = str_starts_with($first, '+') ? $first : '+' . $first;
    }

    public function send(string $to, array $context = [], ?string $content = null): ?string
    {
        $this->triggerHook($this->defaultFromNumber, $to, $context, TwilioEvent::CALL_ESTABLISHED, FakeCall::TYPE_ESTABLISH);

        return sprintf('FAKE-%s', bin2hex(random_bytes(8)));
    }

    /**
     * @param array<string, mixed> $context
     */
    public function triggerHook(
        string $from,
        string $to,
        array $context,
        string $eventType,
        string $hookType,
        ?string $keyPressed = null,
    ): FakeCall {
        $call = new TwilioCall();
        $call->setUuid(Uuid::v4()->toRfc4122());
        $call->setDirection(TwilioCall::DIRECTION_OUTBOUND);
        $call->setFromNumber($from);
        $call->setToNumber($to);
        $call->setContext($context);

        $event = new TwilioCallEvent($call, $keyPressed);
        $this->eventDispatcher->dispatch($event, $eventType);

        $content = null;
        $response = $event->getResponse();
        if ($response instanceof VoiceResponse) {
            $content = $response->asXML();
        }

        $fakeCall = new FakeCall();
        $fakeCall->setFromNumber($from);
        $fakeCall->setToNumber($to);
        $fakeCall->setType($hookType);
        $fakeCall->setContent($content);
        $fakeCall->setContext($context ?: null);

        $this->fakeCallRepository->save($fakeCall);

        return $fakeCall;
    }
}
