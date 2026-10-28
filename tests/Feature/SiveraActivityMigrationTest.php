<?php

use App\Models\EventReceipt;
use App\Models\IssueAlert;
use App\Models\IssueStatusHistory;
use App\Models\NotificationLog;
use App\Models\User;
use Illuminate\Foundation\Testing\DatabaseMigrations;

uses(DatabaseMigrations::class);

it('backfills existing receipt notifications without changing source data', function () {
    $migration = require database_path('migrations/2026_10_28_020823_create_issue_alerts_table.php');
    $migration->down();
    $receipts = EventReceipt::factory()->count(3)->create();
    $history = IssueStatusHistory::factory()->create(['from_status' => 'NEW', 'to_status' => 'RESOLVED']);
    $log = NotificationLog::factory()->create(['status' => 'sent']);
    $migration->up();
    $migration->up();

    expect(IssueAlert::query()->where('type', 'event')->count())->toBe(3)
        ->and(IssueAlert::query()->where('type', 'event')->orderBy('event_receipt_id')->pluck('event_receipt_id')->all())->toBe($receipts->pluck('id')->all())
        ->and(EventReceipt::count())->toBe(3)->and($log->fresh()->status)->toBe('sent');
    expect(IssueAlert::query()->where('type', 'status_changed')->count())->toBe(1)
        ->and(IssueAlert::query()->where('status_history_id', $history->id)->value('status'))->toBe('RESOLVED');
});

it('preserves delivery history when a user is removed', function () {
    $user = User::factory()->create();
    $log = NotificationLog::create(['user_id' => $user->id, 'channel' => 'email', 'status' => 'sent', 'digest_date' => '2026-10-27', 'digest_recipient' => $user->email]);
    $user->delete();
    expect($log->fresh())->not->toBeNull()->and($log->fresh()->user_id)->toBeNull()->and($log->fresh()->status)->toBe('sent');
});
