<?php

namespace Pimphand\GDrive;

use RuntimeException;

/**
 * AES-256-GCM encryption compatible with pimpdrive backend/src/utils/crypto.ts.
 */
class TokenCrypto
{
    public function __construct(
        private readonly string $encryptionKey,
    ) {}

    public static function fromConfig(): self
    {
        $key = config('gdrive.token_encryption_key');

        if (! is_string($key) || $key === '') {
            throw new RuntimeException('gdrive.token_encryption_key is not configured.');
        }

        return new self($key);
    }

    public function encrypt(string $value): string
    {
        $key = hash('sha256', $this->encryptionKey, true);
        $iv = random_bytes(12);
        $tag = '';
        $encrypted = openssl_encrypt($value, 'aes-256-gcm', $key, OPENSSL_RAW_DATA, $iv, $tag);

        if ($encrypted === false) {
            throw new RuntimeException('Failed to encrypt value.');
        }

        return base64_encode($iv) . ':' . base64_encode($tag) . ':' . base64_encode($encrypted);
    }

    public function decrypt(string $value): string
    {
        $parts = explode(':', $value, 3);

        if (count($parts) !== 3) {
            throw new RuntimeException('Invalid encrypted payload.');
        }

        [$ivRaw, $tagRaw, $encryptedRaw] = $parts;
        $key = hash('sha256', $this->encryptionKey, true);
        $decrypted = openssl_decrypt(
            base64_decode($encryptedRaw, true),
            'aes-256-gcm',
            $key,
            OPENSSL_RAW_DATA,
            base64_decode($ivRaw, true),
            base64_decode($tagRaw, true),
        );

        if ($decrypted === false) {
            throw new RuntimeException('Failed to decrypt value.');
        }

        return $decrypted;
    }

    public function randomToken(int $bytes = 32): string
    {
        return rtrim(strtr(base64_encode(random_bytes($bytes)), '+/', '-_'), '=');
    }

    public function hashToken(string $token): string
    {
        return hash('sha256', $token);
    }
}
