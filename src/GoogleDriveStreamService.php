<?php

namespace Pimphand\GDrive;

use GuzzleHttp\Client as GuzzleClient;
use GuzzleHttp\Exception\GuzzleException;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use RuntimeException;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Stream Google Drive files through the backend — mirrors stream-google-file.ts.
 */
class GoogleDriveStreamService
{
    public function __construct(
        private readonly GoogleDriveService $driveService,
    ) {}

    public static function fromConfig(?GoogleDriveTokenStore $tokenStore = null): self
    {
        return new self(GoogleDriveService::fromConfig($tokenStore));
    }

    /**
     * @return array{url: string, mime_type: string, file_name: string, is_export: bool}
     */
    public function resolveStreamTarget(string $providerFileId, string $fileName, string $mimeType, string $disposition = 'attachment'): array
    {
        $exportMap = config("gdrive.export_mime_types.{$disposition}", []);
        $exportTarget = $exportMap[$mimeType] ?? null;

        if ($exportTarget !== null) {
            return [
                'url' => "https://www.googleapis.com/drive/v3/files/{$providerFileId}/export?mimeType=".rawurlencode($exportTarget['mimeType']),
                'mime_type' => $exportTarget['mimeType'],
                'file_name' => $this->withExtension($fileName, $exportTarget['extension']),
                'is_export' => true,
            ];
        }

        return [
            'url' => "https://www.googleapis.com/drive/v3/files/{$providerFileId}?alt=media",
            'mime_type' => $mimeType,
            'file_name' => $fileName,
            'is_export' => false,
        ];
    }

    public function stream(
        string $providerFileId,
        string $fileName,
        string $mimeType,
        ?string $range = null,
        string $disposition = 'attachment',
    ): StreamedResponse {
        $target = $this->resolveStreamTarget($providerFileId, $fileName, $mimeType, $disposition);
        $client = $this->driveService->getAuthedClient();
        $token = $client->getAccessToken();

        if (! is_array($token) || ! isset($token['access_token'])) {
            throw new RuntimeException('Google access token is unavailable for streaming.');
        }

        $headers = [
            'Authorization' => 'Bearer '.$token['access_token'],
        ];

        if ($range !== null && ! $target['is_export']) {
            $headers['Range'] = $range;
        }

        $guzzle = new GuzzleClient(['http_errors' => false]);

        try {
            $upstream = $guzzle->request('GET', $target['url'], [
                'headers' => $headers,
                'stream' => true,
            ]);
        } catch (GuzzleException $exception) {
            throw new RuntimeException('Failed to stream Google Drive file: '.$exception->getMessage(), 0, $exception);
        }

        if ($upstream->getStatusCode() >= 400) {
            throw new RuntimeException('Google file stream failed: '.$upstream->getBody()->getContents(), $upstream->getStatusCode());
        }

        $responseHeaders = [
            'Content-Type' => $target['mime_type'],
            'Accept-Ranges' => 'bytes',
            'Content-Disposition' => $this->contentDisposition($disposition, $target['file_name']),
        ];

        if ($upstream->hasHeader('Content-Length')) {
            $responseHeaders['Content-Length'] = $upstream->getHeaderLine('Content-Length');
        }

        if ($upstream->hasHeader('Content-Range')) {
            $responseHeaders['Content-Range'] = $upstream->getHeaderLine('Content-Range');
        }

        return new StreamedResponse(function () use ($upstream): void {
            $body = $upstream->getBody();

            while (! $body->eof()) {
                echo $body->read(8192);

                if (connection_aborted()) {
                    break;
                }
            }
        }, $upstream->getStatusCode(), $responseHeaders);
    }

    private function contentDisposition(string $type, string $fileName): string
    {
        $safeName = str_replace('"', '', $fileName);

        return "{$type}; filename=\"{$safeName}\"";
    }

    private function withExtension(string $fileName, string $extension): string
    {
        return str_ends_with(strtolower($fileName), strtolower($extension))
            ? $fileName
            : $fileName.$extension;
    }
}
