<?php

namespace Database\Factories;

use App\Models\ApiRequestLog;
use App\Models\Application;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<ApiRequestLog>
 */
class ApiRequestLogFactory extends Factory
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
            'client_id' => 'cli_'.str_repeat('a', 24),
            'source_ip' => '192.0.2.1',
            'http_status' => 202,
            'signature_valid' => true,
            'request_size' => 256,
        ];
    }
}
