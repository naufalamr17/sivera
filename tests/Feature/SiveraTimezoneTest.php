<?php

use App\Models\AuditLog;
use App\Models\Issue;
use App\Models\IssueComment;
use App\Models\IssueOccurrence;
use App\Models\IssueStatusHistory;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('sivera.timezone', 'Asia/Jakarta');
});

it('formats timestamps in WIB including midnight rollover and missing values', function (?string $value, string $expected) {
    expect(trim(view('layouts.timestamp', ['value' => $value])->render()))->toBe($expected);
})->with([
    ['2026-11-11 18:05:06', '12 Nov 2026 01:05:06 WIB'],
    ['2026-11-11T18:05:06Z', '12 Nov 2026 01:05:06 WIB'],
    ['2026-11-12T01:05:06+07:00', '12 Nov 2026 01:05:06 WIB'],
    [null, '—'],
]);

it('does not mutate model timestamps or change UTC storage when displaying WIB', function () {
    $value = Carbon::parse('2026-11-11 18:05:06', 'UTC');
    expect(trim(view('layouts.timestamp', ['value' => $value])->render()))->toBe('12 Nov 2026 01:05:06 WIB')
        ->and($value->timezoneName)->toBe('UTC')
        ->and($value->format('Y-m-d H:i:s'))->toBe('2026-11-11 18:05:06');

    $log = AuditLog::factory()->create(['created_at' => '2026-11-11 18:05:06']);
    $this->actingAs(User::factory()->admin()->create())->get(route('audit.index'))->assertOk()
        ->assertSee('12 Nov 2026 01:05:06 WIB');
    expect(DB::table('audit_logs')->where('id', $log->id)->value('created_at'))->toBe('2026-11-11 18:05:06')
        ->and(config('app.timezone'))->toBe('UTC');
});

it('uses WIB for issue details occurrences status history and comments', function () {
    $utc = '2026-11-11 18:05:06';
    $issue = Issue::factory()->create(['first_seen_at' => $utc, 'last_seen_at' => $utc]);
    IssueOccurrence::factory()->for($issue)->create(['occurred_at' => $utc]);
    IssueStatusHistory::factory()->create(['issue_id' => $issue->id, 'created_at' => $utc]);
    IssueComment::factory()->create(['issue_id' => $issue->id, 'created_at' => $utc]);

    $response = $this->actingAs(User::factory()->create())->get(route('issues.show', $issue))->assertOk();
    expect(substr_count($response->getContent(), '12 Nov 2026 01:05:06 WIB'))->toBe(5);
});

it('interprets issue date filters as WIB days and queries their UTC boundaries', function () {
    Issue::factory()->create(['last_seen_at' => '2026-11-10 16:59:59']);
    $first = Issue::factory()->create(['last_seen_at' => '2026-11-10 17:00:00']);
    $last = Issue::factory()->create(['last_seen_at' => '2026-11-11 16:59:59']);
    Issue::factory()->create(['last_seen_at' => '2026-11-11 17:00:00']);

    $this->actingAs(User::factory()->create())->get(route('issues.index', ['from' => '2026-11-11', 'to' => '2026-11-11']))
        ->assertOk()->assertViewHas('issues', fn ($issues): bool => $issues->modelKeys() === [$last->id, $first->id]);
});
