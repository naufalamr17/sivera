<?php

namespace App\Console\Commands;

use App\Models\ApiRequestLog;
use App\Models\AuditLog;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueAlert;
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
        $years = max(3, (int) config('sivera.retention_years', 3));
        $cutoff = now()->subYearsNoOverflow($years);
        $deleted = [
            'issue_alerts' => IssueAlert::query()->where('created_at', '<', $cutoff)->delete(),
            'occurrences' => IssueOccurrence::query()->where('created_at', '<', $cutoff)->delete(),
            'api_logs' => ApiRequestLog::query()->where('received_at', '<', $cutoff)->delete(),
            'notification_logs' => NotificationLog::query()->where('created_at', '<', $cutoff)->delete(),
            'event_receipts' => EventReceipt::query()->where('created_at', '<', $cutoff)->delete(),
            'issues' => Issue::query()->where('last_seen_at', '<', $cutoff)->delete(),
            'audit_logs' => AuditLog::query()->where('created_at', '<', $cutoff)->delete(),
        ];

        $this->info('SIVERA cleanup complete: '.json_encode($deleted));

        return self::SUCCESS;
    }
}
