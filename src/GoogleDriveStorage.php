<?php

namespace Pimphand\GDrive;

use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * S3-style storage API backed by Google Drive.
 */
class GoogleDriveStorage
{
    public function __construct(
        private readonly GoogleDriveService $driveService,
        private readonly GoogleDriveStreamService $streamService,
    ) {}

    public static function fromConfig(?GoogleDriveTokenStore $tokenStore = null): self
    {
        $driveService = GoogleDriveService::fromConfig($tokenStore);

        return new self($driveService, new GoogleDriveStreamService($driveService));
    }

    public function put(string $fileName, mixed $body, string $mimeType, ?string $sizeBytes = null): array
    {
        return $this->driveService->upload($fileName, $mimeType, $body, $sizeBytes);
    }

    /**
     * @return array{upload_url: string, name: string, mime_type: string, size: string}
     */
    public function initResumableUpload(string $fileName, string $mimeType, int $sizeBytes, ?string $parentFolderId = null): array
    {
        return $this->driveService->initResumableUpload($fileName, $mimeType, $sizeBytes, $parentFolderId);
    }

    public function uploadResumableChunk(string $uploadUrl, string $chunk, string $contentRange): array
    {
        return $this->driveService->uploadResumableChunk($uploadUrl, $chunk, $contentRange);
    }

    public function get(string $fileId, ?string $range = null, string $disposition = 'attachment'): StreamedResponse
    {
        $meta = $this->driveService->getMetadata($fileId);

        return $this->streamService->stream(
            $fileId,
            $meta['name'],
            $meta['mime_type'],
            $range,
            $disposition,
        );
    }

    public function delete(string $fileId): void
    {
        $this->driveService->delete($fileId);
    }

    public function exists(string $fileId): bool
    {
        try {
            $this->driveService->getMetadata($fileId);

            return true;
        } catch (\Throwable) {
            return false;
        }
    }

    public function list(): array
    {
        return $this->driveService->listAppFolderFiles();
    }

    public function url(string $fileId): ?string
    {
        $meta = $this->driveService->getMetadata($fileId);

        return $meta['web_view_link'] ?? $meta['web_content_link'] ?? null;
    }

    public function quota(): array
    {
        return $this->driveService->syncQuota();
    }

    public function sync(): array
    {
        return $this->driveService->listAppFolderFiles();
    }

    public function rename(string $fileId, string $newName): array
    {
        return $this->driveService->rename($fileId, $newName);
    }

    public function getMetadata(string $fileId): array
    {
        return $this->driveService->getMetadata($fileId);
    }

    public function copy(string $fileId, ?string $newName = null): array
    {
        return $this->driveService->copy($fileId, $newName);
    }

    public function createFolder(string $name, ?string $parentFolderId = null): array
    {
        return $this->driveService->createFolder($name, $parentFolderId);
    }

    public function drive(): GoogleDriveService
    {
        return $this->driveService;
    }
}
