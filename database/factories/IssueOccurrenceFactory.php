<?php

namespace Database\Factories;

use App\Models\Issue;
use App\Models\IssueOccurrence;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<IssueOccurrence>
 */
class IssueOccurrenceFactory extends Factory
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
            'stack_trace_json' => [],
            'request_metadata_json' => [],
            'occurred_at' => now(),
        ];
    }
}
