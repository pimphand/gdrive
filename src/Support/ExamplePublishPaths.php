<?php

namespace Pimphand\GDrive\Support;

class ExamplePublishPaths
{
    public static function stubRoot(): string
    {
        return dirname(__DIR__, 2).'/resources/stubs/example';
    }

    public static function packageRoot(): string
    {
        return dirname(__DIR__, 2);
    }

    /**
     * @return array<string, string>
     */
    public static function paths(): array
    {
        $stub = self::stubRoot();
        $package = self::packageRoot();

        return [
            "{$stub}/routes/gdrive.php" => base_path('routes/gdrive.php'),
            "{$stub}/app/Http/Controllers/GDrive/GDriveController.php" => app_path('Http/Controllers/GDrive/GDriveController.php'),
            "{$stub}/app/Http/Controllers/GDrive/GDriveAuthController.php" => app_path('Http/Controllers/GDrive/GDriveAuthController.php'),
            "{$stub}/app/Http/Controllers/GDrive/GDriveApiController.php" => app_path('Http/Controllers/GDrive/GDriveApiController.php'),
            "{$stub}/app/Services/GDrive/FileGoogleDriveTokenStore.php" => app_path('Services/GDrive/FileGoogleDriveTokenStore.php'),
            "{$stub}/app/Services/GDrive/GDriveFileCatalog.php" => app_path('Services/GDrive/GDriveFileCatalog.php'),
            "{$stub}/resources/views/gdrive/layout.blade.php" => resource_path('views/gdrive/layout.blade.php'),
            "{$stub}/resources/views/gdrive/index.blade.php" => resource_path('views/gdrive/index.blade.php'),
            "{$stub}/public/js/gdrive-demo.js" => public_path('js/gdrive-demo.js'),
            "{$stub}/public/css/gdrive-drive.css" => public_path('css/gdrive-drive.css'),
            "{$stub}/gdrive.env.example" => base_path('gdrive.env.example'),
            "{$package}/config/gdrive.php" => config_path('gdrive.php'),
        ];
    }
}
