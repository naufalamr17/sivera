<?php

namespace App\Console\Commands;

use App\Services\Sivera\NotificationService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Validator;

#[Signature('sivera:send-digest {--date= : Local date to summarize (YYYY-MM-DD), defaults to yesterday}')]
#[Description('Send yesterday summary to all active users and create daily issue follow-ups')]
class SendDailyDigestCommand extends Command
{
    public function handle(NotificationService $notifications): int
    {
        $today = CarbonImmutable::now(config('sivera.timezone'))->toDateString();
        $date = $this->option('date') ?: CarbonImmutable::parse($today)->subDay()->toDateString();
        $validator = Validator::make(['date' => $date], ['date' => ['required', 'date_format:Y-m-d', 'before:'.$today]]);
        if ($validator->fails()) {
            $this->error('Choose a completed day using YYYY-MM-DD.');

            return self::FAILURE;
        }
        $lock = Cache::lock('sivera:digest:'.$date, 3600);
        if (! $lock->get()) {
            $this->warn('This digest is already running.');

            return self::SUCCESS;
        }
        try {
            if (! $this->option('date')) {
                $notifications->createDailyFollowUps($today);
            }
            $result = $notifications->sendDigest($date);
            $this->info("Digest {$date}: {$result['sent']} sent, {$result['failed']} failed.");

            return $result['failed'] > 0 ? self::FAILURE : self::SUCCESS;
        } finally {
            $lock->release();
        }
    }
}
