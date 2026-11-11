<?php

use App\Models\AuditLog;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\User;
use App\Services\Sivera\DisplayTime;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('sivera.timezone', 'Asia/Jakarta');
    app()->instance(DisplayTime::class, new class extends DisplayTime
    {
        public function databaseOffsetSeconds(): int
        {
            return 25200;
        }
    });
});

it('distinguishes application UTC timestamps from database WIB wall time without changing either', function () {
    $clock = app(DisplayTime::class);
    $application = CarbonImmutable::parse('2026-11-11 07:31:05', 'UTC');
    $database = CarbonImmutable::parse('2026-11-11 14:31:05', 'UTC');

    expect($clock->format($application))->toBe('11 Nov 2026 14:31:05 WIB')
        ->and($clock->format($database, true))->toBe('11 Nov 2026 14:31:05 WIB')
        ->and($clock->format(null))->toBe('—')
        ->and($application->format('H:i:s'))->toBe('07:31:05')
        ->and($database->format('H:i:s'))->toBe('14:31:05');
});

it('corrects only the observed legacy reporter shifts corroborated by the event ULID', function (string $value, string $eventId, string $expected) {
    expect(app(DisplayTime::class)->format($value, eventId: $eventId))->toBe($expected);
})->with([
    ['2026-11-11 14:31:05', '01M74R8YYWCZM57C92XP4RZYHW', '11 Nov 2026 14:31:05 WIB'],
    ['2026-11-11 07:31:05', '01M74R8YYWCZM57C92XP4RZYHW', '11 Nov 2026 14:31:05 WIB'],
    ['2026-11-10 12:30:00', '01M71VGP5R2QRE2T6BREDWKF4G', '10 Nov 2026 11:30:00 WIB'],
    ['2026-11-10 05:30:00', '01M71VGP5R2QRE2T6BREDWKF4G', '10 Nov 2026 11:30:00 WIB'],
    ['2026-11-11 18:05:06', '01M74R8YYWCZM57C92XP4RZYHW', '12 Nov 2026 01:05:06 WIB'],
    ['2026-11-11 07:31:05', 'non-ulid-event', '11 Nov 2026 14:31:05 WIB'],
]);

it('displays database generated audit times without rewriting the stored value', function () {
    $admin = User::factory()->admin()->create();
    $log = AuditLog::factory()->create(['created_at' => '2026-11-11 14:31:05']);

    $this->actingAs($admin)->get(route('audit.index'))->assertOk()->assertSee('11 Nov 2026 14:31:05 WIB');
    expect(DB::table('audit_logs')->where('id', $log->id)->value('created_at'))->toBe('2026-11-11 14:31:05');
});

it('uses database wall time for dashboard reads and sends canonical UTC timestamps to the browser', function () {
    $issue = Issue::factory()->create();
    EventReceipt::factory()->for($issue)->create(['created_at' => '2026-11-11 14:31:05']);

    $response = $this->actingAs(User::factory()->create())
        ->getJson(route('dashboard.live-summary', ['from' => '2026-11-11', 'to' => '2026-11-11']))
        ->assertOk()->assertJsonPath('events', 1)->assertJsonPath('latest.0.period_last_seen', '2026-11-11T07:31:05+00:00');
    expect($response->json('activity.14'))->toBe(['label' => '14:00', 'count' => 1]);
});
