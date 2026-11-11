<?php

use App\Models\ApiRequestLog;
use App\Models\Application;
use App\Models\ApplicationCredential;
use App\Models\EventReceipt;
use App\Models\Issue;
use App\Models\IssueOccurrence;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

uses(RefreshDatabase::class);

function eventPayload(Application $application): array
{
    return [
        'event_id' => (string) Str::ulid(),
        'application' => $application->slug,
        'environment' => 'production',
        'severity' => 'error',
        'exception' => [
            'class' => 'RuntimeException',
            'message' => 'password=supersecret failed',
            'file' => '/home/server/app/Services/Invoice.php',
            'line' => 42,
        ],
        'request' => ['method' => 'POST', 'route' => 'invoices.store', 'path' => '/invoices'],
        'user' => ['id' => 182],
        'stack_trace' => [['file' => '/home/server/app/Services/Invoice.php', 'line' => 42, 'function' => 'save', 'class' => 'App\\Services\\Invoice']],
        'occurred_at' => now()->toIso8601String(),
    ];
}

function sendEvent(TestCase $test, Application $application, ApplicationCredential $credential, array $payload, ?string $nonce = null, ?string $secret = null, ?int $sentAt = null): TestResponse
{
    $timestamp = (string) ($sentAt ?? time());
    $nonce ??= (string) Str::uuid();
    $body = json_encode($payload);
    $canonical = implode("\n", ['POST', '/api/v1/events', $timestamp, $nonce, hash('sha256', $body)]);
    $signature = 'sha256='.hash_hmac('sha256', $canonical, $secret ?? $credential->encrypted_secret);

    return $test->withHeaders([
        'X-SIVERA-App-ID' => $application->slug,
        'X-SIVERA-Client-ID' => $credential->client_id,
        'X-SIVERA-Timestamp' => $timestamp,
        'X-SIVERA-Nonce' => $nonce,
        'X-SIVERA-Signature' => $signature,
    ])->postJson('/api/v1/events', $payload);
}

beforeEach(function () {
    $this->application = Application::factory()->create();
    $this->credential = ApplicationCredential::factory()->for($this->application)->create();
});

it('isolates duplicate event ids by application even when the issue changes', function () {
    $otherApplication = Application::factory()->create();
    $otherCredential = ApplicationCredential::factory()->for($otherApplication)->create();
    $first = eventPayload($this->application);
    $second = eventPayload($otherApplication);
    $second['event_id'] = $first['event_id'];

    sendEvent($this, $this->application, $this->credential, $first)->assertAccepted()->assertJsonPath('duplicate', false);
    sendEvent($this, $otherApplication, $otherCredential, $second)->assertAccepted()->assertJsonPath('duplicate', false);
    $second['exception']['line'] = 999;
    sendEvent($this, $otherApplication, $otherCredential, $second)->assertAccepted()->assertJsonPath('duplicate', true);

    expect(EventReceipt::count())->toBe(2)
        ->and(IssueOccurrence::count())->toBe(2)
        ->and(Issue::count())->toBe(2)
        ->and(Issue::query()->sum('occurrence_count'))->toBe(2);
});

it('bounds unauthenticated request logs across source addresses', function () {
    for ($attempt = 0; $attempt < 65; $attempt++) {
        $this->withServerVariables(['REMOTE_ADDR' => '192.0.2.'.($attempt + 1)])
            ->postJson('/api/v1/events', [])->assertUnauthorized();
    }
    expect(ApiRequestLog::count())->toBe(60);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertAccepted();
    expect(ApiRequestLog::count())->toBe(61);
    $this->travel(61)->seconds();
    $this->postJson('/api/v1/events', [])->assertUnauthorized();
    expect(ApiRequestLog::count())->toBe(62);
});

it('throttles ingestion before signature processing and database logging', function () {
    for ($attempt = 0; $attempt < 600; $attempt++) {
        $this->postJson('/api/v1/events', [])->assertUnauthorized();
    }
    $this->postJson('/api/v1/events', [])->assertTooManyRequests()->assertHeader('Retry-After');
    expect(ApiRequestLog::count())->toBe(60);
});

it('accepts signed events, groups issues, and keeps duplicate event ids idempotent', function () {
    $first = eventPayload($this->application);
    sendEvent($this, $this->application, $this->credential, $first)->assertStatus(202)->assertJsonPath('duplicate', false);
    sendEvent($this, $this->application, $this->credential, $first)->assertStatus(202)->assertJsonPath('duplicate', true);

    $second = eventPayload($this->application);
    sendEvent($this, $this->application, $this->credential, $second)->assertStatus(202);

    expect(Issue::count())->toBe(1)
        ->and(Issue::first()->occurrence_count)->toBe(2)
        ->and(IssueOccurrence::count())->toBe(2)
        ->and(Issue::first()->message)->not->toContain('supersecret')
        ->and(Issue::first()->file)->toBe('app/Services/Invoice.php');
});

it('normalizes source event timestamps to UTC before storing and displaying them in WIB', function (string $occurredAt) {
    config()->set('sivera.timezone', 'Asia/Jakarta');
    $this->travelTo(CarbonImmutable::parse('2026-11-11T07:31:05Z'));
    $this->credential->update(['valid_from' => now()->subMinute()]);
    $payload = eventPayload($this->application);
    $payload['occurred_at'] = $occurredAt;

    sendEvent($this, $this->application, $this->credential, $payload)->assertAccepted();

    $this->assertDatabaseHas('event_receipts', ['event_id' => $payload['event_id'], 'occurred_at' => '2026-11-11 07:31:05']);
    $this->assertDatabaseHas('issue_occurrences', ['event_id' => $payload['event_id'], 'occurred_at' => '2026-11-11 07:31:05']);
    $issue = Issue::firstOrFail();
    expect($issue->last_seen_at->format('Y-m-d H:i:s'))->toBe('2026-11-11 07:31:05');

    $response = $this->actingAs(User::factory()->create())->get(route('issues.show', $issue))->assertOk();
    expect(substr_count($response->getContent(), '11 Nov 2026 14:31:05 WIB'))->toBe(3);
})->with([
    'WIB source' => '2026-11-11T14:31:05+07:00',
    'negative offset source' => '2026-11-11T02:31:05-05:00',
    'UTC source' => '2026-11-11T07:31:05Z',
    'timezone omitted defaults to UTC' => '2026-11-11 07:31:05',
]);

it('rejects invalid signatures, expired timestamps, replayed nonces, and disabled applications', function () {
    $payload = eventPayload($this->application);
    sendEvent($this, $this->application, $this->credential, $payload, secret: 'wrong')->assertStatus(401);
    sendEvent($this, $this->application, $this->credential, $payload, sentAt: time() - 301)->assertStatus(401);

    $nonce = (string) Str::uuid();
    sendEvent($this, $this->application, $this->credential, $payload, nonce: $nonce)->assertStatus(202);
    sendEvent($this, $this->application, $this->credential, $payload, nonce: $nonce)->assertStatus(401);

    $this->application->update(['is_active' => false]);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(401);
});

it('rejects unknown and revoked credentials and cross application identities', function () {
    $payload = eventPayload($this->application);
    $unknown = clone $this->credential;
    $unknown->client_id = 'cli_'.str_repeat('a', 24);
    sendEvent($this, $this->application, $unknown, $payload)->assertStatus(401);

    $other = Application::factory()->create();
    sendEvent($this, $other, $this->credential, eventPayload($other))->assertStatus(401);

    $this->credential->update(['is_active' => false]);
    sendEvent($this, $this->application, $this->credential, $payload)->assertStatus(401);
});

it('enforces credential expiry and application IP allowlists', function () {
    $this->credential->update(['expires_at' => now()->subSecond()]);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(401);

    $this->credential->update(['expires_at' => null]);
    $this->application->update(['allowed_ips' => ['192.0.2.10']]);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(401);
});

it('rejects invalid payloads without persisting sensitive data', function () {
    $payload = eventPayload($this->application);
    $payload['exception']['password'] = 'must-not-store';
    sendEvent($this, $this->application, $this->credential, $payload)->assertStatus(422)->assertExactJson(['error' => 'Invalid payload']);

    expect(Issue::count())->toBe(0)
        ->and(IssueOccurrence::count())->toBe(0)
        ->and(ApiRequestLog::first()->http_status)->toBe(422);
});

it('reopens a resolved issue when a matching event arrives', function () {
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(202);
    Issue::firstOrFail()->update(['status' => 'RESOLVED']);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(202);

    expect(Issue::first()->status)->toBe('REOPENED')
        ->and(Issue::first()->occurrence_count)->toBe(2);
});

it('limits requests per credential', function () {
    $this->application->update(['rate_limit_per_minute' => 1]);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(202);
    sendEvent($this, $this->application, $this->credential, eventPayload($this->application))->assertStatus(429)->assertHeader('Retry-After');
});

it('rejects oversized payloads before processing', function () {
    $this->withHeaders(['Content-Type' => 'application/json'])
        ->call('POST', '/api/v1/events', [], [], [], [], str_repeat('x', 262145))
        ->assertStatus(413);

    expect(Issue::count())->toBe(0);
});

it('redacts sensitive event messages before storing them', function (string $message, string $expected) {
    $payload = eventPayload($this->application);
    $payload['exception']['message'] = $message;
    $payload['request']['path'] = '/example?'.$message;

    sendEvent($this, $this->application, $this->credential, $payload)->assertStatus(202);

    expect(Issue::firstOrFail()->message)->toBe($expected)
        ->and(IssueOccurrence::firstOrFail()->request_metadata_json['path'])->toBe('/example?'.$expected);
})->with('sensitive event messages');

it('preserves long normalized file paths and groups their events', function () {
    $relativePath = 'app/'.str_repeat('LongDirectory/', 30).'Service.php';
    $payload = eventPayload($this->application);
    $payload['exception']['file'] = '/home/server/'.$relativePath;

    sendEvent($this, $this->application, $this->credential, $payload)->assertStatus(202);
    $payload['event_id'] = (string) Str::ulid();
    sendEvent($this, $this->application, $this->credential, $payload)->assertStatus(202);

    expect(Issue::count())->toBe(1)
        ->and(Issue::firstOrFail()->file)->toBe($relativePath)
        ->and(Issue::firstOrFail()->occurrence_count)->toBe(2);
});
