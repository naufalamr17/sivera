<?php

use App\Http\Middleware\EnsureHttps;
use App\Models\Application;
use App\Models\ApplicationCredential;
use App\Models\Issue;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

uses(RefreshDatabase::class);

it('keeps viewers read only and lets admins handle issues', function () {
    $viewer = User::factory()->create(['role' => 'viewer']);
    $admin = User::factory()->admin()->create();
    $issue = Issue::factory()->create();

    $this->actingAs($viewer)->get('/dashboard')->assertOk();
    $this->actingAs($viewer)->get(route('issues.show', $issue))->assertOk();
    $this->actingAs($viewer)->post(route('issues.status', $issue), ['status' => 'RESOLVED'])->assertForbidden();
    $this->actingAs($viewer)->post(route('applications.store'), ['name' => 'Test'])->assertForbidden();
    $this->actingAs($viewer)->get(route('users.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('audit.index'))->assertForbidden();
    $this->actingAs($viewer)->get(route('api-logs.index'))->assertForbidden();

    $this->actingAs($admin)->post(route('issues.status', $issue), ['status' => 'RESOLVED'])->assertRedirect();
    expect($issue->fresh()->status)->toBe('RESOLVED');
});

it('stores generated credentials encrypted and exposes the secret only once', function () {
    $admin = User::factory()->admin()->create();
    $application = Application::factory()->create();

    $response = $this->actingAs($admin)->post(route('credentials.store', $application));
    $response->assertRedirect();
    $credential = $application->credentials()->firstOrFail();
    $secret = session('new_credential.secret');

    expect($secret)->toBeString()
        ->and($credential->encrypted_secret)->toBe($secret)
        ->and(DB::table('application_credentials')->value('encrypted_secret'))->not->toBe($secret);

    $this->get(route('applications.show', $application))->assertSee($secret);
    $this->get(route('applications.show', $application))->assertDontSee($secret);
});

it('lets admins manage users but prevents self deactivation', function () {
    $admin = User::factory()->admin()->create();
    $this->actingAs($admin)->post(route('users.store'), [
        'name' => 'Viewer SIVERA',
        'email' => 'viewer@example.test',
        'role' => 'viewer',
        'is_active' => '1',
        'password' => 'test-password-123',
        'password_confirmation' => 'test-password-123',
    ])->assertRedirect(route('users.index'));

    expect(User::query()->where('email', 'viewer@example.test')->firstOrFail()->role)->toBe('viewer');

    $this->put(route('users.update', $admin), [
        'name' => $admin->name,
        'email' => $admin->email,
        'role' => 'viewer',
        'is_active' => '0',
    ])->assertSessionHasErrors('role');

    expect($admin->fresh()->role)->toBe('admin')
        ->and($admin->fresh()->is_active)->toBeTrue();
});

it('rotates credentials with a grace period and revokes them', function () {
    $admin = User::factory()->admin()->create();
    $application = Application::factory()->create();
    $previous = ApplicationCredential::factory()->for($application)->create();

    $this->actingAs($admin)->post(route('credentials.rotate', $application))->assertRedirect();
    expect($previous->fresh()->expires_at)->not->toBeNull()
        ->and($application->credentials()->count())->toBe(2);

    $this->post(route('credentials.revoke', [$application, $previous]))->assertRedirect();
    expect($previous->fresh()->is_active)->toBeFalse();
});

it('authenticates existing active users and exposes a minimal health response', function () {
    $viewer = User::factory()->create();
    $this->get('/health')->assertExactJson(['status' => 'ok']);
    $this->post(route('login.store'), ['email' => $viewer->email, 'password' => 'password'])
        ->assertRedirect(route('dashboard'));
    $this->get(route('dashboard'))->assertOk();
});

it('rejects insecure production requests', function () {
    $environment = app()->environment();
    app()->detectEnvironment(fn () => 'production');
    $middleware = new EnsureHttps;

    expect($middleware->handle(Request::create('http://example.test/dashboard'), fn () => response('ok'))->getStatusCode())->toBe(403)
        ->and($middleware->handle(Request::create('http://example.test/api/v1/events', 'POST'), fn () => response('ok'))->getStatusCode())->toBe(401);

    app()->detectEnvironment(fn () => $environment);
});
