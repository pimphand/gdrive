<?php

namespace Pimphand\GDrive\Commands;

use Illuminate\Console\Command;
use Pimphand\GDrive\GoogleDriveStorage;

class GoogleDriveSyncCommand extends Command
{
    protected $signature = 'gdrive:sync';

    protected $description = 'Sync quota and list files in the Google Drive app folder';

    public function handle(): int
    {
        try {
            $storage = GoogleDriveStorage::fromConfig();
        } catch (\Throwable $exception) {
            $this->error($exception->getMessage());

            return self::FAILURE;
        }

        $quota = $storage->quota();
        $this->info('Quota synced.');
        $this->table(
            ['total', 'used', 'available', 'trash', 'synced_at'],
            [[
                $quota['total_bytes'] ?? 'unlimited',
                $quota['used_bytes'],
                $quota['available_bytes'] ?? 'n/a',
                $quota['trash_bytes'] ?? '0',
                $quota['last_synced_at'],
            ]],
        );

        $files = $storage->list();
        $this->info(count($files).' file(s) in '.config('gdrive.app_folder_name').' folder.');

        if ($files !== []) {
            $this->table(
                ['id', 'name', 'mime_type', 'size'],
                array_map(fn (array $file) => [$file['id'], $file['name'], $file['mime_type'], $file['size']], $files),
            );
        }

        return self::SUCCESS;
    }
}
