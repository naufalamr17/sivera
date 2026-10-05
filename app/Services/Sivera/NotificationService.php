<?php

namespace App\Services\Sivera;

use App\Jobs\SendNotificationJob;
use App\Models\Issue;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use Illuminate\Support\Facades\DB;

class NotificationService
{
    public function evaluate(Issue $issue, string $trigger): void
    {
        $rules = NotificationRule::query()->where('is_active', true)->where('trigger', $trigger)
            ->where(fn ($query) => $query->whereNull('severity')->orWhere('severity', $issue->severity))
            ->get();

        foreach ($rules as $rule) {
            if ($trigger === 'high_frequency') {
                $count = $issue->receipts()
                    ->where('created_at', '>=', now()->subMinutes($rule->frequency_window_minutes))
                    ->count();

                if ($count < $rule->frequency_threshold) {
                    continue;
                }
            }

            $recent = NotificationLog::query()
                ->where('issue_id', $issue->id)
                ->where('notification_rule_id', $rule->id)
                ->where('created_at', '>=', now()->subMinutes($rule->cooldown_minutes))
                ->exists();

            if ($recent) {
                continue;
            }

            $log = NotificationLog::create([
                'issue_id' => $issue->id,
                'notification_rule_id' => $rule->id,
                'channel' => $rule->channel,
                'status' => 'pending',
            ]);

            DB::afterCommit(fn () => SendNotificationJob::dispatchAfterResponse($log->id));
        }
    }
}
