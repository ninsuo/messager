<?php

namespace App\Tests\Tool;

use App\Tool\Phone;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class PhoneTest extends TestCase
{
    #[DataProvider('validPhoneProvider')]
    public function testNormalize(string $input, string $expected): void
    {
        $this->assertSame($expected, Phone::normalize($input));
    }

    /**
     * @return array<string, array{string, string}>
     */
    public static function validPhoneProvider(): array
    {
        return [
            'e164 mobile' => ['+33612345678', '+33612345678'],
            'local mobile' => ['0612345678', '+33612345678'],
            'local with spaces' => ['06 12 34 56 78', '+33612345678'],
            'e164 with spaces' => ['+33 6 12 34 56 78', '+33612345678'],
            'local with dots' => ['06.12.34.56.78', '+33612345678'],
            'local with dashes' => ['06-12-34-56-78', '+33612345678'],
            'double zero prefix' => ['0033612345678', '+33612345678'],
            'double zero with spaces' => ['00 33 6 12 34 56 78', '+33612345678'],
            'e164 landline' => ['+33145678901', '+33145678901'],
            'local landline' => ['0145678901', '+33145678901'],
            'mixed separators' => ['06 12.34-56 78', '+33612345678'],
            'parentheses' => ['(+33) 6 12 34 56 78', '+33612345678'],
            'leading/trailing spaces' => ['  0612345678  ', '+33612345678'],
            'local 07' => ['0712345678', '+33712345678'],
            'local 09' => ['0912345678', '+33912345678'],
        ];
    }

    #[DataProvider('invalidPhoneProvider')]
    public function testNormalizeReturnsNullForInvalid(string $input): void
    {
        $this->assertNull(Phone::normalize($input));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function invalidPhoneProvider(): array
    {
        return [
            'empty' => [''],
            'whitespace only' => ['   '],
            'too short' => ['+3361234567'],
            'too long' => ['+336123456789'],
            'no leading digit after 33' => ['+33012345678'],
            'letters' => ['abcdefghij'],
            'us number' => ['+12125551234'],
            'partial' => ['06 12'],
        ];
    }
}
