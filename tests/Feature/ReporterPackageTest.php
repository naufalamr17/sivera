<?php

use App\Models\Application;
use App\Models\ApplicationCredential;
use App\Models\Issue;
use Illuminate\Contracts\Debug\ExceptionHandler;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Http;
use Mlp\SiveraReporter\EventBuilder;
use Mlp\SiveraReporter\Jobs\SendEventJob;
use Mlp\SiveraReporter\Reporter;
use Mlp\SiveraReporter\SiveraServiceProvider;

spl_autoload_register(function (string $class): void {
    $prefix = 'Mlp\\SiveraReporter\\';

    if (str_starts_with($class, $prefix)) {
        require dirname(__DIR__, 2).'/packages/sivera-reporter/src/'.str_replace('\\', '/', substr($class, strlen($prefix))).'.php';
    }
});

uses(RefreshDatabase::class);

beforeEach(function () {
    config()->set('sivera.enabled', true);
    config()->set('sivera.url', 'https://monitor.example.test');
    config()->set('sivera.app_id', 'test-app');
    config()->set('sivera.client_id', 'cli_'.str_repeat('a', 24));
    config()->set('sivera.secret', str_repeat('s', 64));
    config()->set('sivera.environment', 'production');
    config()->set('sivera.transport', 'http');
});

it('builds a minimal payload and signs its exact HTTP body', function () {
    Http::fake(['monitor.example.test/*' => Http::response(['accepted' => true], 202)]);
    $reporter = new Reporter(new EventBuilder);
    $reporter->report(new RuntimeException('password=supersecret failed'));

    Http::assertSent(function ($request): bool {
        $body = $request->body();
        $payload = json_decode($body, true);
        $timestamp = $request->header('X-SIVERA-Timestamp')[0];
        $nonce = $request->header('X-SIVERA-Nonce')[0];
        $expected = 'sha256='.hash_hmac('sha256', implode("\n", [
            'POST', '/api/v1/events', $timestamp, $nonce, hash('sha256', $body),
        ]), config('sivera.secret'));

        return $request->url() === 'https://monitor.example.test/api/v1/events'
            && $request->header('X-SIVERA-Signature')[0] === $expected
            && ! str_contains($payload['exception']['message'], 'supersecret')
            && ! array_key_exists('body', $payload['request']);
    });
});

it('never throws when delivery fails and skips disabled reporting', function () {
    Http::fake(fn () => throw new ConnectionException('offline'));
    $reporter = new Reporter(new EventBuilder);

    expect(fn () => $reporter->report(new RuntimeException('test')))->not->toThrow(Throwable::class);

    config()->set('sivera.enabled', false);
    Http::fake(['monitor.example.test/*' => Http::response(['accepted' => true], 202)]);
    $reporter->report(new RuntimeException('test'));
    Http::assertSentCount(0);
});

it('dispatches a single job in queue mode without requiring a new queue', function () {
    Bus::fake();
    config()->set('sivera.transport', 'queue');
    $reporter = new Reporter(new EventBuilder);

    expect($reporter->send(['event_id' => 'example']))->toBeTrue();
    Bus::assertDispatched(SendEventJob::class, 1);
});

it('registers a test command that sends a synthetic event', function () {
    Http::fake(['monitor.example.test/*' => Http::response(['accepted' => true], 202)]);
    $this->app->register(SiveraServiceProvider::class);

    expect(Artisan::call('sivera:test'))->toBe(0);
    Http::assertSentCount(1);
});

it('explains invalid reporter configuration without sending a request', function (string $key, mixed $value, string $expected) {
    Http::fake();
    config()->set('sivera.'.$key, $value);
    $this->app->register(SiveraServiceProvider::class);

    expect(Artisan::call('sivera:test'))->toBe(1);
    expect(Artisan::output())->toContain($expected)->not->toContain(config('sivera.secret') ?: 'unused-secret');
    Http::assertNothingSent();
})->with([
    'disabled' => ['enabled', false, 'SIVERA_ENABLED=false'],
    'missing URL' => ['url', null, 'SIVERA_URL must be an HTTPS base URL'],
    'HTTP URL' => ['url', 'http://monitor.example.test', 'SIVERA_URL must be an HTTPS base URL'],
    'missing app ID' => ['app_id', '', 'SIVERA_APP_ID is missing'],
    'missing client ID' => ['client_id', null, 'SIVERA_CLIENT_ID is missing'],
    'missing secret' => ['secret', '', 'SIVERA_SECRET is missing'],
]);

it('explains rejected HTTP responses without printing the response body or credentials', function (int $status, string $expected) {
    $secret = config('sivera.secret');
    $clientId = config('sivera.client_id');
    Http::fake(['monitor.example.test/*' => Http::response(['error' => $secret, 'debug' => $clientId], $status)]);
    $this->app->register(SiveraServiceProvider::class);

    expect(Artisan::call('sivera:test'))->toBe(1);
    expect(Artisan::output())->toContain('HTTP '.$status.':', $expected)->not->toContain($secret, $clientId);
})->with([
    'authentication' => [401, 'Authentication rejected'],
    'forbidden' => [403, 'Access forbidden'],
    'wrong endpoint' => [404, 'Ingestion endpoint not found'],
    'payload size' => [413, '256 KiB'],
    'environment mismatch' => [422, 'SIVERA_ENVIRONMENT'],
    'rate limit' => [429, 'Rate limit exceeded'],
    'server failure' => [500, 'SIVERA server or gateway error'],
    'gateway failure' => [502, 'SIVERA server or gateway error'],
    'unexpected success status' => [200, 'event delivery requires HTTP 202'],
]);

it('explains network failures without printing raw exception messages', function (string $message, string $expected) {
    $secret = config('sivera.secret');
    $clientId = config('sivera.client_id');
    Http::fake(fn () => throw new ConnectionException($message.' '.$secret.' '.$clientId));
    $this->app->register(SiveraServiceProvider::class);

    expect(Artisan::call('sivera:test'))->toBe(1);
    expect(Artisan::output())->toContain($expected)->not->toContain($secret, $clientId);
})->with([
    'DNS' => ['cURL error 6: unresolved host', 'Could not resolve the SIVERA hostname'],
    'connection' => ['cURL error 7: refused', 'Could not connect to SIVERA'],
    'timeout' => ['cURL error 28: timed out', 'SIVERA_CONNECT_TIMEOUT'],
    'TLS handshake' => ['cURL error 35: handshake failed', 'TLS handshake failed'],
    'certificate' => ['cURL error 60: certificate problem', 'TLS certificate verification failed'],
    'other cURL failure' => ['cURL error 52: empty response', 'cURL error 52: No HTTP response received'],
    'unknown network failure' => ['offline', 'Connection failed: No HTTP response received'],
]);

it('keeps unexpected delivery failures safe and clears the diagnostic after success', function () {
    $reporter = new Reporter(new EventBuilder);
    Http::fakeSequence()
        ->pushResponse(fn () => throw new RuntimeException(config('sivera.secret')))
        ->push(['accepted' => true], 202);

    expect($reporter->sendNow(['event_id' => 'test']))->toBeFalse();
    expect($reporter->lastError())->toContain('RuntimeException')->not->toContain(config('sivera.secret'));

    expect($reporter->sendNow(['event_id' => 'test']))->toBeTrue();
    expect($reporter->lastError())->toBeNull();
});

it('hooks Laravel exception reporting without suppressing normal logging', function () {
    Http::fake(['monitor.example.test/*' => Http::response(['accepted' => true], 202)]);
    $this->app->register(SiveraServiceProvider::class);

    $this->app->make(ExceptionHandler::class)->report(new RuntimeException('SIVERA hook test'));

    Http::assertSentCount(1);
});

it('delivers a reporter payload accepted by the ingestion API', function () {
    $application = Application::factory()->create();
    $credential = ApplicationCredential::factory()->for($application)->create();
    config()->set('sivera.app_id', $application->slug);
    config()->set('sivera.client_id', $credential->client_id);
    config()->set('sivera.secret', $credential->encrypted_secret);
    $captured = null;
    Http::fake(function ($request) use (&$captured) {
        $captured = $request;

        return Http::response(['accepted' => true], 202);
    });

    expect((new Reporter(new EventBuilder))->sendNow((new EventBuilder)->build(new RuntimeException('integration'))))->toBeTrue();
    $server = ['CONTENT_TYPE' => 'application/json'];

    foreach (['X-SIVERA-App-ID', 'X-SIVERA-Client-ID', 'X-SIVERA-Timestamp', 'X-SIVERA-Nonce', 'X-SIVERA-Signature'] as $name) {
        $server['HTTP_'.strtoupper(str_replace('-', '_', $name))] = $captured->header($name)[0];
    }

    $this->call('POST', '/api/v1/events', [], [], [], $server, $captured->body())->assertStatus(202);
    expect(Issue::count())->toBe(1);
});

it('redacts sensitive event messages before sending them', function (string $message, string $expected) {
    Http::fake(['monitor.example.test/*' => Http::response(['accepted' => true], 202)]);

    (new Reporter(new EventBuilder))->report(new RuntimeException($message));

    Http::assertSent(fn ($request): bool => $request->data()['exception']['message'] === $expected);
})->with('sensitive event messages');
