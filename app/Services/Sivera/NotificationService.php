<?php

namespace App\Services\Sivera;

use App\Jobs\SendNotificationJob;
use App\Models\NotificationLog;
use App\Models\NotificationRule;

class NotificationService
{
    /** @return array{sent: int, failed: int} */
    public function sendDigest(string $date): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        $rules = NotificationRule::query()->where('is_active', true)->where('channel', 'email')
            ->where('trigger', 'daily_digest')->orderBy('id')->get()->unique(fn ($rule) => mb_strtolower($rule->target));
        foreach ($rules as $rule) {
            $log = NotificationLog::query()->firstOrCreate([
                'digest_date' => $date, 'digest_recipient' => mb_strtolower($rule->target),
            ], ['notification_rule_id' => $rule->id, 'channel' => 'email', 'status' => 'pending']);
            if ($log->status === 'sent') {
                continue;
            }
            $log->update(['notification_rule_id' => $rule->id]);
            SendNotificationJob::dispatchSync($log->id);
            $status = $log->fresh()->status;
            if (isset($result[$status])) {
                $result[$status]++;
            }
        }

        return $result;
    }
}
