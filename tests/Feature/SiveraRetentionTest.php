<?php

use App\Models\ApiRequestLog;
use App\Models\Application;
use App\Models\AuditLog;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueAlert;
use App\Models\IssueOccurrence;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Services\Sivera\EventIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('samples detail after 100 events while preserving counts and idempotency', function () {
    $application = Application::factory()->create();
    $ingestion = app(EventIngestionService::class);
    $last = null;

    for ($index = 0; $index < 101; $index++) {
        $last = [
            'event_id' => (string) Str::ulid(),
            'application' => $application->slug,
            'environment' => 'production',
            'exception' => ['class' => 'RuntimeException', 'message' => 'Test', 'file' => 'app/Service.php', 'line' => 12],
            'request' => ['route' => 'example'],
            'occurred_at' => now()->toIso8601String(),
        ];
        $ingestion->ingest($application, $last, '192.0.2.10');
    }

    expect(Issue::first()->occurrence_count)->toBe(101)
        ->and(EventReceipt::count())->toBe(101)
        ->and(IssueOccurrence::count())->toBe(100)
        ->and($ingestion->ingest($application, $last, '192.0.2.10'))->toBeTrue()
        ->and(Issue::first()->occurrence_count)->toBe(101);
});

it('retains every data category for three calendar years including leap day boundaries', function () {
    $this->travelTo(CarbonImmutable::parse('2028-02-29T12:00:00Z'));
    expect(config('sivera.retention_years'))->toBe(3);
    $issue = Issue::factory()->create();
    $rule = NotificationRule::factory()->create();
    foreach (['2025-02-28 11:59:59', '2025-02-28 12:00:00', '2026-02-28 12:00:00', '2028-01-01 12:00:00'] as $time) {
        $occurrence = IssueOccurrence::factory()->for($issue)->create();
        $receipt = EventReceipt::factory()->for($issue)->create();
        $apiLog = ApiRequestLog::factory()->create();
        $notification = NotificationLog::factory()->create(['issue_id' => $issue->id, 'notification_rule_id' => $rule->id]);
        $audit = AuditLog::factory()->create();
        $alert = IssueAlert::factory()->for($issue)->create();
        foreach (['issue_occurrences' => $occurrence, 'event_receipts' => $receipt, 'notification_logs' => $notification, 'audit_logs' => $audit, 'issue_alerts' => $alert] as $table => $record) {
            DB::table($table)->where('id', $record->id)->update(['created_at' => $time]);
        }
        DB::table('api_request_logs')->where('id', $apiLog->id)->update(['received_at' => $time]);
    }

    expect(Artisan::call('sivera:cleanup'))->toBe(0)
        ->and(IssueOccurrence::count())->toBe(3)
        ->and(ApiRequestLog::count())->toBe(3)
        ->and(EventReceipt::count())->toBe(3)
        ->and(NotificationLog::count())->toBe(3)
        ->and(AuditLog::count())->toBe(3)
        ->and(IssueAlert::count())->toBe(3)
        ->and(Issue::count())->toBe(1);
});

it('cleans issues of every status only after their last event exceeds three years', function (string $status) {
    $this->travelTo(CarbonImmutable::parse('2026-10-27T12:00:00Z'));
    $expired = Issue::factory()->create(['status' => $status, 'last_seen_at' => '2023-10-27 11:59:59']);
    $boundary = Issue::factory()->create(['status' => $status, 'last_seen_at' => '2023-10-27 12:00:00']);
    $active = Issue::factory()->create(['status' => $status, 'first_seen_at' => '2020-01-01', 'last_seen_at' => now()]);
    $occurrence = IssueOccurrence::factory()->for($expired)->create();
    DB::table('issue_occurrences')->where('id', $occurrence->id)->update(['created_at' => '2023-10-27 11:59:59']);

    $this->artisan('sivera:cleanup')->assertSuccessful();
    expect($expired->fresh())->toBeNull()->and($occurrence->fresh())->toBeNull()
        ->and($boundary->fresh())->not->toBeNull()->and($active->fresh())->not->toBeNull();
})->with(['NEW', 'ACKNOWLEDGED', 'INVESTIGATING', 'RESOLVED', 'IGNORED', 'REOPENED']);

it('does not let missing or shorter cached configuration purge data before three years', function (?int $years) {
    config()->set('sivera.retention_years', $years);
    $issue = Issue::factory()->create(['last_seen_at' => now()->subYears(2)]);
    $occurrence = IssueOccurrence::factory()->for($issue)->create();
    DB::table('issue_occurrences')->where('id', $occurrence->id)->update(['created_at' => now()->subYears(2)]);

    $this->artisan('sivera:cleanup')->assertSuccessful();
    expect($issue->fresh())->not->toBeNull()->and($occurrence->fresh())->not->toBeNull();
})->with([null, 0, 1, 2]);
