<?php

use App\Jobs\SendNotificationJob;
use App\Models\Application;
use App\Models\Issue;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use App\Models\User;
use App\Services\Sivera\EventIngestionService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Str;

uses(RefreshDatabase::class);

it('groups 50 occurrences and creates one high frequency alert during cooldown', function () {
    $application = Application::factory()->create();
    NotificationRule::factory()->create([
        'trigger' => 'high_frequency',
        'severity' => null,
        'frequency_threshold' => 5,
        'frequency_window_minutes' => 5,
        'cooldown_minutes' => 30,
    ]);
    $ingestion = app(EventIngestionService::class);

    for ($index = 0; $index < 50; $index++) {
        $ingestion->ingest($application, [
            'event_id' => (string) Str::ulid(),
            'application' => $application->slug,
            'environment' => 'production',
            'severity' => 'error',
            'exception' => ['class' => 'RuntimeException', 'message' => 'Example error', 'file' => 'app/Service.php', 'line' => 10],
            'request' => ['method' => 'GET', 'route' => 'example', 'path' => '/example'],
            'occurred_at' => now()->toIso8601String(),
        ], '192.0.2.10');
    }

    expect(Issue::count())->toBe(1)
        ->and(Issue::first()->occurrence_count)->toBe(50)
        ->and(NotificationLog::count())->toBe(1);
});

it('sends configured email and Qontak alerts without storing provider credentials', function () {
    $issue = Issue::factory()->create(['severity' => 'CRITICAL']);
    $emailRule = NotificationRule::factory()->create(['target' => 'alerts@example.test']);
    $emailLog = NotificationLog::create([
        'issue_id' => $issue->id,
        'notification_rule_id' => $emailRule->id,
        'channel' => 'email',
        'status' => 'pending',
    ]);
    Mail::fake();
    (new SendNotificationJob($emailLog->id))->handle();

    Mail::assertSentCount(1);
    expect($emailLog->fresh()->status)->toBe('sent');

    config()->set('services.qontak.token', 'dummy-token');
    config()->set('services.qontak.channel_id', 'dummy-channel');
    config()->set('services.qontak.template_id', 'dummy-template');
    Http::fake(['service-chat.qontak.com/*' => Http::response(['status' => 'ok'], 200)]);
    $qontakRule = NotificationRule::factory()->create([
        'name' => 'WhatsApp critical',
        'channel' => 'qontak',
        'target' => '6281234567890',
        'target_name' => 'Operations',
    ]);
    $qontakLog = NotificationLog::create([
        'issue_id' => $issue->id,
        'notification_rule_id' => $qontakRule->id,
        'channel' => 'qontak',
        'status' => 'pending',
    ]);
    (new SendNotificationJob($qontakLog->id))->handle();

    Http::assertSent(function ($request): bool {
        $payload = $request->data();

        return $request->url() === 'https://service-chat.qontak.com/api/open/v1/broadcasts/whatsapp/direct'
            && $payload['to_number'] === '6281234567890'
            && $payload['parameters']['body'][1]['key'] === '2'
            && ! str_contains($payload['parameters']['body'][1]['value_text'], "\n");
    });
    expect($qontakLog->fresh()->status)->toBe('sent')
        ->and($qontakLog->fresh()->response_code)->toBe(200);
});

it('allows only admins to configure alert rules', function () {
    $viewer = User::factory()->create();
    $admin = User::factory()->admin()->create();
    $payload = [
        'name' => 'Critical email',
        'trigger' => 'new_critical',
        'channel' => 'email',
        'target' => 'alerts@example.test',
        'severity' => 'CRITICAL',
        'frequency_threshold' => 10,
        'frequency_window_minutes' => 5,
        'cooldown_minutes' => 30,
        'is_active' => '1',
    ];

    $this->actingAs($viewer)->post(route('notifications.store'), $payload)->assertForbidden();
    $this->actingAs($admin)->post(route('notifications.store'), $payload)->assertRedirect(route('notifications.index'));

    expect(NotificationRule::count())->toBe(1);
});

it('normalizes WhatsApp recipients in admin settings', function () {
    $admin = User::factory()->admin()->create();

    $this->actingAs($admin)->post(route('notifications.store'), [
        'name' => 'Operations WhatsApp',
        'trigger' => 'reopened',
        'channel' => 'qontak',
        'target' => '0812-3456-7890',
        'target_name' => 'Operations',
        'frequency_threshold' => 10,
        'frequency_window_minutes' => 5,
        'cooldown_minutes' => 30,
        'is_active' => '1',
    ])->assertRedirect(route('notifications.index'));

    expect(NotificationRule::firstOrFail()->target)->toBe('6281234567890');
});

it('alerts when an admin raises severity or reopens an issue', function () {
    $admin = User::factory()->admin()->create();
    $issue = Issue::factory()->create(['severity' => 'ERROR', 'status' => 'RESOLVED']);
    NotificationRule::factory()->create(['name' => 'Escalation', 'trigger' => 'becomes_critical', 'severity' => 'CRITICAL']);
    NotificationRule::factory()->create(['name' => 'Reopen', 'trigger' => 'reopened', 'severity' => null]);

    $this->actingAs($admin)->post(route('issues.severity', $issue), ['severity' => 'CRITICAL'])->assertRedirect();
    $this->post(route('issues.status', $issue), ['status' => 'REOPENED'])->assertRedirect();

    expect(NotificationLog::count())->toBe(2);
});
