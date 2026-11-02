<?php

use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueOccurrence;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\DatabaseMigrations;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

uses(DatabaseMigrations::class);

it('backfills application ownership without changing historical events and supports a safe round trip', function () {
    $migration = require database_path('migrations/2026_10_28_073444_scope_event_ids_to_applications.php');
    $migration->down();
    $issue = Issue::factory()->create();
    $receiptId = DB::table('event_receipts')->insertGetId([
        'issue_id' => $issue->id, 'event_id' => 'historical-event', 'occurred_at' => now(),
    ]);
    $occurrenceId = DB::table('issue_occurrences')->insertGetId([
        'issue_id' => $issue->id, 'event_id' => 'historical-event', 'occurred_at' => now(),
        'stack_trace_json' => '[]', 'request_metadata_json' => '{"path":"/orders"}',
    ]);
    $alertId = DB::table('issue_alerts')->insertGetId([
        'issue_id' => $issue->id, 'event_receipt_id' => $receiptId, 'type' => 'event',
    ]);
    $migration->up();
    $migration->up();

    expect(EventReceipt::findOrFail($receiptId)->application_id)->toBe($issue->application_id)
        ->and(IssueOccurrence::findOrFail($occurrenceId)->application_id)->toBe($issue->application_id)
        ->and(IssueOccurrence::findOrFail($occurrenceId)->request_metadata_json)->toBe(['path' => '/orders'])
        ->and(DB::table('issue_alerts')->where('id', $alertId)->exists())->toBeTrue();
    $migration->down();
    expect(Schema::hasColumn('event_receipts', 'application_id'))->toBeFalse()
        ->and(EventReceipt::findOrFail($receiptId)->event_id)->toBe('historical-event')
        ->and(IssueOccurrence::findOrFail($occurrenceId)->event_id)->toBe('historical-event');
    $migration->up();
});

it('enforces uniqueness within each application at database level', function (string $tableName) {
    $first = Issue::factory()->create();
    $second = Issue::factory()->for($first->application)->create();
    $model = $tableName === 'event_receipts' ? EventReceipt::class : IssueOccurrence::class;
    $model::factory()->for($first)->create(['event_id' => 'same-app-event']);

    expect(fn () => $model::factory()->for($second)->create(['event_id' => 'same-app-event']))->toThrow(QueryException::class);
})->with(['event_receipts', 'issue_occurrences']);

it('blocks rollback before changing either table when global ids would collide', function (string $tableName) {
    $model = $tableName === 'event_receipts' ? EventReceipt::class : IssueOccurrence::class;
    $model::factory()->create(['event_id' => 'shared-event']);
    $model::factory()->create(['event_id' => 'shared-event']);
    $migration = require database_path('migrations/2026_10_28_073444_scope_event_ids_to_applications.php');

    expect(fn () => $migration->down())->toThrow(RuntimeException::class, 'Cannot restore global event IDs');
    expect(Schema::hasColumn('event_receipts', 'application_id'))->toBeTrue()
        ->and(Schema::hasColumn('issue_occurrences', 'application_id'))->toBeTrue()
        ->and(DB::table($tableName)->count())->toBe(2);
    DB::table($tableName)->where('id', DB::table($tableName)->max('id'))->delete();
})->with(['event_receipts', 'issue_occurrences']);
