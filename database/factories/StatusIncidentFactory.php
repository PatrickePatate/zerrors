<?php

namespace Database\Factories;

use App\Models\StatusPage;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusIncidentFactory extends Factory
{
    public function definition(): array
    {
        return [
            'status_page_id' => StatusPage::factory(),
            'monitor_id' => null,
            'title' => fake()->sentence(3),
            'started_at' => now()->subHour(),
            'resolved_at' => null,
            'auto' => false,
        ];
    }
}
