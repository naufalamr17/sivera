<?php

namespace Database\Factories;

use App\Models\EventReceipt;
use App\Models\Issue;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<EventReceipt>
 */
class EventReceiptFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'event_id' => (string) Str::ulid(),
            'occurred_at' => now(),
        ];
    }
}
