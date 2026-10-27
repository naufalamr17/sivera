<?php

namespace Database\Factories;

use App\Models\NotificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationRule>
 */
class NotificationRuleFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => 'Morning summary',
            'trigger' => 'daily_digest',
            'channel' => 'email',
            'target' => fake()->safeEmail(),
            'severity' => null,
            'frequency_threshold' => 10,
            'frequency_window_minutes' => 5,
            'cooldown_minutes' => 30,
            'is_active' => true,
        ];
    }
}
