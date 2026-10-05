<?php

namespace Mlp\SiveraReporter\Console;

use Illuminate\Console\Command;

class InstallCommand extends Command
{
    protected $signature = 'sivera:install';

    protected $description = 'Publish the SIVERA reporter configuration';

    public function handle(): int
    {
        $this->call('vendor:publish', ['--tag' => 'sivera-config', '--force' => true]);
        $this->info('Set SIVERA_URL, SIVERA_APP_ID, SIVERA_CLIENT_ID, and SIVERA_SECRET in .env.');

        return self::SUCCESS;
    }
}
