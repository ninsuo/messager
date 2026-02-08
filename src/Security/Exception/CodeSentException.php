<?php

namespace App\Security\Exception;

use Symfony\Component\Security\Core\Exception\AuthenticationException;

class CodeSentException extends AuthenticationException
{
    public function __construct(
        private readonly string $secret,
    ) {
        parent::__construct('Code sent.');
    }

    public function getSecret(): string
    {
        return $this->secret;
    }

    public function getMessageKey(): string
    {
        return 'Code sent.';
    }
}
