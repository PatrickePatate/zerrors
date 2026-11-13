<?php

namespace Database\Factories;

use App\Models\CronMonitor;
use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CronMonitor>
 */
class CronMonitorFactory extends Factory
{
    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'slug' => fake()->unique()->slug(2),
            'name' => fake()->words(2, true),
            'schedule_type' => 'crontab',
            'schedule_value' => '* * * * *',
            'checkin_margin' => 5,
            'max_runtime' => 60,
            'timezone' => 'UTC',
            'status' => 'unknown',
        ];
    }
}
