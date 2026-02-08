<?php

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

class PhoneMaskExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('mask_phone', $this->maskPhone(...)),
        ];
    }

    public function maskPhone(string $phone): string
    {
        $length = mb_strlen($phone);

        if ($length <= 8) {
            return $phone;
        }

        $prefix = mb_substr($phone, 0, 4);
        $suffix = mb_substr($phone, -4);
        $masked = str_repeat('X', $length - 8);

        return $prefix . $masked . $suffix;
    }
}
