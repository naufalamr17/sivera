<?php

use App\Jobs\SendNotificationJob;
use App\Mail\DailyDigestMail;
use App\Models\Application;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueAlert;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Sivera\EventIngestionService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('notifies all users of status changes including resolved and ignored without sending per change emails', function (string $status) {
    Mail::fake();
    $admin = User::factory()->admin()->create();
    $viewer = User::factory()->create();
    $issue = Issue::factory()->create(['status' => 'NEW']);
    $this->actingAs($admin)->post(route('issues.status', $issue), ['status' => $status])->assertRedirect();
    $this->post(route('issues.status', $issue), ['status' => $status])->assertRedirect();
    expect(IssueAlert::count())->toBe(1)->and(IssueAlert::first()->from_status)->toBe('NEW');
    foreach ([$admin, $viewer] as $user) {
        $this->actingAs($user)->getJson(route('notifications.feed'))->assertOk()
            ->assertJsonPath('events.0.type', 'status_changed')->assertJsonPath('events.0.status', $status);
    }
    Mail::assertNothingSent();
})->with(['ACKNOWLEDGED', 'INVESTIGATING', 'RESOLVED', 'IGNORED', 'REOPENED']);

it('always emails yesterday summary and creates one daily follow up only for outstanding issues', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28T00:00:00Z'));
    Mail::fake();
    $admin = User::factory()->admin()->create();
    User::factory()->create();
    User::factory()->create(['is_active' => false]);
    $open = collect(['NEW', 'ACKNOWLEDGED', 'INVESTIGATING', 'REOPENED'])->map(fn ($status) => Issue::factory()->create(['status' => $status, 'last_seen_at' => now()->subWeeks(2)]));
    Issue::factory()->create(['status' => 'RESOLVED']);
    Issue::factory()->create(['status' => 'IGNORED']);

    $this->artisan('sivera:send-digest')->assertSuccessful();
    $this->artisan('sivera:send-digest')->assertSuccessful();
    Mail::assertSentCount(2);
    Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail) use ($open): bool {
        expect($mail->summary['from'])->toBe('2026-10-27')->and($mail->summary['events'])->toBe(0)
            ->and($mail->summary['follow_up_total'])->toBe(4)
            ->and($mail->summary['follow_up_issues']->pluck('id')->sort()->values()->all())->toBe($open->pluck('id')->sort()->values()->all());
        expect($mail->render())->toContain('Daily follow-up', '2026-10-27');

        return true;
    });
    expect(IssueAlert::query()->where('type', 'follow_up')->count())->toBe(4)
        ->and(NotificationLog::count())->toBe(2);
    $this->actingAs($admin)->getJson(route('notifications.feed'))->assertOk()->assertJsonCount(4, 'events');

    $open->first()->update(['status' => 'RESOLVED']);
    $this->getJson(route('notifications.feed', ['after' => 0]))->assertOk()
        ->assertJsonCount(3, 'events')->assertJsonPath('cursor', 4);
    $this->travel(1)->days();
    $this->artisan('sivera:send-digest')->assertSuccessful();
    Mail::assertSentCount(4);
    expect(IssueAlert::query()->where('type', 'follow_up')->count())->toBe(7);
});

it('includes resolved and ignored activity and status changes in yesterday summary but not in follow ups', function () {
    $this->travelTo(CarbonImmutable::parse('2026-10-28T00:00:00Z'));
    Mail::fake();
    $admin = User::factory()->admin()->create();
    foreach (['RESOLVED', 'IGNORED', 'NEW'] as $status) {
        $issue = Issue::factory()->create(['status' => $status, 'severity' => 'ERROR', 'last_seen_at' => now()->subWeeks(3)]);
        $receipt = EventReceipt::factory()->for($issue)->create();
        DB::table('event_receipts')->where('id', $receipt->id)->update(['created_at' => '2026-10-27 06:00:00']);
        if ($status !== 'NEW') {
            IssueAlert::factory()->for($issue)->create(['type' => 'status_changed', 'status' => $status, 'created_at' => '2026-10-27 06:00:00']);
        }
    }
    IssueAlert::factory()->for(Issue::where('status', 'RESOLVED')->first())->create(['created_at' => '2026-10-28 01:00:00']);
    $this->artisan('sivera:send-digest')->assertSuccessful();
    Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail): bool {
        expect($mail->summary['events'])->toBe(3)->and($mail->summary['resolved'])->toBe(1)
            ->and($mail->summary['ignored'])->toBe(1)->and($mail->summary['status_change_total'])->toBe(2)
            ->and($mail->summary['follow_up_total'])->toBe(1)
            ->and($mail->summary['status_changes']->pluck('status')->sort()->values()->all())->toBe(['IGNORED', 'RESOLVED']);
        expect($mail->render())->toContain('Status changes during this day', 'RESOLVED', 'IGNORED');

        return true;
    });
    $this->actingAs($admin)->get(route('issues.index', ['status' => 'ACTIVE']))->assertOk()
        ->assertViewHas('issues', fn ($issues): bool => $issues->total() === 1 && $issues->first()->status === 'NEW');
});

it('keeps follow ups bounded in email and links to all active issues', function () {
    Mail::fake();
    User::factory()->create();
    Issue::factory()->count(25)->create(['status' => 'NEW']);
    $this->artisan('sivera:send-digest')->assertSuccessful();
    Mail::assertSent(DailyDigestMail::class, function (DailyDigestMail $mail): bool {
        expect($mail->summary['follow_up_total'])->toBe(25)->and($mail->summary['follow_up_issues'])->toHaveCount(20);
        expect($mail->render())->toContain('status=ACTIVE', 'Showing the first 20 issues');

        return true;
    });
});

it('does not deliver to disabled or changed user addresses', function (bool $active, string $email) {
    Mail::fake();
    $user = User::factory()->create(['is_active' => $active, 'email' => $email]);
    $log = NotificationLog::create([
        'user_id' => $user->id, 'channel' => 'email', 'status' => 'pending',
        'digest_date' => '2026-10-27', 'digest_recipient' => 'original@example.test',
    ]);
    SendNotificationJob::dispatchSync($log->id);
    Mail::assertNothingSent();
})->with([[false, 'original@example.test'], [true, 'changed@example.test']]);

it('continues notifying repeated events for an ignored issue without creating duplicate event alerts', function () {
    $application = Application::factory()->create();
    $event = [
        'event_id' => (string) Str::ulid(), 'application' => $application->slug, 'environment' => 'production',
        'exception' => ['class' => 'RuntimeException', 'message' => 'Test', 'file' => 'app/Test.php', 'line' => 1],
        'occurred_at' => now()->toIso8601String(),
    ];
    $ingestion = app(EventIngestionService::class);
    $ingestion->ingest($application, $event, null);
    Issue::first()->update(['status' => 'IGNORED']);
    $event['event_id'] = (string) Str::ulid();
    $ingestion->ingest($application, $event, null);
    $ingestion->ingest($application, $event, null);
    expect(IssueAlert::count())->toBe(2);
    $this->actingAs(User::factory()->create())->getJson(route('notifications.feed'))
        ->assertOk()->assertJsonCount(2, 'events')->assertJsonPath('events.0.status', 'IGNORED');
});

it('schedules the daily summary for 0700 WIB', function () {
    $task = collect(app(Schedule::class)->events())->first(fn ($event) => str_contains($event->command ?? '', 'sivera:send-digest'));
    expect($task)->not->toBeNull()->and($task->expression)->toBe('0 7 * * *')->and($task->timezone)->toBe('Asia/Jakarta');
});

it('uses the full activity watermark when backfilled records are older than the initial visible batch', function () {
    $issue = Issue::factory()->create();
    IssueAlert::factory()->for($issue)->count(25)->create(['created_at' => now()]);
    $old = IssueAlert::factory()->for($issue)->create(['created_at' => now()->subDays(3)]);
    $this->actingAs(User::factory()->create())->getJson(route('notifications.feed'))->assertOk()
        ->assertJsonCount(20, 'events')->assertJsonPath('cursor', $old->id)
        ->assertJsonMissing(['id' => $old->id]);

    $new = IssueAlert::factory()->for($issue)->create();
    $this->getJson(route('notifications.feed', ['after' => $old->id]))->assertOk()
        ->assertJsonCount(1, 'events')->assertJsonPath('events.0.id', $new->id)->assertJsonPath('cursor', $new->id);
});
