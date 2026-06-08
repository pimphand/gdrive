<?php

namespace Pimphand\GDrive\Commands;

use Illuminate\Console\Command;
use Pimphand\GDrive\TokenCrypto;

class SeedGoogleConfigCommand extends Command
{
    protected $signature = 'gdrive:seed-config
                            {--show-encrypted : Print encrypted client credentials for database seeding}';

    protected $description = 'Validate Google OAuth env vars and optionally print encrypted credentials';

    public function handle(): int
    {
        $clientId = config('gdrive.client_id');
        $clientSecret = config('gdrive.client_secret');
        $redirectUri = config('gdrive.redirect_uri');

        if (! is_string($clientId) || $clientId === '' || ! is_string($clientSecret) || $clientSecret === '') {
            $this->error('GDRIVE_CLIENT_ID and GDRIVE_CLIENT_SECRET (or GOOGLE_CLIENT_ID/SECRET) are required in .env');

            return self::FAILURE;
        }

        $this->info('Google Drive OAuth config is valid.');
        $this->line("Redirect URI: {$redirectUri}");
        $this->line('Scopes: ' . implode(', ', config('gdrive.scopes', [])));

        if ($this->option('show-encrypted')) {
            $crypto = TokenCrypto::fromConfig();

            $this->newLine();
            $this->comment('Encrypted values');
            $this->line('client_id_encrypted=' . $crypto->encrypt($clientId));
            $this->line('client_secret_encrypted=' . $crypto->encrypt($clientSecret));
        }

        return self::SUCCESS;
    }
}
