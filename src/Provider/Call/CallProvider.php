<?php

namespace App\Provider\Call;

interface CallProvider
{
    /**
     * @param array<string, mixed> $context
     *
     * @return string|null The call SID
     */
    public function send(string $from, string $to, array $context = [], ?string $content = null): ?string;
}
