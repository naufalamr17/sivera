<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\ApplicationCredential;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<ApplicationCredential>
 */
class ApplicationCredentialFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'application_id' => Application::factory(),
            'client_id' => 'cli_'.Str::lower(Str::random(24)),
            'encrypted_secret' => Str::random(64),
            'valid_from' => now()->subMinute(),
            'is_active' => true,
        ];
    }
}
