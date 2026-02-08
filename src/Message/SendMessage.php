<?php

namespace App\Message;

class SendMessage
{
    public function __construct(
        private readonly string $messageUuid,
    ) {
    }

    public function getMessageUuid(): string
    {
        return $this->messageUuid;
    }
}
