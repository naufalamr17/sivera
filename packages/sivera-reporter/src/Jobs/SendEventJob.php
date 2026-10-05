<?php

namespace Mlp\SiveraReporter\Jobs;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Mlp\SiveraReporter\Reporter;

class SendEventJob implements ShouldQueue
{
    use Dispatchable, Queueable;

    public int $tries = 1;

    /**
     * @param  array<string, mixed>  $event
     */
    public function __construct(public array $event) {}

    public function handle(Reporter $reporter): void
    {
        $reporter->sendNow($this->event);
    }
}
