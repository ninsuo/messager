<?php

namespace App\Event;

use App\Entity\Twilio\TwilioMessage;
use Symfony\Contracts\EventDispatcher\Event;
use Twilio\TwiML\MessagingResponse;

class TwilioMessageEvent extends Event
{
    private ?MessagingResponse $response = null;

    public function __construct(
        private readonly TwilioMessage $message,
    ) {
    }

    public function getMessage(): TwilioMessage
    {
        return $this->message;
    }

    public function getResponse(): ?MessagingResponse
    {
        return $this->response;
    }

    public function setResponse(?MessagingResponse $response): static
    {
        $this->response = $response;

        return $this;
    }
}
