<?php

use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('defaults the page and live summary to the last seven local calendar days including today', function () {
    config()->set('sivera.timezone', 'Asia/Jakarta');
    $this->travelTo(CarbonImmutable::parse('2026-10-26T18:00:00Z'));
    $issue = Issue::factory()->create();
    foreach (['2026-10-20 16:59:59', '2026-10-20 17:00:00', '2026-10-27 16:59:59', '2026-10-27 17:00:00'] as $time) {
        $receipt = EventReceipt::factory()->for($issue)->create();
        DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => $time]);
    }

    $this->actingAs(User::factory()->create())->get(route('dashboard'))->assertOk()
        ->assertViewHas('summary', fn (array $summary): bool => $summary['from'] === '2026-10-21'
            && $summary['to'] === '2026-10-27' && $summary['events'] === 2 && count($summary['activity']) === 7);
    $this->getJson(route('dashboard.live-summary'))->assertOk()
        ->assertJsonPath('from', '2026-10-21')->assertJsonPath('to', '2026-10-27')
        ->assertJsonPath('events', 2)->assertJsonCount(7, 'activity');
});

it('preserves single date filters when only one boundary is supplied', function (array $filters, string $date) {
    $this->actingAs(User::factory()->create())->getJson(route('dashboard.live-summary', $filters))->assertOk()
        ->assertJsonPath('from', $date)->assertJsonPath('to', $date)->assertJsonCount(24, 'activity');
})->with([
    [['from' => '2026-10-25'], '2026-10-25'],
    [['to' => '2026-10-25'], '2026-10-25'],
]);

it('filters counts charts and rankings by receipt date rather than lifetime issue totals', function () {
    config()->set('sivera.timezone', 'Asia/Jakarta');
    $app = Application::factory()->create(['name' => 'Sasehat']);
    $otherApp = Application::factory()->create(['name' => 'Other app']);
    $issue = Issue::factory()->for($app)->create(['severity' => 'CRITICAL', 'occurrence_count' => 999, 'message' => 'Connection refused']);
    $other = Issue::factory()->for($otherApp)->create(['severity' => 'ERROR', 'occurrence_count' => 1000]);
    $old = Issue::factory()->create(['severity' => 'CRITICAL']);
    foreach ([[$issue, '2026-10-25 17:00:00'], [$issue, '2026-10-26 16:59:59'], [$other, '2026-10-26 10:00:00'], [$old, '2026-10-26 17:00:00'], [$old, '2026-10-25 16:59:59']] as [$model, $time]) {
        $receipt = EventReceipt::factory()->for($model)->create();
        DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => $time]);
    }
    $response = $this->actingAs(User::factory()->create())->getJson(route('dashboard.live-summary', ['from' => '2026-10-26', 'to' => '2026-10-26']))
        ->assertOk()->assertJsonPath('events', 3)->assertJsonPath('open', 2)->assertJsonPath('critical', 1)
        ->assertJsonPath('ranking.0.name', 'Sasehat')->assertJsonPath('top_errors.0.period_count', 2)
        ->assertJsonPath('top_errors.0.message', 'Connection refused')->assertJsonCount(24, 'activity');
    expect(array_sum(array_column($response->json('activity'), 'count')))->toBe(3);
    $this->get(route('dashboard', ['from' => '2026-10-26', 'to' => '2026-10-26']))->assertOk()->assertSee('dashboard-data');
});

it('counts all events but ranks only error and critical severity and uses daily buckets for date ranges', function () {
    $info = Issue::factory()->create(['severity' => 'INFO', 'status' => 'RESOLVED']);
    $receipt = EventReceipt::factory()->for($info)->create();
    DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => '2026-10-25 20:00:00']);
    $this->actingAs(User::factory()->create())->getJson(route('dashboard.live-summary', ['from' => '2026-10-26', 'to' => '2026-10-27']))
        ->assertOk()->assertJsonPath('events', 1)->assertJsonPath('resolved', 1)->assertJsonCount(0, 'ranking')
        ->assertJsonCount(0, 'top_errors')->assertJsonCount(2, 'activity');
});

it('validates dashboard date ranges on page and polling requests', function (array $filters) {
    $this->actingAs(User::factory()->create())->getJson(route('dashboard.live-summary', $filters))->assertUnprocessable();
    $this->getJson(route('dashboard', $filters))->assertUnprocessable();
})->with([
    [['from' => 'invalid']],
    [['from' => '2026-02-30']],
    [['from' => '2026-10-27', 'to' => '2026-10-26']],
    [['from' => '2025-01-01', 'to' => '2026-10-27']],
]);
