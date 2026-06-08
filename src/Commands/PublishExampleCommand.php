<?php

namespace Pimphand\GDrive\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\File;
use Pimphand\GDrive\Support\ExamplePublishPaths;

class PublishExampleCommand extends Command
{
    protected $signature = 'gdrive:publish-example';

    protected $description = 'Publish the gdrive example app stubs, overwriting existing files with the latest versions';

    public function handle(): int
    {
        $paths = ExamplePublishPaths::paths();
        $published = 0;

        foreach ($paths as $from => $to) {
            if (! is_file($from)) {
                $this->error("Stub file missing: {$from}");

                return self::FAILURE;
            }

            File::ensureDirectoryExists(dirname($to));
            File::copy($from, $to);

            $this->components->info("Published: {$to}");
            $published++;
        }

        $this->newLine();
        $this->info("Done. {$published} file(s) published.");

        return self::SUCCESS;
    }
}
