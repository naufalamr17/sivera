<?php

use App\Jobs\SendNotificationJob;
use App\Mail\DailyDigestMail;
use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueAlert;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\Sivera\EventIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('ingests bursts without sending per event email and keeps live activity available', function () {
    Mail::fake();
    Bus::fake();
    $application = Application::factory()->create();
    NotificationRule::factory()->create();
    for ($index = 0; $index < 50; $index++) {
        app(EventIngestionService::class)->ingest($application, [
            'event_id' => (string) Str::ulid(),
            'application' => $application->slug,
            'environment' => 'production',
            'severity' => 'critical',
            'exception' => ['class' => 'RuntimeException', 'message' => 'Example error', 'file' => 'app/Service.php', 'line' => 10],
            'request' => ['method' => 'GET', 'route' => 'example', 'path' => '/example'],
            'occurred_at' => now()->toIso8601String(),
        ], '192.0.2.10');
    }
    expect(Issue::count())->toBe(1)->and(Issue::first()->occurrence_count)->toBe(50)
        ->and(EventReceipt::count())->toBe(50)->and(NotificationLog::count())->toBe(0);
    Mail::assertNothingSent();
    Bus::assertNothingDispatched();
    $this->actingAs(User::factory()->create())->getJson(route('notifications.feed'))
        ->assertOk()->assertJsonCount(20, 'events')->assertJsonPath('cursor', 50);
});

it('sends one previous local day summary to every active user and prevents duplicate delivery', function () {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(1, 0));
    config()->set('sivera.timezone', 'Asia/Jakarta');
    Mail::fake();
    User::factory()->admin()->create(['email' => 'alerts@example.test']);
    User::factory()->create(['email' => 'viewer@example.test']);
    User::factory()->create(['email' => 'ALERTS@example.test']);
    User::factory()->create(['is_active' => false]);
    NotificationRule::factory()->create(['channel' => 'qontak', 'target' => '6281234567890']);
    $issue = Issue::factory()->create(['severity' => 'CRITICAL', 'message' => 'Database unavailable']);
    foreach (['2026-10-25 17:00:00', '2026-10-26 16:59:59', '2026-10-25 16:59:59', '2026-10-26 17:00:00'] as $time) {
        $receipt = EventReceipt::factory()->for($issue)->create();
        DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => $time]);
    }
    $this->artisan('sivera:send-digest')->assertSuccessful();
    $this->artisan('sivera:send-digest')->assertSuccessful();
    Mail::assertSentCount(2);
    Mail::assertSent(DailyDigestMail::class, fn ($mail) => $mail->hasTo('viewer@example.test'));
    Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail): bool {
        expect($mail->summary['from'])->toBe('2026-10-26')->and($mail->summary['events'])->toBe(2);
        expect($mail->render())->toContain('Database unavailable', '2026-10-26', 'Inspect issue');

        return $mail->hasTo('alerts@example.test');
    });
    expect(NotificationLog::count())->toBe(2)->and(NotificationLog::first()->status)->toBe('sent')
        ->and(NotificationLog::first()->issue_id)->toBeNull();
});

it('sends a clear zero event summary and skips legacy queued notifications', function () {
    Mail::fake();
    $rule = NotificationRule::factory()->create();
    $legacy = NotificationLog::factory()->create(['notification_rule_id' => $rule->id]);
    SendNotificationJob::dispatchSync($legacy->id);
    Mail::assertNothingSent();
    User::factory()->create();
    $this->artisan('sivera:send-digest', ['--date' => now()->subDays(2)->toDateString()])->assertSuccessful();
    Mail::assertSent(DailyDigestMail::class, fn ($mail) => $mail->summary['events'] === 0);
});

it('records a failed delivery and retries it without resending successful recipients', function () {
    User::factory()->create(['email' => 'first@example.test']);
    User::factory()->create(['email' => 'second@example.test']);
    Mail::shouldReceive('to')->once()->with('first@example.test')->andThrow(new RuntimeException('SMTP unavailable'));
    Mail::shouldReceive('to')->once()->with('second@example.test')->andReturnSelf();
    Mail::shouldReceive('send')->once();
    Mail::shouldReceive('getDefaultDriver')->andReturn('array');
    $date = now(config('sivera.timezone'))->subDays(2)->toDateString();
    $this->artisan('sivera:send-digest', ['--date' => $date])->assertFailed();
    expect(NotificationLog::query()->where('status', 'failed')->count())->toBe(1)
        ->and(NotificationLog::query()->where('status', 'sent')->count())->toBe(1);
    Mail::fake();
    $this->artisan('sivera:send-digest', ['--date' => $date])->assertSuccessful();
    Mail::assertSentCount(1);
    Mail::assertSent(DailyDigestMail::class, fn ($mail) => $mail->hasTo('first@example.test'));
    expect(NotificationLog::query()->where('status', 'sent')->count())->toBe(2);
});

it('rejects invalid and unfinished digest days', function (string $date) {
    $this->travelTo(now()->setDate(2026, 10, 27)->setTime(1, 0));
    Mail::fake();
    $this->artisan('sivera:send-digest', ['--date' => $date])->assertFailed();
    Mail::assertNothingSent();
})->with(['not-a-date', '2026-02-30', '2026-10-27', '2026-10-28']);

it('shows active users as recipients to admins and retires manual recipient configuration', function () {
    $viewer = User::factory()->create(['email' => 'viewer@example.test']);
    User::factory()->create(['email' => 'disabled@example.test', 'is_active' => false]);
    $this->actingAs($viewer)->get(route('notifications.index'))->assertForbidden();
    $this->actingAs(User::factory()->admin()->create())->get(route('notifications.index'))->assertOk()
        ->assertSee('viewer@example.test')->assertDontSee('disabled@example.test')->assertDontSee('Add recipient');
    $this->get('/settings/notifications/create')->assertNotFound();
    $this->post('/settings/notifications', ['target' => 'outside@example.test'])->assertStatus(405);
});

it('keeps severity and status changes independent of email delivery', function () {
    Mail::fake();
    NotificationRule::factory()->create();
    $issue = Issue::factory()->create(['severity' => 'ERROR', 'status' => 'RESOLVED']);
    $this->actingAs(User::factory()->admin()->create())->post(route('issues.severity', $issue), ['severity' => 'CRITICAL'])->assertRedirect();
    $this->post(route('issues.status', $issue), ['status' => 'REOPENED'])->assertRedirect();
    expect($issue->fresh()->status)->toBe('REOPENED')->and(NotificationLog::count())->toBe(0);
    Mail::assertNothingSent();
});

it('pages live activity without losing events during a burst and protects the endpoint', function () {
    $this->getJson(route('notifications.feed'))->assertUnauthorized();
    $user = User::factory()->create();
    $issue = Issue::factory()->create(['message' => '<script>alert(1)</script>']);
    foreach (EventReceipt::factory()->for($issue)->count(55)->create() as $receipt) {
        IssueAlert::factory()->for($issue)->create(['type' => 'event', 'event_receipt_id' => $receipt->id]);
    }
    $first = $this->actingAs($user)->getJson(route('notifications.feed', ['after' => 0]))->assertOk()
        ->assertJsonCount(50, 'events')->assertJsonPath('has_more', true)->assertJsonPath('cursor', 50);
    expect($first->json('events.0.received_at'))->toMatch('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}\+00:00$/');
    $this->getJson(route('notifications.feed', ['after' => $first->json('cursor')]))->assertOk()
        ->assertJsonCount(5, 'events')->assertJsonPath('cursor', 55)->assertJsonPath('has_more', false);
    $this->getJson(route('notifications.feed', ['after' => 55]))->assertOk()->assertJsonCount(0, 'events')->assertJsonPath('cursor', 55);
    $this->getJson(route('notifications.feed', ['after' => -1]))->assertUnprocessable();
    $user->update(['is_active' => false]);
    $this->getJson(route('notifications.feed'))->assertForbidden();
});
