<?php

namespace Pimphand\GDrive;

use DateTimeImmutable;
use Google\Client as GoogleClient;
use Google\Service\Drive;
use Google\Service\Drive\DriveFile;
use GuzzleHttp\Client as GuzzleClient;
use Illuminate\Support\Carbon;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use Psr\Http\Message\StreamInterface;
use RuntimeException;

/**
 * Google Drive storage service — mirrors pimpdrive backend google.service.ts.
 *
 * Physical files are stored in a root-level app folder on Drive (default: pimpdrive).
 * Virtual folders remain application/database metadata only.
 */
class GoogleDriveService
{
    public function __construct(
        private readonly GoogleDriveTokenStore $tokenStore,
        private readonly ?string $clientId = null,
        private readonly ?string $clientSecret = null,
        private readonly ?string $redirectUri = null,
        private readonly string $appFolderName = 'pimpdrive',
    ) {}

    public static function fromConfig(?GoogleDriveTokenStore $tokenStore = null): self
    {
        return new self(
            tokenStore: $tokenStore ?? new EnvGoogleDriveTokenStore,
            clientId: config('gdrive.client_id'),
            clientSecret: config('gdrive.client_secret'),
            redirectUri: config('gdrive.redirect_uri'),
            appFolderName: config('gdrive.app_folder_name', 'pimpdrive'),
        );
    }

    public static function oauthConfigured(): bool
    {
        $clientId = config('gdrive.client_id');
        $clientSecret = config('gdrive.client_secret');

        return is_string($clientId) && $clientId !== ''
            && is_string($clientSecret) && $clientSecret !== '';
    }

    public function createOAuthClient(): GoogleClient
    {
        $clientId = $this->clientId ?? config('gdrive.client_id');
        $clientSecret = $this->clientSecret ?? config('gdrive.client_secret');
        $redirectUri = $this->redirectUri ?? config('gdrive.redirect_uri');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            throw new RuntimeException('Google OAuth client credentials are not configured.');
        }

        $client = new GoogleClient;
        $client->setClientId($clientId);
        $client->setClientSecret($clientSecret);
        $client->setRedirectUri($redirectUri);
        $client->setScopes(config('gdrive.scopes', []));
        $client->setAccessType('offline');
        $client->setPrompt('consent');

        return $client;
    }

    public function getAuthUrl(string $state): string
    {
        $client = $this->createOAuthClient();
        $client->setState($state);

        return $client->createAuthUrl();
    }

    public function exchangeAuthCode(string $code): GoogleDriveCredentials
    {
        $client = $this->createOAuthClient();
        $token = $client->fetchAccessTokenWithAuthCode($code);

        if (isset($token['error'])) {
            throw new RuntimeException($token['error_description'] ?? $token['error']);
        }

        if (! isset($token['access_token'], $token['refresh_token'])) {
            throw new RuntimeException('Google OAuth response is missing access or refresh token.');
        }

        $expiresAt = new DateTimeImmutable('@' . ($token['created'] + ($token['expires_in'] ?? 3600)));

        return new GoogleDriveCredentials(
            $token['access_token'],
            $token['refresh_token'],
            $expiresAt,
        );
    }

    public function getAuthedClient(): GoogleClient
    {
        $credentials = $this->tokenStore->getCredentials();
        $client = $this->createOAuthClient();
        $client->setAccessToken([
            'access_token' => $credentials->accessToken,
            'refresh_token' => $credentials->refreshToken,
            'expires_in' => max(0, $credentials->expiresAt->getTimestamp() - time()),
            'created' => $credentials->expiresAt->getTimestamp() - 3600,
        ]);

        $buffer = (int) config('gdrive.token_refresh_buffer_seconds', 60);

        if ($credentials->isExpiringSoon($buffer)) {
            $token = $client->fetchAccessTokenWithRefreshToken($credentials->refreshToken);

            if (isset($token['error'])) {
                throw new RuntimeException($token['error_description'] ?? $token['error']);
            }

            if (! isset($token['access_token'])) {
                throw new RuntimeException('Google token refresh did not return an access token.');
            }

            $expiresAt = new DateTimeImmutable('@' . ($token['created'] + ($token['expires_in'] ?? 3600)));
            $this->tokenStore->saveAccessToken($token['access_token'], $expiresAt);
            $client->setAccessToken($token);
        }

        return $client;
    }

    public function drive(): Drive
    {
        return new Drive($this->getAuthedClient());
    }

    public function syncQuota(): array
    {
        $about = $this->drive()->about->get(['fields' => 'storageQuota,user']);
        $quota = $about->getStorageQuota();

        $total = $quota?->getLimit();
        $used = $quota?->getUsage() ?? '0';
        $trash = $quota?->getUsageInDriveTrash();

        return [
            'total_bytes' => $total !== null ? (string) $total : null,
            'used_bytes' => (string) $used,
            'available_bytes' => $total !== null ? (string) ((int) $total - (int) $used) : null,
            'trash_bytes' => $trash !== null ? (string) $trash : null,
            'last_synced_at' => Carbon::now()->toIso8601String(),
        ];
    }

    public function ensureAppFolder(): string
    {
        $drive = $this->drive();
        $folderMime = config('gdrive.folder_mime_type');
        $queryName = $this->escapeDriveQueryValue($this->appFolderName);

        $existing = $drive->files->listFiles([
            'q' => "name = '{$queryName}' and mimeType = '{$folderMime}' and 'root' in parents and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id,name)',
            'pageSize' => 1,
        ]);

        $files = $existing->getFiles();

        if ($files !== null && count($files) > 0 && $files[0]->getId() !== null) {
            return $files[0]->getId();
        }

        $folder = new DriveFile([
            'name' => $this->appFolderName,
            'mimeType' => $folderMime,
            'parents' => ['root'],
        ]);

        $created = $drive->files->create($folder, ['fields' => 'id']);
        $folderId = $created->getId();

        if ($folderId === null) {
            throw new RuntimeException('Failed to create Google Drive app folder.');
        }

        return $folderId;
    }

    /**
     * Start a resumable upload session. The browser uploads file bytes directly to Google
     * using the returned upload_url (bypasses PHP upload limits).
     *
     * @return array{upload_url: string, name: string, mime_type: string, size: string}
     */
    public function initResumableUpload(string $fileName, string $mimeType, int $sizeBytes, ?string $parentFolderId = null): array
    {
        if ($sizeBytes <= 0) {
            throw new RuntimeException('File size must be greater than zero.');
        }

        $appFolderId = $parentFolderId ?? $this->ensureAppFolder();
        $client = $this->getAuthedClient();
        $token = $client->getAccessToken();

        if (! is_array($token) || ! isset($token['access_token'])) {
            throw new RuntimeException('Google access token is unavailable.');
        }

        $http = new GuzzleClient(['http_errors' => false]);
        $response = $http->request('POST', 'https://www.googleapis.com/upload/drive/v3/files', [
            'query' => [
                'uploadType' => 'resumable',
                'fields' => 'id,name,mimeType,size',
            ],
            'headers' => [
                'Authorization' => 'Bearer ' . $token['access_token'],
                'Content-Type' => 'application/json',
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => (string) $sizeBytes,
            ],
            'json' => [
                'name' => $fileName,
                'parents' => [$appFolderId],
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Failed to init resumable upload: ' . $response->getBody()->getContents());
        }

        $uploadUrl = $response->getHeaderLine('Location');

        if ($uploadUrl === '') {
            throw new RuntimeException('Google Drive did not return a resumable upload URL.');
        }

        return [
            'upload_url' => $uploadUrl,
            'name' => $fileName,
            'mime_type' => $mimeType,
            'size' => (string) $sizeBytes,
        ];
    }

    /**
     * Upload one chunk to a Google resumable session URL (server-side proxy avoids browser CORS).
     *
     * @return array{status: int, complete: bool, resume: bool, file: array<string, mixed>|null}
     */
    public function uploadResumableChunk(string $uploadUrl, string $chunk, string $contentRange): array
    {
        $http = new GuzzleClient(['http_errors' => false]);
        $response = $http->request('PUT', $uploadUrl, [
            'headers' => [
                'Content-Length' => (string) strlen($chunk),
                'Content-Range' => $contentRange,
            ],
            'body' => $chunk,
        ]);

        $status = $response->getStatusCode();
        $body = $response->getBody()->getContents();
        $file = null;

        if (in_array($status, [200, 201], true)) {
            $decoded = json_decode($body, true);
            $file = is_array($decoded) ? $decoded : null;
        }

        if ($status >= 400 && $status !== 308) {
            throw new RuntimeException('Google chunk upload failed: ' . $body, $status);
        }

        return [
            'status' => $status,
            'complete' => in_array($status, [200, 201], true),
            'resume' => $status === 308,
            'file' => $file,
        ];
    }

    /**
     * @param  resource|StreamInterface|string  $body
     * @return array{id: string, name: string, mime_type: string, size: string}
     */
    public function upload(string $fileName, string $mimeType, mixed $body, ?string $declaredSizeBytes = null): array
    {
        $appFolderId = $this->ensureAppFolder();
        $driveFile = new DriveFile([
            'name' => $fileName,
            'parents' => [$appFolderId],
        ]);

        $uploaded = $this->drive()->files->create($driveFile, [
            'data' => $this->normalizeBody($body),
            'mimeType' => $mimeType,
            'uploadType' => 'multipart',
            'fields' => 'id,name,mimeType,size',
        ]);

        if ($uploaded->getId() === null) {
            throw new RuntimeException('Google Drive upload did not return a file id.');
        }

        if ($declaredSizeBytes !== null && $uploaded->getSize() !== null && (string) $uploaded->getSize() !== $declaredSizeBytes) {
            $this->delete($uploaded->getId());

            throw new RuntimeException('Uploaded byte count did not match declared size.');
        }

        return [
            'id' => $uploaded->getId(),
            'name' => $uploaded->getName() ?? $fileName,
            'mime_type' => $uploaded->getMimeType() ?? $mimeType,
            'size' => (string) ($uploaded->getSize() ?? $declaredSizeBytes ?? '0'),
        ];
    }

    public function rename(string $fileId, string $newName): array
    {
        $updated = $this->drive()->files->update($fileId, new DriveFile([
            'name' => $newName,
        ]), ['fields' => 'id,name,mimeType,size,modifiedTime']);

        return [
            'id' => $updated->getId() ?? $fileId,
            'name' => $updated->getName() ?? $newName,
            'mime_type' => $updated->getMimeType() ?? 'application/octet-stream',
            'size' => (string) ($updated->getSize() ?? '0'),
            'modified_at' => $updated->getModifiedTime(),
        ];
    }

    public function createFolder(string $name, ?string $parentFolderId = null): array
    {
        $parentId = $parentFolderId ?? $this->ensureAppFolder();
        $folderMime = config('gdrive.folder_mime_type');

        $created = $this->drive()->files->create(new DriveFile([
            'name' => $name,
            'mimeType' => $folderMime,
            'parents' => [$parentId],
        ]), ['fields' => 'id,name,mimeType,modifiedTime']);

        if ($created->getId() === null) {
            throw new RuntimeException('Failed to create folder on Google Drive.');
        }

        return [
            'id' => $created->getId(),
            'name' => $created->getName() ?? $name,
            'mime_type' => $folderMime,
            'size' => '0',
            'modified_at' => $created->getModifiedTime(),
            'is_folder' => true,
        ];
    }

    public function copy(string $fileId, ?string $newName = null): array
    {
        $appFolderId = $this->ensureAppFolder();
        $meta = $this->getMetadata($fileId);
        $name = $newName ?? ('Salinan dari ' . $meta['name']);

        $copied = $this->drive()->files->copy($fileId, new DriveFile([
            'name' => $name,
            'parents' => [$appFolderId],
        ]), ['fields' => 'id,name,mimeType,size,modifiedTime']);

        return [
            'id' => $copied->getId() ?? '',
            'name' => $copied->getName() ?? $name,
            'mime_type' => $copied->getMimeType() ?? 'application/octet-stream',
            'size' => (string) ($copied->getSize() ?? '0'),
            'modified_at' => $copied->getModifiedTime(),
        ];
    }

    public function delete(string $fileId): void
    {
        $this->drive()->files->delete($fileId);
    }

    public function getMetadata(string $fileId): array
    {
        $file = $this->drive()->files->get($fileId, [
            'fields' => 'id,name,mimeType,size,modifiedTime,webViewLink,webContentLink',
        ]);

        return [
            'id' => $file->getId() ?? $fileId,
            'name' => $file->getName() ?? '',
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size' => (string) ($file->getSize() ?? '0'),
            'modified_at' => $file->getModifiedTime(),
            'web_view_link' => $file->getWebViewLink(),
            'web_content_link' => $file->getWebContentLink(),
        ];
    }

    /**
     * @return list<array{id: string, name: string, mime_type: string, size: string}>
     */
    public function listAppFolderFiles(): array
    {
        $appFolderId = $this->ensureAppFolder();
        $drive = $this->drive();
        $folderMime = config('gdrive.folder_mime_type');
        $files = [];
        $pageToken = null;

        do {
            $response = $drive->files->listFiles([
                'q' => "'{$appFolderId}' in parents and trashed = false",
                'spaces' => 'drive',
                'fields' => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,webViewLink)',
                'pageSize' => 1000,
                'pageToken' => $pageToken,
            ]);

            foreach ($response->getFiles() ?? [] as $file) {
                if ($file->getId() === null || $file->getName() === null || $file->getMimeType() === null) {
                    continue;
                }

                $isFolder = $file->getMimeType() === $folderMime;

                $files[] = [
                    'id' => $file->getId(),
                    'name' => $file->getName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => (string) ($file->getSize() ?? '0'),
                    'modified_at' => $file->getModifiedTime(),
                    'web_view_link' => $file->getWebViewLink(),
                    'is_folder' => $isFolder,
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $files;
    }

    /**
     * @param  array<string, array{id: string, name: string, mime_type: string, size: string, status: string}>  $existingByProviderId
     * @return array{created: int, updated: int, deleted: int, files: list<array<string, mixed>>}
     */
    public function syncAppFolderFiles(array $existingByProviderId): array
    {
        $driveFiles = $this->listAppFolderFiles();
        $driveFileIds = [];
        $created = 0;
        $updated = 0;
        $deleted = 0;
        $resultFiles = [];

        foreach ($driveFiles as $driveFile) {
            $driveFileIds[$driveFile['id']] = true;
            $existing = $existingByProviderId[$driveFile['id']] ?? null;

            if ($existing === null) {
                $created++;
                $resultFiles[] = ['action' => 'create', ...$driveFile];

                continue;
            }

            $needsUpdate = $existing['name'] !== $driveFile['name']
                || $existing['mime_type'] !== $driveFile['mime_type']
                || $existing['size'] !== $driveFile['size']
                || ($existing['status'] ?? 'active') !== 'active';

            if ($needsUpdate) {
                $updated++;
                $resultFiles[] = ['action' => 'update', ...$driveFile];
            }
        }

        foreach ($existingByProviderId as $providerId => $existing) {
            if (($existing['status'] ?? 'active') === 'active' && ! isset($driveFileIds[$providerId])) {
                $deleted++;
                $resultFiles[] = ['action' => 'delete', 'id' => $providerId, 'name' => $existing['name']];
            }
        }

        return [
            'created' => $created,
            'updated' => $updated,
            'deleted' => $deleted,
            'files' => $resultFiles,
        ];
    }

    private function escapeDriveQueryValue(string $value): string
    {
        return str_replace(["\\", "'"], ["\\\\", "\\'"], $value);
    }

    private function normalizeBody(mixed $body): string
    {
        if (is_resource($body)) {
            return stream_get_contents($body) ?: '';
        }

        if ($body instanceof StreamInterface) {
            return $body->getContents();
        }

        if (is_string($body)) {
            return $body;
        }

        throw new RuntimeException('Upload body must be a string, stream resource, or StreamInterface.');
    }
}
