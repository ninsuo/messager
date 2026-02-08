<?php

namespace App\Http;

use Symfony\Component\HttpFoundation\Response;

class TwilioXmlResponse extends Response
{
    /**
     * @param array<string, string> $headers
     */
    public function __construct(string $content = '', int $status = 200, array $headers = [])
    {
        parent::__construct($content, $status, array_merge($headers, [
            'Content-Type' => 'text/xml',
        ]));
    }
}
