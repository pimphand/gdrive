<?php

namespace Pimphand\GDrive\Facades;

use Illuminate\Support\Facades\Facade;
use Pimphand\GDrive\GoogleDriveStorage;

/**
 * @method static array put(string $fileName, mixed $body, string $mimeType, ?string $sizeBytes = null)
 * @method static \Symfony\Component\HttpFoundation\StreamedResponse get(string $fileId, ?string $range = null, string $disposition = 'attachment')
 * @method static void delete(string $fileId)
 * @method static bool exists(string $fileId)
 * @method static array list()
 * @method static string|null url(string $fileId)
 * @method static array quota()
 * @method static array sync()
 * @method static \Pimphand\GDrive\GoogleDriveService drive()
 *
 * @see \Pimphand\GDrive\GoogleDriveStorage
 */
class GDrive extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return GoogleDriveStorage::class;
    }
}
