<?php

use App\Http\Controllers\GDrive\GDriveApiController;
use App\Http\Controllers\GDrive\GDriveAuthController;
use App\Http\Controllers\GDrive\GDriveController;
use App\Services\GDrive\FileGoogleDriveTokenStore;
use Illuminate\Support\Facades\Route;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use Pimphand\GDrive\GoogleDriveService;
use Pimphand\GDrive\GoogleDriveStorage;

app()->singleton(GoogleDriveTokenStore::class, FileGoogleDriveTokenStore::class);

app()->singleton(GoogleDriveService::class, function ($app) {
    return GoogleDriveService::fromConfig($app->make(GoogleDriveTokenStore::class));
});

app()->singleton(GoogleDriveStorage::class, function ($app) {
    return GoogleDriveStorage::fromConfig($app->make(GoogleDriveTokenStore::class));
});

Route::prefix('gdrive')->name('gdrive.')->group(function () {
    Route::get('/', [GDriveController::class, 'index'])->name('index');

    Route::get('/connect', [GDriveAuthController::class, 'connect'])->name('connect');
    Route::get('/callback', [GDriveAuthController::class, 'callback'])->name('callback');
    Route::post('/disconnect', [GDriveAuthController::class, 'disconnect'])->name('disconnect');

    Route::get('/files/{id}/download', [GDriveController::class, 'download'])->name('download');
    Route::get('/files/{id}/preview', [GDriveController::class, 'preview'])->name('preview');
    Route::delete('/files/{id}', [GDriveController::class, 'destroy'])->name('destroy');

    Route::prefix('api')->name('api.')->group(function () {
        Route::post('/upload/init', [GDriveApiController::class, 'initUpload'])->name('upload.init');
        Route::post('/upload/chunk', [GDriveApiController::class, 'uploadChunk'])->name('upload.chunk');
        Route::post('/sync', [GDriveApiController::class, 'sync'])->name('sync');
        Route::post('/folders', [GDriveApiController::class, 'createFolder'])->name('folders.create');
        Route::get('/files/{id}', [GDriveApiController::class, 'show'])->name('files.show');
        Route::patch('/files/{id}', [GDriveApiController::class, 'rename'])->name('files.rename');
        Route::post('/files/{id}/copy', [GDriveApiController::class, 'copy'])->name('files.copy');
        Route::post('/files/{id}/star', [GDriveApiController::class, 'toggleStar'])->name('files.star');
    });
});

Route::get('/auth/google/callback', [GDriveAuthController::class, 'callback'])
    ->name('gdrive.auth-callback-alias');
