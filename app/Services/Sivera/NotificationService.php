<?php

namespace App\Services\Sivera;

use App\Jobs\SendNotificationJob;
use App\Models\Issue;
use App\Models\IssueAlert;
use App\Models\NotificationLog;
use App\Models\User;

class NotificationService
{
    public function createDailyFollowUps(string $date): void
    {
        Issue::query()->whereNotIn('status', Issue::CLOSED_STATUSES)->select(['id', 'status'])
            ->chunkById(500, function ($issues) use ($date): void {
                foreach ($issues as $issue) {
                    IssueAlert::query()->firstOrCreate([
                        'issue_id' => $issue->id,
                        'follow_up_date' => $date,
                    ], [
                        'type' => 'follow_up',
                        'status' => $issue->status,
                    ]);
                }
            });
    }

    /** @return array{sent: int, failed: int} */
    public function sendDigest(string $date): array
    {
        $result = ['sent' => 0, 'failed' => 0];
        $users = User::query()->where('is_active', true)->orderBy('id')->get()
            ->unique(fn (User $user): string => mb_strtolower(trim($user->email)));
        foreach ($users as $user) {
            $log = NotificationLog::query()->firstOrCreate([
                'digest_date' => $date,
                'digest_recipient' => mb_strtolower(trim($user->email)),
            ], [
                'user_id' => $user->id,
                'channel' => 'email',
                'status' => 'pending',
            ]);
            if ($log->status === 'sent') {
                continue;
            }
            $log->update(['user_id' => $user->id]);
            SendNotificationJob::dispatchSync($log->id);
            $status = $log->fresh()->status;
            if (isset($result[$status])) {
                $result[$status]++;
            }
        }

        return $result;
    }
}
