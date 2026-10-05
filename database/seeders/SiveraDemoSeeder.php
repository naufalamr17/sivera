<?php

namespace Database\Seeders;

use App\Models\Application;
use App\Models\Issue;
use App\Models\IssueOccurrence;
use Illuminate\Database\Seeder;

class SiveraDemoSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Demo data is disabled in production.');

            return;
        }

        Application::factory()->count(2)->create()->each(function (Application $application): void {
            Issue::factory()->count(3)->for($application)->create()->each(function (Issue $issue): void {
                IssueOccurrence::factory()->for($issue)->create();
            });
        });
    }
}
