<?php

namespace App\Tool;

use Symfony\Component\DependencyInjection\Attribute\Autowire;

class Encryption
{
    public const VERSION = 1;

    private readonly string $encryptionKey;
    private readonly string $hmacKey;

    public function __construct(
        #[Autowire(env: 'APP_SECRET')] string $appSecret,
    ) {
        // Derive separate keys for encryption and blind indexing via HKDF
        $this->encryptionKey = hash_hkdf('sha256', $appSecret, \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_KEYBYTES, 'encryption');
        $this->hmacKey = hash_hkdf('sha256', $appSecret, 32, 'blind-index');
    }

    public function encrypt(#[\SensitiveParameter] string $plaintext, string $associatedData): string
    {
        $nonce = random_bytes(\SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES);

        $ciphertext = sodium_crypto_aead_xchacha20poly1305_ietf_encrypt(
            $plaintext,
            $associatedData,
            $nonce,
            $this->encryptionKey,
        );

        $urlsafe = \rtrim(\strtr(base64_encode($nonce.$ciphertext), '+/', '-_'), '=');

        return sprintf('%d-%s', self::VERSION, $urlsafe);
    }

    public function decrypt(string $encrypted, string $associatedData): string
    {
        $version = \substr($encrypted, 0, \strpos($encrypted, '-'));
        $urlsafe = \substr($encrypted, \strpos($encrypted, '-') + 1);
        $binary = base64_decode(\strtr($urlsafe, '-_', '+/'));

        return match ($version) {
            '1' => $this->decryptV1($binary, $associatedData),
            default => throw new \InvalidArgumentException('Invalid or missing encryption version'),
        };
    }

    public function blindHash(#[\SensitiveParameter] string $cleartext, string $className): string
    {
        return hash_hmac('sha256', \mb_strtolower($cleartext), $this->hmacKey.$className);
    }

    private function decryptV1(string $binary, string $associatedData): string
    {
        $nonceLength = \SODIUM_CRYPTO_AEAD_XCHACHA20POLY1305_IETF_NPUBBYTES;
        $nonce = \substr($binary, 0, $nonceLength);
        $ciphertext = \substr($binary, $nonceLength);

        try {
            $plaintext = sodium_crypto_aead_xchacha20poly1305_ietf_decrypt(
                $ciphertext,
                $associatedData,
                $nonce,
                $this->encryptionKey,
            );
        } catch (\SodiumException) {
            throw new \InvalidArgumentException('Decryption failed: invalid ciphertext or tampered data');
        }

        if (false === $plaintext) {
            throw new \InvalidArgumentException('Decryption failed: invalid ciphertext or tampered data');
        }

        return $plaintext;
    }
}
