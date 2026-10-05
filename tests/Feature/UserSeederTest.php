<?php

use App\Models\User;
use Database\Seeders\UserSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Schema;

uses(RefreshDatabase::class);

it('seeds local admin and viewer accounts without changing existing users', function () {
    $this->seed(UserSeeder::class);

    $admin = User::query()->where('email', 'admin@sivera.test')->firstOrFail();
    $viewer = User::query()->where('email', 'viewer@sivera.test')->firstOrFail();

    expect($admin->role)->toBe('admin')
        ->and($viewer->role)->toBe('viewer')
        ->and(Hash::check('Sivera123!', $admin->password))->toBeTrue()
        ->and(Schema::hasColumn('users', 'remember_token'))->toBeFalse();

    $admin->update(['name' => 'Existing Admin']);
    $this->seed(UserSeeder::class);

    expect(User::query()->count())->toBe(2)
        ->and($admin->fresh()->name)->toBe('Existing Admin');
});
