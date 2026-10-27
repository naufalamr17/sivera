<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('sivera:cleanup')->dailyAt('00:30');
Schedule::command('sivera:send-digest')->dailyAt(config('sivera.digest_time'))
    ->timezone(config('sivera.timezone'))->withoutOverlapping();
