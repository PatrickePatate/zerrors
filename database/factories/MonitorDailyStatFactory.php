<?php

namespace Database\Factories;

use App\Models\Monitor;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorDailyStatFactory extends Factory
{
    public function definition(): array
    {
        $total = $this->faker->numberBetween(50, 288);
        $up = $this->faker->numberBetween((int) ($total * 0.9), $total);

        return [
            'monitor_id' => Monitor::factory(),
            'date' => $this->faker->dateTimeBetween('-90 days', '-8 days')->format('Y-m-d'),
            'total_checks' => $total,
            'up_count' => $up,
            'avg_response_time_ms' => $this->faker->numberBetween(50, 400),
            'avg_dns_time_ms' => $this->faker->numberBetween(1, 20),
            'avg_connect_time_ms' => $this->faker->numberBetween(5, 60),
            'avg_ssl_time_ms' => $this->faker->numberBetween(20, 150),
            'avg_ttfb_ms' => $this->faker->numberBetween(20, 300),
            'avg_download_time_ms' => $this->faker->numberBetween(1, 10),
        ];
    }
}
