<?php

namespace App\Http\Controllers\GDrive;

use App\Http\Controllers\Controller;
use App\Services\GDrive\FileGoogleDriveTokenStore;
use App\Services\GDrive\GDriveFileCatalog;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Pimphand\GDrive\GoogleDriveStorage;

class GDriveApiController extends Controller
{
    private function resolveUploadParentId(Request $request): ?string
    {
        foreach ([
            $request->input('parent_id'),
            $request->input('folder_id'),
            $request->header('X-Parent-Folder-Id'),
        ] as $candidate) {
            if (is_string($candidate) && $candidate !== '') {
                return $candidate;
            }
        }

        return null;
    }

    public function initUpload(Request $request, FileGoogleDriveTokenStore $tokens, GDriveFileCatalog $catalog): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'mime_type' => ['required', 'string', 'max:255'],
            'size' => ['required', 'integer', 'min:1'],
            'parent_id' => ['nullable', 'string', 'max:255'],
            'folder_id' => ['nullable', 'string', 'max:255'],
            'relative_path' => ['nullable', 'string', 'max:1024'],
        ]);

        try {
            $parentId = $catalog->resolveUploadParent(
                $this->resolveUploadParentId($request),
                $data['relative_path'] ?? null,
            );

            $session = $catalog->initResumableUpload(
                $data['name'],
                $data['mime_type'],
                (int) $data['size'],
                $parentId,
            );
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json($session);
    }

    public function uploadChunk(Request $request, FileGoogleDriveTokenStore $tokens, GoogleDriveStorage $storage): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $uploadUrl = $request->header('X-Upload-Url');
        $contentRange = $request->header('X-Content-Range');

        if (! is_string($uploadUrl) || $uploadUrl === '' || ! is_string($contentRange) || $contentRange === '') {
            return response()->json(['message' => 'X-Upload-Url and X-Content-Range headers are required.'], 422);
        }

        $chunk = $request->getContent();
        $maxChunk = (int) config('gdrive.upload_chunk_max_bytes', 2 * 1024 * 1024);

        if ($chunk === '') {
            return response()->json(['message' => 'Chunk body is empty.'], 422);
        }

        if (strlen($chunk) > $maxChunk) {
            return response()->json([
                'message' => 'Chunk exceeds server limit. Lower GDRIVE_UPLOAD_CHUNK_SIZE in JS or raise upload_chunk_max_bytes.',
            ], 413);
        }

        try {
            $result = $storage->uploadResumableChunk($uploadUrl, $chunk, $contentRange);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }

        return response()->json($result);
    }

    public function createFolder(Request $request, FileGoogleDriveTokenStore $tokens, GDriveFileCatalog $catalog): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'parent_id' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return response()->json($catalog->createFolder(
                $data['name'],
                $this->resolveUploadParentId($request),
            ));
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function sync(Request $request, FileGoogleDriveTokenStore $tokens, GDriveFileCatalog $catalog, GoogleDriveStorage $storage): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $folderId = $request->input('folder_id');

        try {
            return response()->json([
                'files' => $catalog->list(is_string($folderId) && $folderId !== '' ? $folderId : null),
                'quota' => $storage->quota(),
            ]);
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function show(string $id, FileGoogleDriveTokenStore $tokens, GoogleDriveStorage $storage): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        try {
            return response()->json($storage->drive()->getMetadata($id));
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 404);
        }
    }

    public function rename(Request $request, string $id, FileGoogleDriveTokenStore $tokens, GoogleDriveStorage $storage): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
        ]);

        try {
            return response()->json($storage->drive()->rename($id, $data['name']));
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function copy(Request $request, string $id, FileGoogleDriveTokenStore $tokens, GDriveFileCatalog $catalog): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:255'],
        ]);

        try {
            return response()->json($catalog->copy($id, $data['name'] ?? null));
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }

    public function toggleStar(Request $request, string $id, FileGoogleDriveTokenStore $tokens, GDriveFileCatalog $catalog): JsonResponse
    {
        if (! $tokens->connected()) {
            return response()->json(['message' => 'Google Drive is not connected.'], 401);
        }

        $data = $request->validate([
            'starred' => ['required', 'boolean'],
        ]);

        try {
            return response()->json($catalog->setStarred($id, (bool) $data['starred']));
        } catch (\Throwable $exception) {
            return response()->json(['message' => $exception->getMessage()], 500);
        }
    }
}
