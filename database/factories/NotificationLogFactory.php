<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\NotificationLog;
use App\Models\NotificationRule;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<NotificationLog>
 */
class NotificationLogFactory extends Factory
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
            'notification_rule_id' => NotificationRule::factory(),
            'channel' => 'email',
            'status' => 'pending',
        ];
    }
}
