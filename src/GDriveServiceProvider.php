<?php

namespace Pimphand\GDrive;

use Illuminate\Support\Facades\Route;
use Illuminate\Support\ServiceProvider;
use Pimphand\GDrive\Commands\GoogleDriveSyncCommand;
use Pimphand\GDrive\Commands\PublishExampleCommand;
use Pimphand\GDrive\Commands\SeedGoogleConfigCommand;
use Pimphand\GDrive\Contracts\GoogleDriveTokenStore;
use Pimphand\GDrive\EnvGoogleDriveTokenStore;
use Pimphand\GDrive\Support\ExamplePublishPaths;

class GDriveServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->mergeConfigFrom(__DIR__.'/../config/gdrive.php', 'gdrive');

        $this->app->singleton(TokenCrypto::class, fn () => TokenCrypto::fromConfig());

        $this->app->singleton(GoogleDriveTokenStore::class, function ($app) {
            $storeClass = config('gdrive.token_store', EnvGoogleDriveTokenStore::class);

            return $app->make($storeClass);
        });

        $this->app->singleton(GoogleDriveService::class, function ($app) {
            return GoogleDriveService::fromConfig($app->make(GoogleDriveTokenStore::class));
        });

        $this->app->singleton(GoogleDriveStreamService::class, function ($app) {
            return new GoogleDriveStreamService($app->make(GoogleDriveService::class));
        });

        $this->app->singleton(GoogleDriveStorage::class, function ($app) {
            return GoogleDriveStorage::fromConfig($app->make(GoogleDriveTokenStore::class));
        });
    }

    public function boot(): void
    {
        $this->registerExampleRoutes();

        if ($this->app->runningInConsole()) {
            $this->publishes([
                __DIR__.'/../config/gdrive.php' => config_path('gdrive.php'),
            ], 'gdrive-config');

            $this->publishes(ExamplePublishPaths::paths(), 'gdrive-example');

            $this->commands([
                PublishExampleCommand::class,
                SeedGoogleConfigCommand::class,
                GoogleDriveSyncCommand::class,
            ]);
        }
    }

    private function registerExampleRoutes(): void
    {
        $routesFile = base_path('routes/gdrive.php');

        if (! file_exists($routesFile)) {
            return;
        }

        $this->app->booted(function () use ($routesFile) {
            Route::middleware('web')->group($routesFile);
        });
    }
}
