<?php

namespace App\Jobs;

use App\Mail\DailyDigestMail;
use App\Models\NotificationLog;
use App\Services\Sivera\IssueAnalytics;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $notificationLogId) {}

    public function handle(IssueAnalytics $analytics): void
    {
        $log = NotificationLog::query()->with('rule')->find($this->notificationLogId);
        if (! $log || ! $log->digest_date || $log->status === 'sent'
            || ! $log->rule?->is_active || $log->rule->channel !== 'email' || $log->rule->trigger !== 'daily_digest') {
            return;
        }
        try {
            $summary = $analytics->summary($log->digest_date, $log->digest_date);
            Mail::to($log->digest_recipient)->send(new DailyDigestMail($summary));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed']);
            report($exception);
        }
    }
}
