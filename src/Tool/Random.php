<?php

namespace App\Tool;

class Random
{
    public static function upperalphanumeric(int $size): string
    {
        return self::filtered($size, '/[^A-Z0-9]/');
    }

    public static function upperalphabetic(int $size): string
    {
        return self::filtered($size, '/[^A-Z]/');
    }

    public static function loweralphanumeric(int $size): string
    {
        return self::filtered($size, '/[^a-z0-9]/');
    }

    public static function loweralphabetic(int $size): string
    {
        return self::filtered($size, '/[^a-z]/');
    }

    public static function alphanumeric(int $size): string
    {
        return self::filtered($size, '/[^A-Za-z0-9]/');
    }

    public static function numeric(int $size): string
    {
        return self::filtered($size, '/[^0-9]/');
    }

    public static function bytes(int $size): string
    {
        return random_bytes($size);
    }

    public static function hexadecimalBytes(int $size): string
    {
        return bin2hex(self::bytes($size));
    }

    public static function between(int $a, int $b): int
    {
        if ($a > $b) {
            return self::between($b, $a);
        }

        $max = $b - $a;

        return $a + (unpack('n', self::bytes(2))[1] * time()) % $max;
    }

    private static function filtered(int $size, string $regexp): string
    {
        $string = '';

        do {
            $string .= preg_replace($regexp, '', self::bytes(1024));
        } while (strlen($string) < $size);

        return substr($string, 0, $size);
    }
}
