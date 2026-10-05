<?php

namespace Database\Seeders;

use App\Models\User;
use Illuminate\Database\Seeder;

class UserSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (! app()->environment('local', 'testing')) {
            $this->command?->warn('Sample users are only seeded locally. Use sivera:create-admin elsewhere.');

            return;
        }

        foreach (['admin', 'viewer'] as $role) {
            User::query()->firstOrCreate(
                ['email' => $role.'@sivera.test'],
                [
                    'name' => ucfirst($role).' SIVERA',
                    'password' => 'Sivera123!',
                    'role' => $role,
                    'is_active' => true,
                    'email_verified_at' => now(),
                ],
            );
        }
    }
}
