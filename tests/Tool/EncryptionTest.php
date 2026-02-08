<?php

namespace App\Tests\Tool;

use App\Tool\Encryption;
use PHPUnit\Framework\TestCase;

class EncryptionTest extends TestCase
{
    private Encryption $encryption;

    protected function setUp(): void
    {
        $this->encryption = new Encryption('test-secret-key-for-unit-tests!!');
    }

    public function testEncryptDecryptRoundtrip(): void
    {
        $cleartext = 'Hello, world!';

        $cypher = $this->encryption->encrypt($cleartext, 'user-uuid');
        $this->assertNotEquals($cleartext, $cypher);

        $decrypted = $this->encryption->decrypt($cypher, 'user-uuid');
        $this->assertEquals($cleartext, $decrypted);
    }

    public function testNonDeterministic(): void
    {
        $a = $this->encryption->encrypt('same text', 'same-user');
        $b = $this->encryption->encrypt('same text', 'same-user');

        $this->assertNotEquals($a, $b, 'Two encryptions of the same value must produce different ciphertexts');
    }

    public function testWrongAssociatedDataFails(): void
    {
        $cypher = $this->encryption->encrypt('secret', 'user-a');

        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Decryption failed');

        $this->encryption->decrypt($cypher, 'user-b');
    }

    public function testTamperedCiphertextFails(): void
    {
        $cypher = $this->encryption->encrypt('secret', 'user');

        // Decode, flip a byte in the ciphertext, re-encode
        $parts = explode('-', $cypher, 2);
        $binary = base64_decode(strtr($parts[1], '-_', '+/'));
        $binary[intdiv(strlen($binary), 2)] = chr(ord($binary[intdiv(strlen($binary), 2)]) ^ 0xFF);
        $tampered = $parts[0].'-'.\rtrim(\strtr(base64_encode($binary), '+/', '-_'), '=');

        $this->expectException(\InvalidArgumentException::class);
        $this->encryption->decrypt($tampered, 'user');
    }

    public function testVersionPrefix(): void
    {
        $cypher = $this->encryption->encrypt('test', 'user');
        $this->assertStringStartsWith('1-', $cypher);
    }

    public function testUrlSafeOutput(): void
    {
        $cypher = $this->encryption->encrypt('test data with special chars!', 'user');
        $encoded = substr($cypher, 2);
        $this->assertDoesNotMatchRegularExpression('/[+\/=]/', $encoded);
    }

    public function testInvalidVersionThrows(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        $this->expectExceptionMessage('Invalid or missing encryption version');

        $this->encryption->decrypt('99-invaliddata', 'user');
    }

    public function testBlindHashDeterministic(): void
    {
        $hash1 = $this->encryption->blindHash('hello', 'App\\Entity\\User');
        $hash2 = $this->encryption->blindHash('hello', 'App\\Entity\\User');

        $this->assertEquals($hash1, $hash2);
        $this->assertNotEquals('hello', $hash1);
    }

    public function testBlindHashCaseInsensitive(): void
    {
        $hash1 = $this->encryption->blindHash('Hello', 'App\\Entity\\User');
        $hash2 = $this->encryption->blindHash('hello', 'App\\Entity\\User');

        $this->assertEquals($hash1, $hash2);
    }

    public function testBlindHashDifferentClasses(): void
    {
        $hash1 = $this->encryption->blindHash('hello', 'App\\Entity\\User');
        $hash2 = $this->encryption->blindHash('hello', 'App\\Entity\\Other');

        $this->assertNotEquals($hash1, $hash2);
    }

    public function testEmptyPlaintext(): void
    {
        $cypher = $this->encryption->encrypt('', 'user');
        $decrypted = $this->encryption->decrypt($cypher, 'user');

        $this->assertSame('', $decrypted);
    }

    public function testUtf8Plaintext(): void
    {
        $cleartext = 'Bonjour le monde! 日本語 🎉';

        $cypher = $this->encryption->encrypt($cleartext, 'user');
        $decrypted = $this->encryption->decrypt($cypher, 'user');

        $this->assertSame($cleartext, $decrypted);
    }
}
