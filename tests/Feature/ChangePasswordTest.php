<?php

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;

uses(RefreshDatabase::class);

it('lets admins and viewers change only their own password', function (string $role) {
    $user = User::factory()->create(['role' => $role]);
    $other = User::factory()->create();
    $otherHash = $other->password;
    $newPassword = 'new-safe-password-123';

    $this->actingAs($user)->get(route('password.edit'))->assertOk()
        ->assertSee('Current password')->assertSee('name="password_confirmation"', false);
    $sessionId = session()->getId();
    $this->put(route('password.update'), [
        'current_password' => 'password',
        'password' => $newPassword,
        'password_confirmation' => $newPassword,
        'user_id' => $other->id,
        'role' => 'admin',
    ])->assertRedirect(route('password.edit'))->assertSessionHasNoErrors()->assertSessionHas('status');

    expect(Hash::check($newPassword, $user->fresh()->password))->toBeTrue()
        ->and(Hash::check('password', $user->fresh()->password))->toBeFalse()
        ->and($user->fresh()->password)->not->toBe($newPassword)
        ->and($user->fresh()->role)->toBe($role)
        ->and($other->fresh()->password)->toBe($otherHash)
        ->and(session()->getId())->not->toBe($sessionId);

    $audit = AuditLog::query()->where('action', 'user.password_changed')->sole();
    expect($audit->user_id)->toBe($user->id)->and((int) $audit->subject_id)->toBe($user->id)
        ->and($audit->toJson())->not->toContain($newPassword, 'current_password');

    $this->post(route('logout'))->assertRedirect(route('login'));
    $this->post(route('login.store'), ['email' => $user->email, 'password' => 'password'])->assertSessionHasErrors('email');
    $this->post(route('login.store'), ['email' => $user->email, 'password' => $newPassword])->assertRedirect(route('dashboard'));
    $this->assertAuthenticatedAs($user);
})->with(['admin', 'viewer']);

it('rejects invalid password changes without changing or flashing secrets', function (array $overrides, string $error) {
    $user = User::factory()->create();
    $hash = $user->password;
    $payload = array_replace([
        'current_password' => 'password',
        'password' => 'new-safe-password-123',
        'password_confirmation' => 'new-safe-password-123',
    ], $overrides);

    $this->actingAs($user)->from(route('password.edit'))->put(route('password.update'), $payload)
        ->assertRedirect(route('password.edit'))->assertSessionHasErrors($error)
        ->assertSessionMissing('_old_input.current_password')
        ->assertSessionMissing('_old_input.password')
        ->assertSessionMissing('_old_input.password_confirmation');

    expect($user->fresh()->password)->toBe($hash)
        ->and(AuditLog::query()->where('action', 'user.password_changed')->count())->toBe(0);
})->with([
    'missing current password' => [['current_password' => ''], 'current_password'],
    'wrong current password' => [['current_password' => 'wrong-password'], 'current_password'],
    'too short' => [['password' => 'short', 'password_confirmation' => 'short'], 'password'],
    'confirmation mismatch' => [['password_confirmation' => 'different-password'], 'password'],
    'missing new password' => [['password' => ''], 'password'],
    'bcrypt byte limit' => [['password' => str_repeat('é', 40), 'password_confirmation' => str_repeat('é', 40)], 'password'],
]);

it('rejects reusing the current password', function () {
    $password = 'existing-safe-password';
    $user = User::factory()->create(['password' => $password]);
    $this->actingAs($user)->put(route('password.update'), [
        'current_password' => $password,
        'password' => $password,
        'password_confirmation' => $password,
    ])->assertSessionHasErrors('password');
});

it('requires an authenticated active account', function () {
    $this->get(route('password.edit'))->assertRedirect(route('login'));
    $this->put(route('password.update'), [])->assertRedirect(route('login'));
    $inactive = User::factory()->create(['is_active' => false]);
    $this->actingAs($inactive)->get(route('password.edit'))->assertRedirect(route('login'));
    $this->actingAs($inactive)->put(route('password.update'), [])->assertRedirect(route('login'));
});

it('throttles repeated password guesses', function () {
    $user = User::factory()->create();
    $this->actingAs($user);
    for ($attempt = 0; $attempt < 5; $attempt++) {
        $this->put(route('password.update'), [
            'current_password' => 'incorrect-password',
            'password' => 'new-safe-password-123',
            'password_confirmation' => 'new-safe-password-123',
        ])->assertSessionHasErrors('current_password');
    }
    $this->put(route('password.update'), [])->assertTooManyRequests();
    expect(Hash::check('password', $user->fresh()->password))->toBeTrue();
});
