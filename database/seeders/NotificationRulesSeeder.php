<?php

namespace Database\Seeders;

use App\Models\NotificationRule;
use Illuminate\Database\Seeder;

class NotificationRulesSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command?->error('Demo notification rules are disabled in production.');

            return;
        }

        NotificationRule::factory()->create(['target' => 'alerts@example.test']);
    }
}
