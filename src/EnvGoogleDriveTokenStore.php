<?php

namespace Pimphand\GDrive;

use DateTimeInterface;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;

/**
 * In-memory token store backed by config/env credentials.
 * Refreshed access tokens are kept in memory for the current request lifecycle.
 */
class EnvGoogleDriveTokenStore implements GoogleDriveTokenStore
{
    private ?GoogleDriveCredentials $credentials = null;

    public function __construct(?GoogleDriveCredentials $credentials = null)
    {
        $this->credentials = $credentials;
    }

    public function getCredentials(): GoogleDriveCredentials
    {
        return $this->credentials ??= GoogleDriveCredentials::fromConfig();
    }

    public function saveAccessToken(string $accessToken, DateTimeInterface $expiresAt): void
    {
        $this->credentials = $this->credentials->withAccessToken($accessToken, $expiresAt);
    }
}
