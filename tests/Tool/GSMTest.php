<?php

namespace App\Tests\Tool;

use App\Tool\GSM;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class GSMTest extends TestCase
{
    #[DataProvider('gsmCompatibleProvider')]
    public function testIsGSMCompatible(string $input, bool $expected): void
    {
        $this->assertSame($expected, GSM::isGSMCompatible($input));
    }

    /**
     * @return iterable<string, array{string, bool}>
     */
    public static function gsmCompatibleProvider(): iterable
    {
        yield 'ascii text' => ['Hello world', true];
        yield 'gsm accented' => ['Bonjour à tous', true];
        yield 'euro sign' => ['Price: 10€', true];
        yield 'newline' => ["Line1\nLine2", true];
        yield 'empty string' => ['', true];
        yield 'chinese character' => ['Hello 你好', false];
        yield 'emoji' => ['Hello 😀', false];
        yield 'cyrillic' => ['Привет', false];
    }

    #[DataProvider('transliterateProvider')]
    public function testTransliterate(string $input, string $expected): void
    {
        $this->assertSame($expected, GSM::transliterate($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function transliterateProvider(): iterable
    {
        yield 'plain ascii' => ['Hello', 'Hello'];
        yield 'french quotes' => ['«Bonjour»', '"Bonjour"'];
        yield 'curly quote' => ["\xE2\x80\x99test", "'test"];
        yield 'em dash' => ['A—B', 'A-B'];
        yield 'en dash' => ['A–B', 'A-B'];
        yield 'accented A' => ['Àlpha', 'Alpha'];
        yield 'cyrillic text' => ['Москва', 'Moskva'];
        yield 'oe ligature' => ['cœur', 'coeur'];
        yield 'multiple spaces' => ['too   many', 'too many'];
        yield 'crlf to lf' => ["line1\r\nline2", "line1\nline2"];
        yield 'degree symbol' => ['45°', '450'];
        yield 'superscript digits' => ['m²', 'm2'];
        yield 'curly brackets' => ['{test}', '(test)'];
        yield 'square brackets' => ['[test]', '(test)'];
    }

    #[DataProvider('enforceGSMProvider')]
    public function testEnforceGSMAlphabet(string $input, string $expected): void
    {
        $this->assertSame($expected, GSM::enforceGSMAlphabet($input));
    }

    /**
     * @return iterable<string, array{string, string}>
     */
    public static function enforceGSMProvider(): iterable
    {
        yield 'plain ascii' => ['Hello world', 'Hello world'];
        yield 'emoji replaced' => ['Hi 😀!', 'Hi ?!'];
        yield 'transliterate then enforce' => ['Hêllo wörld', 'Hello wörld'];
        yield 'chinese replaced' => ['Hi 你好', 'Hi ??'];
    }

    public function testGetSMSPartsSingleGSM(): void
    {
        $message = str_repeat('A', 160);
        $parts = GSM::getSMSParts($message);

        $this->assertCount(1, $parts);
        $this->assertSame($message, $parts[0]);
    }

    public function testGetSMSPartsMultipartGSM(): void
    {
        $message = str_repeat('A', 161);
        $parts = GSM::getSMSParts($message);

        $this->assertGreaterThan(1, count($parts));
        $this->assertSame($message, implode('', $parts));
    }

    public function testGetSMSPartsSingleUnicode(): void
    {
        $message = str_repeat('你', 70);
        $parts = GSM::getSMSParts($message);

        $this->assertCount(1, $parts);
        $this->assertSame($message, $parts[0]);
    }

    public function testGetSMSPartsMultipartUnicode(): void
    {
        $message = str_repeat('你', 71);
        $parts = GSM::getSMSParts($message);

        $this->assertGreaterThan(1, count($parts));
        $this->assertSame($message, implode('', $parts));
    }

    public function testGetSMSPartsEscapedCharactersCountDouble(): void
    {
        // 80 euros signs = 160 GSM chars (each € costs 2) → single part
        $message = str_repeat('€', 80);
        $parts = GSM::getSMSParts($message);
        $this->assertCount(1, $parts);

        // 81 euros signs = 162 GSM chars → multipart
        $message = str_repeat('€', 81);
        $parts = GSM::getSMSParts($message);
        $this->assertGreaterThan(1, count($parts));
        $this->assertSame($message, implode('', $parts));
    }

    public function testGetSMSPartsEmptyString(): void
    {
        $parts = GSM::getSMSParts('');
        $this->assertCount(1, $parts);
        $this->assertSame('', $parts[0]);
    }
}
