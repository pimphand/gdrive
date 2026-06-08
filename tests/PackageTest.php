<?php

namespace Pimphand\GDrive\Tests;

use Orchestra\Testbench\TestCase;
use Pimphand\GDrive\Facades\GDrive;
use Pimphand\GDrive\GDriveServiceProvider;
use Pimphand\GDrive\GoogleDriveService;
use Pimphand\GDrive\GoogleDriveStorage;
use Pimphand\GDrive\Support\ExamplePublishPaths;
use Pimphand\GDrive\TokenCrypto;

class PackageTest extends TestCase
{
    protected function getPackageProviders($app): array
    {
        return [GDriveServiceProvider::class];
    }

    protected function getPackageAliases($app): array
    {
        return ['GDrive' => GDrive::class];
    }

    protected function defineEnvironment($app): void
    {
        $app['config']->set('gdrive.client_id', 'test-client-id');
        $app['config']->set('gdrive.client_secret', 'test-client-secret');
        $app['config']->set('gdrive.token_encryption_key', 'test-encryption-key');
        $app['config']->set('gdrive.access_token', 'test-access-token');
        $app['config']->set('gdrive.refresh_token', 'test-refresh-token');
    }

    public function test_service_provider_registers_bindings(): void
    {
        $this->assertInstanceOf(GoogleDriveStorage::class, $this->app->make(GoogleDriveStorage::class));
        $this->assertInstanceOf(TokenCrypto::class, $this->app->make(TokenCrypto::class));
    }

    public function test_facade_resolves_storage(): void
    {
        $this->assertInstanceOf(GoogleDriveStorage::class, GDrive::getFacadeRoot());
    }

    public function test_token_crypto_encrypt_decrypt_roundtrip(): void
    {
        $crypto = TokenCrypto::fromConfig();
        $encrypted = $crypto->encrypt('secret-token');
        $decrypted = $crypto->decrypt($encrypted);

        $this->assertSame('secret-token', $decrypted);
    }

    public function test_get_auth_url_puts_state_in_query_not_scope(): void
    {
        $service = GoogleDriveService::fromConfig();
        $state = 'AQBNhVe_-XmJpwtbHjhYaEeQ3ejC10YIpA4QsRj0aTs';
        $url = $service->getAuthUrl($state);

        parse_str((string) parse_url($url, PHP_URL_QUERY), $query);

        $this->assertSame($state, $query['state'] ?? null);
        $this->assertStringContainsString('https://www.googleapis.com/auth/drive', $query['scope'] ?? '');
        $this->assertStringNotContainsString($state, $query['scope'] ?? '');
    }

    public function test_oauth_configured_detects_missing_credentials(): void
    {
        $this->app['config']->set('gdrive.client_id', '');
        $this->app['config']->set('gdrive.client_secret', '');

        $this->assertFalse(GoogleDriveService::oauthConfigured());

        $this->app['config']->set('gdrive.client_id', 'test-client-id');
        $this->app['config']->set('gdrive.client_secret', 'test-client-secret');

        $this->assertTrue(GoogleDriveService::oauthConfigured());
    }

    public function test_example_stubs_exist_for_publish(): void
    {
        $stub = ExamplePublishPaths::stubRoot();

        $this->assertFileExists("{$stub}/routes/gdrive.php");
        $this->assertFileExists("{$stub}/app/Http/Controllers/GDrive/GDriveController.php");
        $this->assertFileExists("{$stub}/app/Http/Controllers/GDrive/GDriveAuthController.php");
        $this->assertFileExists("{$stub}/app/Http/Controllers/GDrive/GDriveApiController.php");
        $this->assertFileExists("{$stub}/app/Services/GDrive/FileGoogleDriveTokenStore.php");
        $this->assertFileExists("{$stub}/app/Services/GDrive/GDriveFileCatalog.php");
        $this->assertFileExists("{$stub}/resources/views/gdrive/index.blade.php");
        $this->assertFileExists("{$stub}/resources/views/gdrive/layout.blade.php");
        $this->assertFileExists("{$stub}/public/js/gdrive-demo.js");
        $this->assertFileExists("{$stub}/public/css/gdrive-drive.css");
        $this->assertFileExists("{$stub}/gdrive.env.example");
    }

    public function test_publish_example_command_overwrites_with_latest_stubs(): void
    {
        $stubIndex = ExamplePublishPaths::stubRoot().'/resources/views/gdrive/index.blade.php';
        $targetIndex = resource_path('views/gdrive/index.blade.php');

        if (! is_dir(dirname($targetIndex))) {
            mkdir(dirname($targetIndex), 0777, true);
        }

        file_put_contents($targetIndex, '<!-- stale published view -->');

        $this->artisan('gdrive:publish-example')->assertSuccessful();

        $this->assertSame(file_get_contents($stubIndex), file_get_contents($targetIndex));
    }
}
