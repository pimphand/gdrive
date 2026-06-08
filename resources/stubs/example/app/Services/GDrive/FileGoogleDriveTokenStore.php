<?php

namespace App\Services\GDrive;

use DateTimeImmutable;
use DateTimeInterface;
use Illuminate\Support\Facades\File;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use Pimphand\GDrive\GoogleDriveCredentials;
use RuntimeException;

class FileGoogleDriveTokenStore implements GoogleDriveTokenStore
{
    public function __construct(
        private readonly ?string $path = null,
    ) {}

    public function connected(): bool
    {
        return File::exists($this->credentialsPath());
    }

    public function clear(): void
    {
        $path = $this->credentialsPath();

        if (File::exists($path)) {
            File::delete($path);
        }
    }

    public function save(GoogleDriveCredentials $credentials): void
    {
        $path = $this->credentialsPath();
        File::ensureDirectoryExists(dirname($path));

        $encrypted = $credentials->toEncrypted();

        File::put($path, json_encode([
            'access_token_encrypted' => $encrypted['access_token_encrypted'],
            'refresh_token_encrypted' => $encrypted['refresh_token_encrypted'],
            'token_expires_at' => $credentials->expiresAt->format(DATE_ATOM),
        ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
    }

    public function getCredentials(): GoogleDriveCredentials
    {
        if (! $this->connected()) {
            throw new RuntimeException('Google Drive is not connected. Visit /gdrive to connect your account.');
        }

        $data = json_decode(File::get($this->credentialsPath()), true);

        if (! is_array($data)) {
            throw new RuntimeException('Invalid Google Drive credentials file.');
        }

        return GoogleDriveCredentials::fromEncrypted(
            $data['access_token_encrypted'],
            $data['refresh_token_encrypted'],
            new DateTimeImmutable($data['token_expires_at']),
        );
    }

    public function saveAccessToken(string $accessToken, DateTimeInterface $expiresAt): void
    {
        $this->save($this->getCredentials()->withAccessToken($accessToken, $expiresAt));
    }

    private function credentialsPath(): string
    {
        return $this->path ?? storage_path('app/gdrive/credentials.json');
    }
}
