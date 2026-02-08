<?php

namespace App\Message;

class TriggerMessage
{
    public function __construct(
        private readonly string $triggerUuid,
    ) {
    }

    public function getTriggerUuid(): string
    {
        return $this->triggerUuid;
    }
}
