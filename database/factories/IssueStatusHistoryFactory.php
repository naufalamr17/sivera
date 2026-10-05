<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueStatusHistory;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<IssueStatusHistory>
 */
class IssueStatusHistoryFactory extends Factory
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
            'from_status' => 'NEW',
            'to_status' => 'ACKNOWLEDGED',
            'changed_by' => User::factory()->admin(),
        ];
    }
}
