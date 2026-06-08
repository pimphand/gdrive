# pimphand/gdrive

Laravel package untuk menggunakan **Google Drive sebagai cloud storage** dengan API mirip S3. Mendukung OAuth2, upload streaming langsung ke Drive (tanpa simpan ke disk lokal), download/preview dengan byte-range, sync quota.

[![Latest Version on Packagist](https://img.shields.io/packagist/v/pimphand/gdrive.svg)](https://packagist.org/packages/pimphand/gdrive)
[![Total Downloads](https://img.shields.io/packagist/dt/pimphand/gdrive.svg)](https://packagist.org/packages/pimphand/gdrive)
[![License](https://img.shields.io/packagist/l/pimphand/gdrive.svg)](https://packagist.org/packages/pimphand/gdrive)

**📖 Documentation:** [English / Indonesia (HTML)](docs/index.html) — panduan integrasi PHP & JavaScript untuk developer baru.

---

## Fitur

- **S3-style API** — `put`, `get`, `delete`, `list`, `exists`, `url`, `quota`
- **OAuth2 Google Drive** — auth URL, exchange code, auto token refresh
- **Streaming upload** — file langsung ke Google Drive tanpa write ke disk server
- **Streaming download** — proxy file dari Drive dengan dukungan `Range` header
- **Google Workspace export** — Docs/Sheets/Slides otomatis di-export saat download/preview
- **Folder app `pimpdrive`** — semua file fisik disimpan di root folder Drive (konfigurabel)
- **Laravel auto-discovery** — service provider & facade terdaftar otomatis
- **Artisan commands** — `gdrive:seed-config`, `gdrive:sync`

---

## Requirements

- PHP 8.2+
- Laravel 11, 12, atau 13
- Google Cloud project dengan **Google Drive API** enabled
- Google OAuth Client ID & Secret

---

## Instalasi

### Via Packagist (production)

```bash
composer require pimphand/gdrive
```

### Via path repository (development lokal)

Tambahkan di `composer.json` project Laravel Anda:

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

Kemudian jalankan:

```bash
composer require pimphand/gdrive:@dev
```

### Publish config (opsional)

```bash
php artisan vendor:publish --tag=gdrive-config
```

File config akan tersedia di `config/gdrive.php`. Tanpa publish, config default package sudah otomatis di-merge.

### Publish contoh aplikasi lengkap (disarankan untuk pertama kali)

Satu perintah ini akan membuat **controller, routes, views, dan token store** — langsung bisa diakses di `/gdrive`:

```bash
php artisan vendor:publish --tag=gdrive-example
```

**Memperbarui stub setelah upgrade package** — `vendor:publish` tidak menimpa file yang sudah ada. Gunakan salah satu:

```bash
php artisan vendor:publish --tag=gdrive-example --force
# atau (selalu menimpa dengan versi terbaru dari package)
php artisan gdrive:publish-example
```

File yang dibuat otomatis:

| File | Fungsi |
|------|--------|
| `routes/gdrive.php` | Route `/gdrive` (auto-loaded oleh package) |
| `app/Http/Controllers/GDrive/GDriveController.php` | Dashboard, upload, download, preview, delete |
| `app/Http/Controllers/GDrive/GDriveAuthController.php` | OAuth connect / callback / disconnect |
| `app/Http/Controllers/GDrive/GDriveApiController.php` | API upload chunk + sync |
| `app/Services/GDrive/FileGoogleDriveTokenStore.php` | Simpan token terenkripsi di `storage/app/gdrive/` |
| `resources/views/gdrive/*.blade.php` | Halaman demo |
| `public/js/gdrive-demo.js` | Upload langsung ke Drive + sync |
| `public/css/gdrive-drive.css` | Style demo |
| `config/gdrive.php` | Konfigurasi package |
| `gdrive.env.example` | Template variabel `.env` |

**Tidak perlu edit `routes/web.php`** — package otomatis memuat `routes/gdrive.php` jika file tersebut ada.

#### Setup cepat (hanya .env)

1. Publish example (lihat di atas)
2. Salin variabel dari `gdrive.env.example` ke `.env`:

```env
GDRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GDRIVE_CLIENT_SECRET=your-client-secret
GDRIVE_REDIRECT_URI=http://localhost:8000/auth/google/callback
```

3. Di Google Cloud Console, tambahkan redirect URI: `http://localhost:8000/auth/google/callback`
4. Jalankan server:

```bash
php artisan serve
```

5. Buka **http://localhost:8000/gdrive** → klik **Connect Google Drive**

#### Route contoh

| Method | URL | Fungsi |
|--------|-----|--------|
| GET | `/gdrive` | Dashboard (quota, daftar file, upload) |
| GET | `/gdrive/connect` | Mulai OAuth Google |
| GET | `/gdrive/callback` | OAuth callback |
| POST | `/gdrive/disconnect` | Putus koneksi Drive |
| POST | `/gdrive/upload` | Upload file |
| GET | `/gdrive/files/{id}/download` | Download file |
| GET | `/gdrive/files/{id}/preview` | Preview inline |
| DELETE | `/gdrive/files/{id}` | Hapus file |

---

## Konfigurasi Google Cloud

1. Buka [Google Cloud Console](https://console.cloud.google.com/)
2. Buat project (atau gunakan yang sudah ada)
3. Enable **Google Drive API**
4. Buat **OAuth 2.0 Client ID** (tipe: Web application)
5. Tambahkan **Authorized redirect URI**, contoh:
   - `http://localhost:8000/auth/google/callback`
   - `https://yourdomain.com/auth/google/callback`

---

## Environment Variables

Tambahkan ke `.env` project Laravel:

```env
# OAuth client (wajib)
GDRIVE_CLIENT_ID=your-client-id.apps.googleusercontent.com
GDRIVE_CLIENT_SECRET=your-client-secret
GDRIVE_REDIRECT_URI=http://localhost:8000/auth/google/callback

# Nama folder root di Google Drive (default: pimpdrive)
GDRIVE_APP_FOLDER=pimpdrive

# Kunci enkripsi token (gunakan string acak panjang)
GDRIVE_TOKEN_ENCRYPTION_KEY=your-random-secret-key

# OAuth tokens akun yang terhubung (setelah OAuth flow)
GDRIVE_ACCESS_TOKEN=ya29....
GDRIVE_REFRESH_TOKEN=1//0g....
GDRIVE_TOKEN_EXPIRES_AT=2026-06-07T12:00:00+00:00
```
---

## Validasi config

```bash
php artisan gdrive:seed-config

# Tampilkan nilai terenkripsi (untuk seed database)
php artisan gdrive:seed-config --show-encrypted
```

---

## Penggunaan Dasar

### Facade (paling sederhana)

```php
use Pimphand\GDrive\Facades\GDrive;

// Upload file
$file = GDrive::put(
    fileName: 'report.pdf',
    body: $request->getContent(),          // string, resource, atau StreamInterface
    mimeType: 'application/pdf',
    sizeBytes: (string) $request->header('Content-Length'), // opsional
);

// Response: ['id' => '...', 'name' => '...', 'mime_type' => '...', 'size' => '...']

// Download (return StreamedResponse)
return GDrive::get($file['id']);

// Preview inline (video/PDF di browser)
return GDrive::get($file['id'], disposition: 'inline');

// Byte-range (video seek)
return GDrive::get($file['id'], range: $request->header('Range'));

// Hapus file
GDrive::delete($file['id']);

// List semua file di folder app
$files = GDrive::list();

// Cek file ada
GDrive::exists($file['id']);

// URL view Google Drive
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

## Upload Controller (contoh lengkap)

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

        // Simpan $file['id'] ke database aplikasi Anda
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

## OAuth Flow (contoh)

Package menyediakan helper OAuth di `GoogleDriveService`. Contoh route sederhana:

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

    // Simpan $credentials ke database (gunakan toEncrypted() untuk enkripsi)
    $encrypted = $credentials->toEncrypted();

    // ... persist ke ConnectedAccount model Anda

    return redirect('/dashboard')->with('success', 'Google Drive connected.');
});
```

---

## Custom Token Store (multi-account)

Untuk aplikasi dengan banyak akun Google Drive, implementasikan `GoogleDriveTokenStore`:

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

Gunakan dengan service langsung:

```php
use Pimphand\GDrive\GoogleDriveStorage;
use App\Services\DatabaseGoogleDriveTokenStore;

$account = ConnectedAccount::findOrFail($id);
$storage = GoogleDriveStorage::fromConfig(new DatabaseGoogleDriveTokenStore($account));

$file = $storage->put('backup.zip', fopen('php://input', 'r'), 'application/zip');
```

Atau bind global di `AppServiceProvider`:

```php
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use App\Services\DatabaseGoogleDriveTokenStore;

// config/gdrive.php
'token_store' => DatabaseGoogleDriveTokenStore::class,
```

---

## Sync File dari Drive

Package menyediakan logika reconcile:

```php
use Pimphand\GDrive\Facades\GDrive;

// Ambil records lokal dari database, keyed by provider_file_id
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

Menampilkan quota dan daftar file di folder app Drive.

---

## API Reference

### `GoogleDriveStorage` (S3-style)

| Method | Deskripsi | Return |
|---|---|---|
| `put($name, $body, $mime, $size?)` | Upload ke folder app | `array{id, name, mime_type, size}` |
| `get($id, $range?, $disposition?)` | Stream download/preview | `StreamedResponse` |
| `delete($id)` | Hapus file di Drive | `void` |
| `exists($id)` | Cek file ada | `bool` |
| `list()` | List file di folder app | `array[]` |
| `url($id)` | Google web view URL | `?string` |
| `quota()` | Sync & return quota | `array` |
| `drive()` | Akses low-level service | `GoogleDriveService` |

### `GoogleDriveService` (low-level)

| Method | Deskripsi |
|---|---|
| `getAuthUrl($state)` | Generate OAuth authorization URL |
| `exchangeAuthCode($code)` | Tukar auth code → credentials |
| `ensureAppFolder()` | Get/create root app folder |
| `upload(...)` | Upload file |
| `rename($id, $name)` | Rename di Drive |
| `delete($id)` | Hapus di Drive |
| `getMetadata($id)` | Ambil metadata file |
| `listAppFolderFiles()` | List semua file di folder app |
| `syncAppFolderFiles($existing)` | Reconcile dengan records lokal |
| `syncQuota()` | Ambil storage quota dari Drive API |

### `TokenCrypto`

| Method | Deskripsi |
|---|---|
| `encrypt($value)` | AES-256-GCM encrypt |
| `decrypt($value)` | AES-256-GCM decrypt |
| `randomToken($bytes?)` | Generate random URL-safe token |
| `hashToken($token)` | SHA-256 hash |

---

## Arsitektur

```
Laravel App
    │
    ├── GDrive Facade / GoogleDriveStorage
    │       ├── put()  ──► GoogleDriveService::upload()
    │       ├── get()  ──► GoogleDriveStreamService::stream()
    │       └── quota() ──► GoogleDriveService::syncQuota()
    │
    ├── GoogleDriveTokenStore (interface)
    │       └── EnvGoogleDriveTokenStore (default, dari .env)
    │
    └── Google Drive API
            └── /root/pimpdrive/   ← semua file fisik disimpan di sini
```

**Penting:** Virtual folder (organisasi file di aplikasi) adalah metadata database saja — tidak dicerminkan ke struktur folder Google Drive. Semua file fisik berada di satu folder root `pimpdrive` per akun Google.

## Publish ke Packagist

1. Push package ke GitHub repository `pimphand/gdrive`
2. Buat release / tag semver (contoh: `v1.0.0`)
3. Submit ke [Packagist.org](https://packagist.org/packages/submit)
4. Install di project lain:

```bash
composer require pimphand/gdrive
```

---

## Security

- Jangan commit `.env` atau token ke repository
- Simpan refresh token terenkripsi di database (gunakan `TokenCrypto`)
- Gunakan OAuth `state` parameter untuk CSRF protection
- Jangan log access token, refresh token, atau client secret
- Upload selalu stream langsung ke Drive — jangan tulis file upload ke disk server

---

## License

MIT License — lihat [LICENSE](LICENSE).

---

## Contributing

Pull request dipersilakan. Untuk perubahan besar, buka issue terlebih dahulu.
