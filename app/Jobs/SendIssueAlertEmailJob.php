<?php

namespace App\Jobs;

use App\Mail\IssueAlertMail;
use App\Models\IssueAlert;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Support\Facades\Mail;
use RuntimeException;
use Throwable;

class SendIssueAlertEmailJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $timeout = 60;

    public function __construct(public int $issueAlertId) {}

    /** @return list<int> */
    public function backoff(): array
    {
        return [10, 60];
    }

    /** @return list<WithoutOverlapping> */
    public function middleware(): array
    {
        return [(new WithoutOverlapping((string) $this->issueAlertId))->releaseAfter(10)->expireAfter(90)];
    }

    public function handle(): void
    {
        $alert = IssueAlert::query()->with('issue.application')->find($this->issueAlertId);
        if (! $alert || ! in_array($alert->type, ['event', 'status_changed'], true)) {
            return;
        }

        $failed = false;
        $users = User::query()->where('is_active', true)->orderBy('id')->get()
            ->unique(fn (User $user): string => mb_strtolower(trim($user->email)));

        foreach ($users as $user) {
            $recipient = mb_strtolower(trim($user->email));
            $log = NotificationLog::query()->firstOrCreate([
                'issue_alert_id' => $alert->id,
                'recipient_email' => $recipient,
            ], [
                'issue_id' => $alert->issue_id,
                'user_id' => $user->id,
                'channel' => 'email',
                'status' => 'pending',
            ]);
            if ($log->status === 'sent') {
                continue;
            }

            try {
                Mail::to($recipient)->send(new IssueAlertMail($alert));
                $log->update(['status' => 'sent', 'sent_at' => now()]);
            } catch (Throwable $exception) {
                $log->update(['status' => 'failed']);
                report($exception);
                $failed = true;
            }
        }

        if ($failed) {
            throw new RuntimeException('One or more realtime notification emails failed.');
        }
    }
}
