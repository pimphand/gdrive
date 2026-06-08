<?php

namespace App\Services\GDrive;

use Google\Service\Drive\DriveFile;
use GuzzleHttp\Client as GuzzleClient;
use Pimphand\GDrive\GoogleDriveStorage;
use RuntimeException;

class GDriveFileCatalog
{
    public function __construct(
        private readonly GoogleDriveStorage $storage,
    ) {}

    /**
     * @return list<array<string, mixed>>
     */
    public function list(?string $parentFolderId = null): array
    {
        $driveService = $this->storage->drive();
        $parentId = $parentFolderId ?? $driveService->ensureAppFolder();
        $drive = $driveService->drive();
        $folderMime = config('gdrive.folder_mime_type');
        $files = [];
        $pageToken = null;

        do {
            $response = $drive->files->listFiles([
                'q' => "'{$parentId}' in parents and trashed = false",
                'spaces' => 'drive',
                'fields' => 'nextPageToken,files(id,name,mimeType,size,modifiedTime,modifiedByMeTime,viewedByMeTime,webViewLink,starred)',
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
                    'modified_by_me_at' => $file->getModifiedByMeTime(),
                    'opened_by_me_at' => $file->getViewedByMeTime(),
                    'web_view_link' => $file->getWebViewLink(),
                    'is_folder' => $isFolder,
                    'starred' => (bool) $file->getStarred(),
                ];
            }

            $pageToken = $response->getNextPageToken();
        } while ($pageToken !== null);

        return $files;
    }

    public function resolveUploadParent(?string $parentFolderId, ?string $relativePath = null): string
    {
        $driveService = $this->storage->drive();
        $parentId = (is_string($parentFolderId) && $parentFolderId !== '')
            ? $parentFolderId
            : $driveService->ensureAppFolder();

        if (! is_string($relativePath) || $relativePath === '' || ! str_contains($relativePath, '/')) {
            return $parentId;
        }

        $segments = explode('/', $relativePath);
        array_pop($segments);

        foreach ($segments as $segment) {
            if ($segment === '' || $segment === '.') {
                continue;
            }

            $parentId = $this->findOrCreateFolder($segment, $parentId);
        }

        return $parentId;
    }

    public function findOrCreateFolder(string $name, string $parentId): string
    {
        $driveService = $this->storage->drive();
        $drive = $driveService->drive();
        $folderMime = config('gdrive.folder_mime_type');
        $escapedName = str_replace("'", "\\'", $name);

        $response = $drive->files->listFiles([
            'q' => "name = '{$escapedName}' and '{$parentId}' in parents and mimeType = '{$folderMime}' and trashed = false",
            'spaces' => 'drive',
            'fields' => 'files(id)',
            'pageSize' => 1,
        ]);

        $existing = ($response->getFiles() ?? [])[0] ?? null;
        if ($existing?->getId() !== null) {
            return $existing->getId();
        }

        return $this->createFolder($name, $parentId)['id'];
    }

    /**
     * @return array{upload_url: string, name: string, mime_type: string, size: string, parent_id: string}
     */
    public function initResumableUpload(string $fileName, string $mimeType, int $sizeBytes, ?string $parentFolderId = null): array
    {
        if ($sizeBytes <= 0) {
            throw new RuntimeException('File size must be greater than zero.');
        }

        $driveService = $this->storage->drive();
        $parentId = (is_string($parentFolderId) && $parentFolderId !== '')
            ? $parentFolderId
            : $driveService->ensureAppFolder();
        $client = $driveService->getAuthedClient();
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
                'Authorization' => 'Bearer '.$token['access_token'],
                'Content-Type' => 'application/json',
                'X-Upload-Content-Type' => $mimeType,
                'X-Upload-Content-Length' => (string) $sizeBytes,
            ],
            'json' => [
                'name' => $fileName,
                'parents' => [$parentId],
            ],
        ]);

        if ($response->getStatusCode() >= 400) {
            throw new RuntimeException('Failed to init resumable upload: '.$response->getBody()->getContents());
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
            'parent_id' => $parentId,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function createFolder(string $name, ?string $parentFolderId = null): array
    {
        $driveService = $this->storage->drive();
        $parentId = $parentFolderId ?? $driveService->ensureAppFolder();
        $folderMime = config('gdrive.folder_mime_type');

        $created = $driveService->drive()->files->create(new DriveFile([
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
            'starred' => false,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function copy(string $fileId, ?string $newName = null): array
    {
        $driveService = $this->storage->drive();
        $appFolderId = $driveService->ensureAppFolder();
        $meta = $driveService->getMetadata($fileId);
        $name = $newName ?? ('Salinan dari '.$meta['name']);
        $folderMime = config('gdrive.folder_mime_type');

        $copied = $driveService->drive()->files->copy($fileId, new DriveFile([
            'name' => $name,
            'parents' => [$appFolderId],
        ]), ['fields' => 'id,name,mimeType,size,modifiedTime,starred']);

        return [
            'id' => $copied->getId() ?? '',
            'name' => $copied->getName() ?? $name,
            'mime_type' => $copied->getMimeType() ?? 'application/octet-stream',
            'size' => (string) ($copied->getSize() ?? '0'),
            'modified_at' => $copied->getModifiedTime(),
            'is_folder' => $copied->getMimeType() === $folderMime,
            'starred' => (bool) $copied->getStarred(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function setStarred(string $fileId, bool $starred): array
    {
        $drive = $this->storage->drive()->drive();
        $folderMime = config('gdrive.folder_mime_type');

        $updated = $drive->files->update($fileId, new DriveFile([
            'starred' => $starred,
        ]), [
            'fields' => 'id,name,mimeType,size,modifiedTime,webViewLink,starred',
        ]);

        return [
            'id' => $updated->getId() ?? $fileId,
            'name' => $updated->getName() ?? '',
            'mime_type' => $updated->getMimeType() ?? 'application/octet-stream',
            'size' => (string) ($updated->getSize() ?? '0'),
            'modified_at' => $updated->getModifiedTime(),
            'web_view_link' => $updated->getWebViewLink(),
            'is_folder' => $updated->getMimeType() === $folderMime,
            'starred' => (bool) $updated->getStarred(),
        ];
    }
}
