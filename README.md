# pimphand/gdrive

Laravel package for using **Google Drive as cloud storage** with an S3-like API. Supports OAuth2, streaming uploads directly to Drive (without saving to local disk), download/preview with byte-range, and quota sync.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pimphand/gdrive.svg)](https://packagist.org/packages/pimphand/gdrive)
[![Total Downloads](https://img.shields.io/packagist/dt/pimphand/gdrive.svg)](https://packagist.org/packages/pimphand/gdrive)
[![License](https://img.shields.io/packagist/l/pimphand/gdrive.svg)](https://packagist.org/packages/pimphand/gdrive)

**📖 Documentation:** [English / Indonesia (HTML)](docs/index.html) — PHP & JavaScript integration guide for new developers.

---

## Features

- **S3-style API** — `put`, `get`, `delete`, `list`, `exists`, `url`, `quota`
- **OAuth2 Google Drive** — auth URL, exchange code, auto token refresh
- **Streaming upload** — files go directly to Google Drive without writing to server disk
- **Streaming download** — proxy files from Drive with `Range` header support
- **Google Workspace export** — Docs/Sheets/Slides are automatically exported on download/preview
- **App folder `pimpdrive`** — all physical files are stored in a root Drive folder (configurable)
- **Laravel auto-discovery** — service provider & facade registered automatically
- **Artisan commands** — `gdrive:seed-config`, `gdrive:sync`

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13
- Google Cloud project with **Google Drive API** enabled
- Google OAuth Client ID & Secret

---

## Installation

### Via Packagist (production)

```bash
composer require pimphand/gdrive:^1.0
```

### Via path repository (local development)

Add to your Laravel project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "../gdrive",
            "options": {
                "symlink": true
            }
        }
    ],
    "require": {
        "pimphand/gdrive": "@dev"
    }
}
```

Then run:

```bash
composer require pimphand/gdrive:@dev
```

### Publish config (optional)

```bash
php artisan vendor:publish --tag=gdrive-config
```

The config file will be available at `config/gdrive.php`. Without publishing, the package's default config is merged automatically.

### Publish full example app (recommended for first-time setup)

This single command creates **controllers, routes, views, and a token store** — immediately accessible at `/gdrive`:

```bash
php artisan vendor:publish --tag=gdrive-example
```

**Updating stubs after a package upgrade** — `vendor:publish` does not overwrite existing files. Use one of:

```bash
php artisan vendor:publish --tag=gdrive-example --force
# or (always overwrites with the latest version from the package)
php artisan gdrive:publish-example
```

Files created automatically:

| File | Purpose |
|------|---------|
| `routes/gdrive.php` | `/gdrive` routes (auto-loaded by the package) |
| `app/Http/Controllers/GDrive/GDriveController.php` | Dashboard, upload, download, preview, delete |
| `app/Http/Controllers/GDrive/GDriveAuthController.php` | OAuth connect / callback / disconnect |
| `app/Http/Controllers/GDrive/GDriveApiController.php` | Chunk upload API + sync |
| `app/Services/GDrive/FileGoogleDriveTokenStore.php` | Store encrypted tokens in `storage/app/gdrive/` |
| `resources/views/gdrive/*.blade.php` | Demo pages |
| `public/js/gdrive-demo.js` | Direct upload to Drive + sync |
| `public/css/gdrive-drive.css` | Demo styles |
| `config/gdrive.php` | Package configuration |
| `gdrive.env.example` | `.env` variable template |

**No need to edit `routes/web.php`** — the package automatically loads `routes/gdrive.php` if that file exists.

#### Quick setup (.env only)

1. Publish the example (see above)
2. Copy variables from `gdrive.env.example` to `.env`:

```env
GDRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GDRIVE_CLIENT_SECRET=your-client-secret
GDRIVE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

3. In Google Cloud Console, add the redirect URI: `http://localhost:8000/auth/google/callback`
4. Start the server:

```bash
php artisan serve
```

5. Open **http://localhost:8000/gdrive** → click **Connect Google Drive**

#### Example routes

| Method | URL | Purpose |
|--------|-----|---------|
| GET | `/gdrive` | Dashboard (quota, file list, upload) |
| GET | `/gdrive/connect` | Start Google OAuth |
| GET | `/gdrive/callback` | OAuth callback |
| POST | `/gdrive/disconnect` | Disconnect Drive |
| POST | `/gdrive/upload` | Upload file |
| GET | `/gdrive/files/{id}/download` | Download file |
| GET | `/gdrive/files/{id}/preview` | Inline preview |
| DELETE | `/gdrive/files/{id}` | Delete file |

---

## Google Cloud Configuration

1. Open [Google Cloud Console](https://console.cloud.google.com/)
2. Create a project (or use an existing one)
3. Enable **Google Drive API**
4. Create an **OAuth 2.0 Client ID** (type: Web application)
5. Add **Authorized redirect URIs**, for example:
   - `http://localhost:8000/auth/google/callback`
   - `https://yourdomain.com/auth/google/callback`

---

## Environment Variables

Add to your Laravel project's `.env`:

```env
# OAuth client (required)
GDRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GDRIVE_CLIENT_SECRET=your-client-secret
GDRIVE_REDIRECT_URI=http://localhost:8000/auth/google/callback

# Root folder name in Google Drive (default: pimpdrive)
GDRIVE_APP_FOLDER=pimpdrive

# Token encryption key (use a long random string)
GDRIVE_TOKEN_ENCRYPTION_KEY=your-random-secret-key

# OAuth tokens for the connected account (after OAuth flow)
GDRIVE_ACCESS_TOKEN=ya29....
GDRIVE_REFRESH_TOKEN=1//0g....
GDRIVE_TOKEN_EXPIRES_AT=2026-06-07T12:00:00+00:00
```
---

## Config validation

```bash
php artisan gdrive:seed-config

# Show encrypted values (for database seeding)
php artisan gdrive:seed-config --show-encrypted
```

---

## Basic Usage

### Facade (simplest)

```php
use Pimphand\GDrive\Facades\GDrive;

// Upload file
$file = GDrive::put(
    fileName: 'report.pdf',
    body: $request->getContent(),          // string, resource, or StreamInterface
    mimeType: 'application/pdf',
    sizeBytes: (string) $request->header('Content-Length'), // optional
);

// Response: ['id' => '...', 'name' => '...', 'mime_type' => '...', 'size' => '...']

// Download (returns StreamedResponse)
return GDrive::get($file['id']);

// Inline preview (video/PDF in browser)
return GDrive::get($file['id'], disposition: 'inline');

// Byte-range (video seek)
return GDrive::get($file['id'], range: $request->header('Range'));

// Delete file
GDrive::delete($file['id']);

// List all files in the app folder
$files = GDrive::list();

// Check if file exists
GDrive::exists($file['id']);

// Google Drive web view URL
$viewUrl = GDrive::url($file['id']);

// Sync quota
$quota = GDrive::quota();
```

### Dependency Injection

```php
use Pimphand\GDrive\GoogleDriveStorage;

class FileController extends Controller
{
    public function __construct(
        private readonly GoogleDriveStorage $storage,
    ) {}

    public function upload(Request $request)
    {
        $file = $this->storage->put(
            $request->file('file')->getClientOriginalName(),
            fopen($request->file('file')->getRealPath(), 'r'),
            $request->file('file')->getMimeType(),
            (string) $request->file('file')->getSize(),
        );

        return response()->json($file);
    }

    public function download(string $fileId)
    {
        return $this->storage->get($fileId);
    }
}
```

### Static factory

```php
use Pimphand\GDrive\GoogleDriveStorage;

$storage = GoogleDriveStorage::fromConfig();
$file = $storage->put('photo.jpg', file_get_contents('/path/to/photo.jpg'), 'image/jpeg');
```

---

## Upload Controller (full example)

```php
<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Pimphand\GDrive\Facades\GDrive;

class UploadController extends Controller
{
    public function store(Request $request)
    {
        $request->validate([
            'file' => ['required', 'file', 'max:512000'], // max 500MB
        ]);

        $uploaded = $request->file('file');

        $file = GDrive::put(
            fileName: $uploaded->getClientOriginalName(),
            body: fopen($uploaded->getRealPath(), 'r'),
            mimeType: $uploaded->getMimeType() ?? 'application/octet-stream',
            sizeBytes: (string) $uploaded->getSize(),
        );

        // Save $file['id'] to your application database
        return response()->json([
            'provider_file_id' => $file['id'],
            'name' => $file['name'],
            'mime_type' => $file['mime_type'],
            'size' => $file['size'],
        ], 201);
    }
}
```

---

## OAuth Flow (example)

The package provides OAuth helpers in `GoogleDriveService`. Simple route example:

```php
// routes/web.php
use Illuminate\Support\Facades\Route;
use Pimphand\GDrive\Facades\GDrive;
use Pimphand\GDrive\GoogleDriveService;
use Pimphand\GDrive\TokenCrypto;

Route::get('/auth/google', function (GoogleDriveService $drive) {
    $state = TokenCrypto::fromConfig()->randomToken();
    session(['google_oauth_state' => $state]);

    return redirect($drive->getAuthUrl($state));
});

Route::get('/auth/google/callback', function (GoogleDriveService $drive, Request $request) {
    if ($request->query('state') !== session('google_oauth_state')) {
        abort(403, 'Invalid OAuth state.');
    }

    $credentials = $drive->exchangeAuthCode($request->query('code'));

    // Save $credentials to database (use toEncrypted() for encryption)
    $encrypted = $credentials->toEncrypted();

    // ... persist to your ConnectedAccount model

    return redirect('/dashboard')->with('success', 'Google Drive connected.');
});
```

---

## Custom Token Store (multi-account)

For applications with multiple Google Drive accounts, implement `GoogleDriveTokenStore`:

```php
<?php

namespace App\Services;

use App\Models\ConnectedAccount;
use DateTimeInterface;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use Pimphand\GDrive\GoogleDriveCredentials;

class DatabaseGoogleDriveTokenStore implements GoogleDriveTokenStore
{
    public function __construct(
        private readonly ConnectedAccount $account,
    ) {}

    public function getCredentials(): GoogleDriveCredentials
    {
        return GoogleDriveCredentials::fromEncrypted(
            $this->account->access_token_encrypted,
            $this->account->refresh_token_encrypted,
            $this->account->token_expires_at,
        );
    }

    public function saveAccessToken(string $accessToken, DateTimeInterface $expiresAt): void
    {
        $crypto = app(\Pimphand\GDrive\TokenCrypto::class);

        $this->account->update([
            'access_token_encrypted' => $crypto->encrypt($accessToken),
            'token_expires_at' => $expiresAt,
        ]);
    }
}
```

Use with the service directly:

```php
use Pimphand\GDrive\GoogleDriveStorage;
use App\Services\DatabaseGoogleDriveTokenStore;

$account = ConnectedAccount::findOrFail($id);
$storage = GoogleDriveStorage::fromConfig(new DatabaseGoogleDriveTokenStore($account));

$file = $storage->put('backup.zip', fopen('php://input', 'r'), 'application/zip');
```

Or bind globally in `AppServiceProvider`:

```php
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use App\Services\DatabaseGoogleDriveTokenStore;

// config/gdrive.php
'token_store' => DatabaseGoogleDriveTokenStore::class,
```

---

## Sync Files from Drive

The package provides reconcile logic:

```php
use Pimphand\GDrive\Facades\GDrive;

// Fetch local records from database, keyed by provider_file_id
$existing = File::where('user_id', $userId)
    ->get()
    ->keyBy('provider_file_id')
    ->map(fn ($f) => [
        'id' => $f->provider_file_id,
        'name' => $f->name,
        'mime_type' => $f->mime_type,
        'size' => (string) $f->size_bytes,
        'status' => $f->status,
    ])
    ->all();

$result = GDrive::drive()->syncAppFolderFiles($existing);

// $result = ['created' => 2, 'updated' => 1, 'deleted' => 0, 'files' => [...]]
foreach ($result['files'] as $change) {
    match ($change['action']) {
        'create' => File::create([...]),
        'update' => File::where('provider_file_id', $change['id'])->update([...]),
        'delete' => File::where('provider_file_id', $change['id'])->update(['status' => 'deleted']),
    };
}
```

### Artisan sync

```bash
php artisan gdrive:sync
```

Displays quota and the file list in the Drive app folder.

---

## API Reference

### `GoogleDriveStorage` (S3-style)

| Method | Description | Return |
|---|---|---|
| `put($name, $body, $mime, $size?)` | Upload to app folder | `array{id, name, mime_type, size}` |
| `get($id, $range?, $disposition?)` | Stream download/preview | `StreamedResponse` |
| `delete($id)` | Delete file on Drive | `void` |
| `exists($id)` | Check if file exists | `bool` |
| `list()` | List files in app folder | `array[]` |
| `url($id)` | Google web view URL | `?string` |
| `quota()` | Sync & return quota | `array` |
| `drive()` | Access low-level service | `GoogleDriveService` |

### `GoogleDriveService` (low-level)

| Method | Description |
|---|---|
| `getAuthUrl($state)` | Generate OAuth authorization URL |
| `exchangeAuthCode($code)` | Exchange auth code → credentials |
| `ensureAppFolder()` | Get/create root app folder |
| `upload(...)` | Upload file |
| `rename($id, $name)` | Rename on Drive |
| `delete($id)` | Delete on Drive |
| `getMetadata($id)` | Fetch file metadata |
| `listAppFolderFiles()` | List all files in app folder |
| `syncAppFolderFiles($existing)` | Reconcile with local records |
| `syncQuota()` | Fetch storage quota from Drive API |

### `TokenCrypto`

| Method | Description |
|---|---|
| `encrypt($value)` | AES-256-GCM encrypt |
| `decrypt($value)` | AES-256-GCM decrypt |
| `randomToken($bytes?)` | Generate random URL-safe token |
| `hashToken($token)` | SHA-256 hash |

---

## Architecture

```
Laravel App
    │
    ├── GDrive Facade / GoogleDriveStorage
    │       ├── put()  ──► GoogleDriveService::upload()
    │       ├── get()  ──► GoogleDriveStreamService::stream()
    │       └── quota() ──► GoogleDriveService::syncQuota()
    │
    ├── GoogleDriveTokenStore (interface)
    │       └── EnvGoogleDriveTokenStore (default, from .env)
    │
    └── Google Drive API
            └── /root/pimpdrive/   ← all physical files are stored here
```

**Important:** Virtual folders (file organization in your app) are database metadata only — they are not mirrored in the Google Drive folder structure. All physical files live in a single root `pimpdrive` folder per Google account.

## Publishing to Packagist

1. Push the package to the GitHub repository `pimphand/gdrive`
2. Create a release / semver tag (e.g. `v1.0.0`)
3. Submit to [Packagist.org](https://packagist.org/packages/submit)
4. Install in other projects:

```bash
composer require pimphand/gdrive:^1.0
```

---

## Security

- Do not commit `.env` or tokens to the repository
- Store refresh tokens encrypted in the database (use `TokenCrypto`)
- Use the OAuth `state` parameter for CSRF protection
- Do not log access tokens, refresh tokens, or client secrets
- Always stream uploads directly to Drive — do not write uploaded files to server disk

---

## License

MIT License — see [LICENSE](LICENSE).

---

## Contributing

Pull requests are welcome. For major changes, please open an issue first.
