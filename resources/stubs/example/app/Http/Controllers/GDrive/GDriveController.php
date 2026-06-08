<?php

namespace App\Http\Controllers\GDrive;

use App\Http\Controllers\Controller;
use App\Services\GDrive\FileGoogleDriveTokenStore;
use App\Services\GDrive\GDriveFileCatalog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Pimphand\GDrive\GoogleDriveStorage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class GDriveController extends Controller
{
    public function index(Request $request, FileGoogleDriveTokenStore $tokens, GDriveFileCatalog $catalog, GoogleDriveStorage $storage)
    {
        $connected = $tokens->connected();
        $quota = null;
        $files = [];
        $currentFolderId = $request->query('folder');
        $currentFolderName = null;

        if ($connected) {
            try {
                $quota = $storage->quota();
                $files = $catalog->list($currentFolderId);

                if (is_string($currentFolderId) && $currentFolderId !== '') {
                    $currentFolderName = $storage->drive()->getMetadata($currentFolderId)['name'] ?? null;
                }
            } catch (\Throwable $exception) {
                return view('gdrive.index', [
                    'connected' => $connected,
                    'quota' => null,
                    'files' => [],
                    'currentFolderId' => $currentFolderId,
                    'currentFolderName' => $currentFolderName,
                    'error' => $exception->getMessage(),
                ]);
            }
        }

        return view('gdrive.index', compact('connected', 'quota', 'files', 'currentFolderId', 'currentFolderName'));
    }

    public function download(string $id, GoogleDriveStorage $storage): StreamedResponse|RedirectResponse
    {
        try {
            return $storage->get($id, disposition: 'attachment');
        } catch (\Throwable $exception) {
            return redirect()->route('gdrive.index')->with('error', $exception->getMessage());
        }
    }

    public function preview(string $id, GoogleDriveStorage $storage): StreamedResponse|RedirectResponse
    {
        try {
            return $storage->get($id, disposition: 'inline');
        } catch (\Throwable $exception) {
            return redirect()->route('gdrive.index')->with('error', $exception->getMessage());
        }
    }

    public function destroy(string $id, GoogleDriveStorage $storage): RedirectResponse
    {
        try {
            $storage->delete($id);
        } catch (\Throwable $exception) {
            return back()->with('error', $exception->getMessage());
        }

        return back()->with('success', 'File deleted from Google Drive.');
    }
}
