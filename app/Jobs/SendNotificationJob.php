<?php

namespace App\Jobs;

use App\Mail\IssueAlertMail;
use App\Models\NotificationLog;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Throwable;

class SendNotificationJob implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    public function __construct(public int $notificationLogId) {}

    public function handle(): void
    {
        $log = NotificationLog::query()->with('issue.application', 'rule')->find($this->notificationLogId);

        if (! $log || $log->status !== 'pending' || ! $log->rule->is_active) {
            return;
        }

        $issue = $log->issue;
        $rule = $log->rule;
        $message = implode(' | ', [
            'SIVERA '.$issue->severity.' alert',
            'Application: '.$issue->application->name,
            'Error: '.$issue->exception_class,
            'Location: '.($issue->file ?? 'unknown').':'.($issue->line ?? '—'),
            'Occurrences: '.$issue->occurrence_count,
            'Status: '.$issue->status,
            'Issue: '.route('issues.show', $issue),
        ]);

        try {
            if ($rule->channel === 'email') {
                Mail::to($rule->target)->send(new IssueAlertMail($message, $issue->severity));
                $responseCode = null;
            } else {
                if (! config('services.qontak.token') || ! config('services.qontak.channel_id')
                    || ! config('services.qontak.template_id')) {
                    throw new \RuntimeException('Qontak is not configured');
                }

                $response = Http::withToken(config('services.qontak.token'))
                    ->acceptJson()
                    ->timeout(config('services.qontak.timeout', 5))
                    ->post(rtrim(config('services.qontak.base_url'), '/').'/api/open/v1/broadcasts/whatsapp/direct', [
                        'to_name' => $rule->target_name ?: 'SIVERA Admin',
                        'to_number' => $rule->target,
                        'message_template_id' => config('services.qontak.template_id'),
                        'channel_integration_id' => config('services.qontak.channel_id'),
                        'language' => ['code' => config('services.qontak.language', 'id')],
                        'parameters' => ['body' => [
                            ['key' => '1', 'value' => 'name', 'value_text' => $rule->target_name ?: 'SIVERA Admin'],
                            ['key' => '2', 'value' => 'content', 'value_text' => preg_replace('/\s+/', ' ', $message)],
                        ]],
                    ]);
                $responseCode = $response->status();

                if (! $response->successful()) {
                    throw new \RuntimeException('Qontak rejected notification');
                }
            }

            $log->forceFill(['status' => 'sent', 'response_code' => $responseCode, 'sent_at' => now()])->save();
        } catch (Throwable) {
            $log->forceFill(['status' => 'failed', 'response_code' => $responseCode ?? null])->save();
        }
    }
}
