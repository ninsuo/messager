<?php

namespace App\Tool;

class Phone
{
    /**
     * Normalize a French phone number to E.164 format (+33XXXXXXXXX).
     *
     * Accepts formats like:
     *   06 12 34 56 78, 0612345678, +336 12 34 56 78, +33612345678,
     *   06.12.34.56.78, 06-12-34-56-78, 0033612345678, etc.
     *
     * Returns null if the input cannot be recognized as a valid French number.
     */
    public static function normalize(string $phone): ?string
    {
        // Strip all whitespace, dots, dashes, parentheses
        $cleaned = preg_replace('/[\s.\-()]+/', '', $phone);

        if (null === $cleaned || '' === $cleaned) {
            return null;
        }

        // Handle 0033 international prefix (common in France)
        if (str_starts_with($cleaned, '0033')) {
            $cleaned = '+33' . substr($cleaned, 4);
        }

        // Handle 0X local format → +33X
        if (str_starts_with($cleaned, '0') && !str_starts_with($cleaned, '+')) {
            $cleaned = '+33' . substr($cleaned, 1);
        }

        // Must start with +33 and have exactly 11 digits after the +
        if (!preg_match('/^\+33[1-9]\d{8}$/', $cleaned)) {
            return null;
        }

        return $cleaned;
    }
}
