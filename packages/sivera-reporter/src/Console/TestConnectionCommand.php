<?php

namespace Mlp\SiveraReporter\Console;

use Illuminate\Console\Command;
use Mlp\SiveraReporter\EventBuilder;
use Mlp\SiveraReporter\Reporter;
use RuntimeException;

class TestConnectionCommand extends Command
{
    protected $signature = 'sivera:test';

    protected $description = 'Send a synthetic exception to SIVERA';

    public function handle(Reporter $reporter, EventBuilder $events): int
    {
        $this->line('SIVERA Reporter');
        $this->line('Application: '.config('sivera.app_id'));
        $this->line('Endpoint: '.config('sivera.url'));

        if (! $reporter->sendNow($events->build(new RuntimeException('SIVERA connection test')))) {
            $this->error('Event delivery failed: '.($reporter->lastError() ?? 'Unknown delivery failure.'));

            return self::FAILURE;
        }

        $this->info('Connection, authentication, signature, and event delivery: OK.');

        return self::SUCCESS;
    }
}
