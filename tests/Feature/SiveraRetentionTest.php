<?php

use App\Models\ApiRequestLog;
use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueOccurrence;
use App\Services\Sivera\EventIngestionService;
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

it('removes expired detail and security logs on the scheduled command', function () {
    $issue = Issue::factory()->create();
    $occurrence = IssueOccurrence::factory()->for($issue)->create();
    $receipt = EventReceipt::factory()->for($issue)->create();
    $apiLog = ApiRequestLog::factory()->create();
    DB::table('issue_occurrences')->where('id', $occurrence->id)->update(['created_at' => now()->subDays(31)]);
    DB::table('api_request_logs')->where('id', $apiLog->id)->update(['received_at' => now()->subDays(31)]);
    DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => now()->subDays(31)]);

    expect(Artisan::call('sivera:cleanup'))->toBe(0)
        ->and(IssueOccurrence::count())->toBe(0)
        ->and(ApiRequestLog::count())->toBe(0)
        ->and(EventReceipt::count())->toBe(1)
        ->and(Issue::count())->toBe(1);
});
