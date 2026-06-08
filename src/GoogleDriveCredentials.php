<?php

namespace Pimphand\GDrive;

use DateTimeImmutable;
use DateTimeInterface;
use RuntimeException;

class GoogleDriveCredentials
{
    public function __construct(
        public readonly string $accessToken,
        public readonly string $refreshToken,
        public readonly DateTimeInterface $expiresAt,
    ) {}

    public function isExpiringSoon(int $bufferSeconds = 60): bool
    {
        return $this->expiresAt->getTimestamp() < (time() + $bufferSeconds);
    }

    public function withAccessToken(string $accessToken, DateTimeInterface $expiresAt): self
    {
        return new self($accessToken, $this->refreshToken, $expiresAt);
    }

    public static function fromEncrypted(
        string $accessTokenEncrypted,
        string $refreshTokenEncrypted,
        DateTimeInterface $expiresAt,
        ?TokenCrypto $crypto = null,
    ): self {
        $crypto ??= TokenCrypto::fromConfig();

        return new self(
            $crypto->decrypt($accessTokenEncrypted),
            $crypto->decrypt($refreshTokenEncrypted),
            $expiresAt,
        );
    }

    public function toEncrypted(?TokenCrypto $crypto = null): array
    {
        $crypto ??= TokenCrypto::fromConfig();

        return [
            'access_token_encrypted' => $crypto->encrypt($this->accessToken),
            'refresh_token_encrypted' => $crypto->encrypt($this->refreshToken),
            'token_expires_at' => $this->expiresAt,
        ];
    }

    public static function fromConfig(): self
    {
        $accessToken = config('gdrive.access_token');
        $refreshToken = config('gdrive.refresh_token');
        $expiresAt = config('gdrive.token_expires_at');

        if (! is_string($accessToken) || $accessToken === '') {
            throw new RuntimeException('GDRIVE_ACCESS_TOKEN (or GOOGLE_ACCESS_TOKEN) is required.');
        }

        if (! is_string($refreshToken) || $refreshToken === '') {
            throw new RuntimeException('GDRIVE_REFRESH_TOKEN (or GOOGLE_REFRESH_TOKEN) is required.');
        }

        $expires = is_string($expiresAt) && $expiresAt !== ''
            ? new DateTimeImmutable($expiresAt)
            : new DateTimeImmutable('+1 hour');

        if (str_contains($accessToken, ':') && str_contains($refreshToken, ':')) {
            $crypto = TokenCrypto::fromConfig();

            return new self(
                $crypto->decrypt($accessToken),
                $crypto->decrypt($refreshToken),
                $expires,
            );
        }

        return new self($accessToken, $refreshToken, $expires);
    }
}
