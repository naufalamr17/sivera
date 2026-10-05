<?php

namespace Database\Factories;

use App\Models\Application;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Issue>
 */
class IssueFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'uuid' => (string) Str::uuid(),
            'application_id' => Application::factory(),
            'fingerprint' => hash('sha256', Str::random(32)),
            'severity' => 'ERROR',
            'status' => 'NEW',
            'exception_class' => 'RuntimeException',
            'message' => fake()->sentence(),
            'file' => 'app/Services/ExampleService.php',
            'line' => 42,
            'route' => 'example.index',
            'method' => 'GET',
            'first_seen_at' => now(),
            'last_seen_at' => now(),
            'occurrence_count' => 1,
        ];
    }
}
