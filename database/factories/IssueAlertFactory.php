<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueAlert;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<IssueAlert> */
class IssueAlertFactory extends Factory
{
    public function definition(): array
    {
        return [
            'issue_id' => Issue::factory(),
            'type' => 'status_changed',
            'from_status' => 'NEW',
            'status' => 'RESOLVED',
            'created_at' => now(),
        ];
    }
}
