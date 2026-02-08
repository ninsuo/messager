<?php

namespace App\Event;

use App\Entity\TwilioCall;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\EventDispatcher\Event;
use Twilio\TwiML\VoiceResponse;

class TwilioCallEvent extends Event
{
    private VoiceResponse|Response|null $response = null;

    public function __construct(
        private readonly TwilioCall $call,
        private readonly ?string $keyPressed = null,
    ) {
    }

    public function getCall(): TwilioCall
    {
        return $this->call;
    }

    public function getKeyPressed(): ?string
    {
        return $this->keyPressed;
    }

    public function getResponse(): VoiceResponse|Response|null
    {
        return $this->response;
    }

    public function setResponse(VoiceResponse|Response $response): static
    {
        $this->response = $response;

        return $this;
    }
}
