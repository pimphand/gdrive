<?php

return [

    /*
    |--------------------------------------------------------------------------
    | Google OAuth (Drive API)
    |--------------------------------------------------------------------------
    |
    | OAuth client credentials for Google Drive API access.
    | GDRIVE_* env vars are preferred; GOOGLE_* fallbacks keep compatibility
    | with pimpdrive Node backend naming.
    |
    */

    'client_id' => env('GDRIVE_CLIENT_ID') ?: env('GOOGLE_CLIENT_ID'),
    'client_secret' => env('GDRIVE_CLIENT_SECRET') ?: env('GOOGLE_CLIENT_SECRET'),
    'redirect_uri' => env('GDRIVE_REDIRECT_URI') ?: env('GOOGLE_REDIRECT_URI') ?: 'http://localhost:8000/gdrive/callback',

    'scopes' => [
        'https://www.googleapis.com/auth/drive',
        'https://www.googleapis.com/auth/userinfo.email',
        'https://www.googleapis.com/auth/userinfo.profile',
    ],

    'app_folder_name' => env('GDRIVE_APP_FOLDER', env('GOOGLE_DRIVE_APP_FOLDER', 'pimpdrive')),

    'token_encryption_key' => env('GDRIVE_TOKEN_ENCRYPTION_KEY', env('TOKEN_ENCRYPTION_KEY', env('APP_KEY'))),

    'token_refresh_buffer_seconds' => (int) env('GDRIVE_TOKEN_REFRESH_BUFFER', 60),

    'upload_chunk_max_bytes' => (int) env('GDRIVE_UPLOAD_CHUNK_MAX_BYTES', 2 * 1024 * 1024),

    'folder_mime_type' => 'application/vnd.google-apps.folder',

    /*
    |--------------------------------------------------------------------------
    | OAuth tokens (env-based single-account mode)
    |--------------------------------------------------------------------------
    |
    | Used by EnvGoogleDriveTokenStore. Values may be plain text or AES-256-GCM
    | encrypted strings in iv:tag:ciphertext format (compatible with pimpdrive).
    |
    */

    'access_token' => env('GDRIVE_ACCESS_TOKEN', env('GOOGLE_ACCESS_TOKEN')),
    'refresh_token' => env('GDRIVE_REFRESH_TOKEN', env('GOOGLE_REFRESH_TOKEN')),
    'token_expires_at' => env('GDRIVE_TOKEN_EXPIRES_AT', env('GOOGLE_TOKEN_EXPIRES_AT')),

    /*
    |--------------------------------------------------------------------------
    | Token store implementation
    |--------------------------------------------------------------------------
    |
    | Bind a custom GoogleDriveTokenStore for multi-account/database storage.
    | Default: Pimphand\GDrive\EnvGoogleDriveTokenStore
    |
    */

    'token_store' => env('GDRIVE_TOKEN_STORE', Pimphand\GDrive\EnvGoogleDriveTokenStore::class),

    'export_mime_types' => [
        'download' => [
            'application/vnd.google-apps.document' => [
                'mimeType' => 'application/pdf',
                'extension' => '.pdf',
            ],
            'application/vnd.google-apps.spreadsheet' => [
                'mimeType' => 'application/vnd.openxmlformats-officedocument.spreadsheetml.sheet',
                'extension' => '.xlsx',
            ],
            'application/vnd.google-apps.presentation' => [
                'mimeType' => 'application/pdf',
                'extension' => '.pdf',
            ],
            'application/vnd.google-apps.drawing' => [
                'mimeType' => 'image/png',
                'extension' => '.png',
            ],
        ],
        'preview' => [
            'application/vnd.google-apps.document' => [
                'mimeType' => 'application/pdf',
                'extension' => '.pdf',
            ],
            'application/vnd.google-apps.spreadsheet' => [
                'mimeType' => 'application/pdf',
                'extension' => '.pdf',
            ],
            'application/vnd.google-apps.presentation' => [
                'mimeType' => 'application/pdf',
                'extension' => '.pdf',
            ],
            'application/vnd.google-apps.drawing' => [
                'mimeType' => 'image/png',
                'extension' => '.png',
            ],
        ],
    ],

];
