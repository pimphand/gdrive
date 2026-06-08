<?php

namespace Pimphand\GDrive\Contracts;

use DateTimeInterface;
use Pimphand\GDrive\GoogleDriveCredentials;

interface GoogleDriveTokenStore
{
    public function getCredentials(): GoogleDriveCredentials;

    public function saveAccessToken(string $accessToken, DateTimeInterface $expiresAt): void;
}
