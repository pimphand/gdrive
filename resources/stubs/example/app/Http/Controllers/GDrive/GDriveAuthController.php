<?php

namespace App\Http\Controllers\GDrive;

use App\Http\Controllers\Controller;
use App\Services\GDrive\FileGoogleDriveTokenStore;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Pimphand\GDrive\GoogleDriveService;
use Pimphand\GDrive\TokenCrypto;

class GDriveAuthController extends Controller
{
    public function connect(GoogleDriveService $drive, TokenCrypto $crypto): RedirectResponse
    {
        $state = $crypto->randomToken();
        session(['gdrive_oauth_state' => $state]);

        return redirect()->away($drive->getAuthUrl($state));
    }

    public function callback(Request $request, GoogleDriveService $drive, FileGoogleDriveTokenStore $tokens): RedirectResponse
    {
        if ($request->query('error')) {
            return redirect()->route('gdrive.index')
                ->with('error', $request->query('error_description', $request->query('error')));
        }

        if ($request->query('state') !== session('gdrive_oauth_state')) {
            return redirect()->route('gdrive.index')->with('error', 'Invalid OAuth state. Please try again.');
        }

        session()->forget('gdrive_oauth_state');

        if (! $request->filled('code')) {
            return redirect()->route('gdrive.index')->with('error', 'Authorization code is missing.');
        }

        try {
            $credentials = $drive->exchangeAuthCode($request->query('code'));
            $tokens->save($credentials);
        } catch (\Throwable $exception) {
            return redirect()->route('gdrive.index')->with('error', $exception->getMessage());
        }

        return redirect()->route('gdrive.index')->with('success', 'Google Drive connected successfully.');
    }

    public function disconnect(FileGoogleDriveTokenStore $tokens): RedirectResponse
    {
        $tokens->clear();

        return redirect()->route('gdrive.index')->with('success', 'Google Drive disconnected.');
    }
}
