<?php

namespace Database\Factories;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class MonitorFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'project_id' => null,
            'name' => $this->faker->unique()->domainWord().' monitor',
            'type' => MonitorType::Http,
            'url' => $this->faker->url(),
            'http_method' => MonitorHttpMethod::Get,
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
            'check_certificate' => false,
            'certificate_expiry_warning_days' => 14,
            'is_active' => true,
            'current_status' => MonitorStatus::Unknown,
            'last_checked_at' => null,
            'consecutive_failures' => 0,
        ];
    }

    public function up(): static
    {
        return $this->state(fn () => [
            'current_status' => MonitorStatus::Up,
            'last_checked_at' => now(),
            'consecutive_failures' => 0,
        ]);
    }

    public function down(): static
    {
        return $this->state(fn () => [
            'current_status' => MonitorStatus::Down,
            'last_checked_at' => now(),
            'consecutive_failures' => 3,
        ]);
    }
}
