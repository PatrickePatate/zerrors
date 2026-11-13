<?php

namespace Database\Factories;

use App\Models\CronCheckIn;
use App\Models\CronMonitor;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<CronCheckIn>
 */
class CronCheckInFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'cron_monitor_id' => CronMonitor::factory(),
            'check_in_id' => (string) Str::uuid(),
            'status' => 'ok',
            'duration' => 1.5,
        ];
    }
}
