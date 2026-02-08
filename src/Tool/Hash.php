<?php

namespace App\Tool;

class Hash
{
    public static function hash(#[\SensitiveParameter] ?string ...$data): string
    {
        return hash('sha256', implode('', $data));
    }

    public static function hmac(string $data, string $key): string
    {
        return hash_hmac('sha256', $data, $key);
    }

    public static function rand(int $length): string
    {
        return self::hash(Random::bytes($length));
    }
}
