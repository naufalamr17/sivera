<?php

use App\Jobs\SendIssueAlertEmailJob;
use App\Mail\IssueAlertMail;
use App\Models\Application;
use App\Models\Issue;
use App\Models\IssueAlert;
use App\Models\NotificationLog;
use App\Models\User;
use App\Services\Sivera\EventIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('queue.default', 'database');
    Mail::fake();
});

it('queues each accepted event and automatic reopen once without sending email in ingestion', function () {
    Queue::fake();
    User::factory()->create();
    $application = Application::factory()->create();
    $event = [
        'event_id' => (string) Str::ulid(),
        'exception' => ['class' => 'RuntimeException', 'message' => 'Example error', 'file' => 'app/Test.php', 'line' => 1],
        'occurred_at' => now()->toIso8601String(),
    ];
    $ingestion = app(EventIngestionService::class);
    $ingestion->ingest($application, $event, null);
    $ingestion->ingest($application, $event, null);
    Issue::first()->update(['status' => 'RESOLVED']);
    $event['event_id'] = (string) Str::ulid();
    $ingestion->ingest($application, $event, null);

    expect(IssueAlert::count())->toBe(3)->and(NotificationLog::count())->toBe(0);
    Queue::assertPushed(SendIssueAlertEmailJob::class, 3);
    foreach (IssueAlert::all() as $alert) {
        Queue::assertPushed(SendIssueAlertEmailJob::class, fn ($job) => $job->issueAlertId === $alert->id && $job->afterCommit === true);
    }
    Mail::assertNothingSent();
});

it('only queues committed activity and discards rolled back activity', function () {
    $issue = Issue::factory()->create();
    DB::beginTransaction();
    IssueAlert::factory()->for($issue)->create();
    expect(DB::table('jobs')->count())->toBe(0);
    DB::rollBack();
    expect(DB::table('jobs')->count())->toBe(0)->and(IssueAlert::count())->toBe(0);

    DB::beginTransaction();
    IssueAlert::factory()->for($issue)->create();
    expect(DB::table('jobs')->count())->toBe(0);
    DB::commit();
    expect(DB::table('jobs')->count())->toBe(1);
    Mail::assertNothingSent();
});

it('emails all active users once per normalized address and logs each delivery', function () {
    User::factory()->admin()->create(['email' => 'alerts@example.test']);
    User::factory()->create(['email' => 'ALERTS@example.test']);
    User::factory()->create(['email' => 'viewer@example.test']);
    User::factory()->create(['is_active' => false, 'email' => 'disabled@example.test']);
    $issue = Issue::factory()->create(['message' => '<script>alert(1)</script>', 'status' => 'IGNORED']);
    $alert = IssueAlert::factory()->for($issue)->create(['type' => 'event']);
    $job = new SendIssueAlertEmailJob($alert->id);
    $job->handle();
    $job->handle();

    Mail::assertSentCount(2);
    Mail::assertSent(IssueAlertMail::class, fn ($mail) => $mail->hasTo('viewer@example.test'));
    Mail::assertSent(IssueAlertMail::class, function (IssueAlertMail $mail) use ($issue): bool {
        $mail->assertSeeInHtml('<script>alert(1)</script>');
        $mail->assertSeeInText('An error event was received.');
        $mail->assertSeeInText('Status: IGNORED');
        $mail->assertSeeInText(route('issues.show', $issue));
        expect($mail->render())->not->toContain('<script>');

        return $mail->hasTo('alerts@example.test');
    });
    expect(NotificationLog::count())->toBe(2)
        ->and(NotificationLog::where('status', 'sent')->count())->toBe(2)
        ->and(NotificationLog::whereNotNull('sent_at')->count())->toBe(2)
        ->and(NotificationLog::first()->digest_date)->toBeNull();
    $this->actingAs(User::first())->get(route('notifications.index'))->assertOk()
        ->assertSee('Realtime issue emails')->assertSee('alerts@example.test');
});

it('emails status changes including closed statuses and preserves the activity status', function (string $status) {
    $admin = User::factory()->admin()->create();
    $viewer = User::factory()->create();
    $issue = Issue::factory()->create(['status' => 'NEW']);
    $this->actingAs($admin)->post(route('issues.status', $issue), ['status' => $status])->assertRedirect();
    $this->post(route('issues.status', $issue), ['status' => $status])->assertRedirect();
    expect(DB::table('jobs')->count())->toBe(1);
    $issue->update(['status' => 'INVESTIGATING']);
    (new SendIssueAlertEmailJob(IssueAlert::first()->id))->handle();

    Mail::assertSentCount(2);
    Mail::assertSent(IssueAlertMail::class, function (IssueAlertMail $mail) use ($status, $viewer): bool {
        $mail->assertSeeInText('Status changed from NEW to '.$status.'.');
        expect($mail->envelope()->subject)->toContain('Status changed to '.$status);

        return $mail->hasTo($viewer->email);
    });
})->with(['ACKNOWLEDGED', 'INVESTIGATING', 'RESOLVED', 'IGNORED', 'REOPENED']);

it('uses current recipients when processing queued activity', function () {
    $disabled = User::factory()->create(['email' => 'disabled@example.test']);
    $changed = User::factory()->create(['email' => 'old@example.test']);
    $alert = IssueAlert::factory()->create();
    $disabled->update(['is_active' => false]);
    $changed->update(['email' => 'current@example.test']);
    (new SendIssueAlertEmailJob($alert->id))->handle();

    Mail::assertSentCount(1);
    Mail::assertSent(IssueAlertMail::class, fn ($mail) => $mail->hasTo('current@example.test'));
    expect(NotificationLog::first()->recipient_email)->toBe('current@example.test');
});

it('retries failed recipients without resending successful recipients', function () {
    User::factory()->create(['email' => 'first@example.test']);
    User::factory()->create(['email' => 'second@example.test']);
    $alert = IssueAlert::factory()->create();
    $mailFake = Mail::getFacadeRoot();
    Mail::shouldReceive('to')->once()->with('first@example.test')->andThrow(new RuntimeException('SMTP unavailable'));
    Mail::shouldReceive('to')->once()->with('second@example.test')->andReturnSelf();
    Mail::shouldReceive('send')->once();
    Mail::shouldReceive('getDefaultDriver')->andReturn('array');
    $job = new SendIssueAlertEmailJob($alert->id);

    expect(fn () => $job->handle())->toThrow(RuntimeException::class, 'One or more realtime notification emails failed.');
    expect(NotificationLog::where('status', 'failed')->count())->toBe(1)
        ->and(NotificationLog::where('status', 'sent')->count())->toBe(1);

    Mail::swap($mailFake);
    $job->handle();
    Mail::assertSentCount(1);
    Mail::assertSent(IssueAlertMail::class, fn ($mail) => $mail->hasTo('first@example.test'));
    expect(NotificationLog::where('status', 'sent')->count())->toBe(2);
});

it('delivers queued activity through the database worker', function () {
    User::factory()->create(['email' => 'viewer@example.test']);
    $alert = IssueAlert::factory()->create();
    Mail::assertNothingSent();

    $this->artisan('queue:work', ['--once' => true, '--sleep' => 0])->assertSuccessful();

    Mail::assertSent(IssueAlertMail::class, fn ($mail) => $mail->hasTo('viewer@example.test') && $mail->alert->id === $alert->id);
    expect(DB::table('jobs')->count())->toBe(0)->and(NotificationLog::first()->status)->toBe('sent');
});

it('leaves daily follow ups in the digest and safely skips deleted activity', function () {
    User::factory()->create();
    $followUp = IssueAlert::factory()->create(['type' => 'follow_up', 'status' => 'NEW', 'follow_up_date' => now()->toDateString()]);
    expect(DB::table('jobs')->count())->toBe(0);
    (new SendIssueAlertEmailJob($followUp->id))->handle();
    $alert = IssueAlert::factory()->create();
    $alert->delete();
    (new SendIssueAlertEmailJob($alert->id))->handle();

    Mail::assertNothingSent();
    expect(NotificationLog::count())->toBe(0);
});
