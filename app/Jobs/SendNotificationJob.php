<?php

namespace App\Jobs;

use App\Mail\DailyDigestMail;
use App\Models\Issue;
use App\Models\IssueAlert;
use App\Models\NotificationLog;
use App\Services\Sivera\IssueAnalytics;
use Carbon\CarbonImmutable;
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
        $log = NotificationLog::query()->with('user')->find($this->notificationLogId);
        if (! $log || ! $log->digest_date || $log->status === 'sent'
            || $log->channel !== 'email' || ! $log->user?->is_active
            || mb_strtolower(trim($log->user->email)) !== $log->digest_recipient) {
            return;
        }
        try {
            $summary = $analytics->summary($log->digest_date, $log->digest_date);
            $start = CarbonImmutable::parse($log->digest_date, config('sivera.timezone'))->startOfDay();
            $end = $start->addDay()->utc();
            $summary['ignored'] = Issue::query()->where('status', 'IGNORED')->whereHas('receipts', fn ($query) => $query
                ->where('created_at', '>=', $start->utc())->where('created_at', '<', $end))->count();
            $statusChanges = IssueAlert::query()->where('type', 'status_changed')
                ->where('created_at', '>=', $start->utc())->where('created_at', '<', $end);
            $summary['status_change_total'] = (clone $statusChanges)->count();
            $summary['status_changes'] = $statusChanges->with('issue.application:id,name')->latest('created_at')->latest('id')->limit(20)->get();
            $followUps = Issue::query()->whereNotIn('status', Issue::CLOSED_STATUSES);
            $summary['follow_up_total'] = (clone $followUps)->count();
            $summary['follow_up_issues'] = $followUps->with('application:id,name')->latest('last_seen_at')->orderBy('id')->limit(20)->get();
            Mail::to($log->digest_recipient)->send(new DailyDigestMail($summary));
            $log->update(['status' => 'sent', 'sent_at' => now()]);
        } catch (Throwable $exception) {
            $log->update(['status' => 'failed']);
            report($exception);
        }
    }
}
