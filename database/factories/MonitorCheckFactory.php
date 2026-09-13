<?php

namespace Database\Factories;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorCheckFactory extends Factory
{
    public function definition(): array
    {
        return [
            'monitor_id' => Monitor::factory(),
            'status' => MonitorStatus::Up,
            'response_time_ms' => $this->faker->numberBetween(20, 400),
            'dns_time_ms' => $this->faker->numberBetween(1, 20),
            'connect_time_ms' => $this->faker->numberBetween(5, 60),
            'ssl_time_ms' => $this->faker->numberBetween(20, 150),
            'ttfb_ms' => $this->faker->numberBetween(20, 300),
            'download_time_ms' => $this->faker->numberBetween(1, 10),
            'status_code' => 200,
            'error_message' => null,
            'health_checks' => null,
            'checked_at' => now(),
        ];
    }

    public function up(): static
    {
        return $this->state(fn () => [
            'status' => MonitorStatus::Up,
            'response_time_ms' => $this->faker->numberBetween(20, 400),
            'status_code' => 200,
            'error_message' => null,
        ]);
    }

    public function down(): static
    {
        return $this->state(fn () => [
            'status' => MonitorStatus::Down,
            'response_time_ms' => null,
            'dns_time_ms' => null,
            'connect_time_ms' => null,
            'ssl_time_ms' => null,
            'ttfb_ms' => null,
            'download_time_ms' => null,
            'status_code' => $this->faker->randomElement([500, 502, 503, null]),
            'error_message' => 'Connection timed out.',
        ]);
    }
}
