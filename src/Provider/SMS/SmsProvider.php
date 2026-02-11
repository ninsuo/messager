<?php

namespace App\Provider\SMS;

interface SmsProvider
{
    /**
     * @param array<string, mixed> $context
     *
     * @return string|null The message SID
     */
    public function send(string $to, string $message, array $context = []): ?string;
}
