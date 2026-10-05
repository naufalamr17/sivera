<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueOccurrence;
use App\Models\NotificationLog;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('sivera:cleanup')]
#[Description('Delete SIVERA data beyond its retention policy')]
class CleanupSiveraCommand extends Command
{
    public function handle(): int
    {
        $retention = config('sivera.retention');
        $deleted = [
            'occurrences' => IssueOccurrence::query()->where('created_at', '<', now()->subDays($retention['occurrences_days']))->delete(),
            'api_logs' => ApiRequestLog::query()->where('received_at', '<', now()->subDays($retention['api_logs_days']))->delete(),
            'notification_logs' => NotificationLog::query()->where('created_at', '<', now()->subDays($retention['notification_logs_days']))->delete(),
            'event_receipts' => EventReceipt::query()->where('created_at', '<', now()->subDays($retention['event_receipts_days']))->delete(),
            'issues' => Issue::query()->whereIn('status', ['RESOLVED', 'IGNORED'])
                ->where('last_seen_at', '<', now()->subDays($retention['issues_days']))->delete(),
            'audit_logs' => AuditLog::query()->where('created_at', '<', now()->subDays($retention['audit_logs_days']))->delete(),
        ];

        $this->info('SIVERA cleanup complete: '.json_encode($deleted));

        return self::SUCCESS;
    }
}
