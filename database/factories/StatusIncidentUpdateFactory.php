<?php

namespace Database\Factories;

use App\Enums\StatusIncidentState;
use App\Models\StatusIncident;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusIncidentUpdateFactory extends Factory
{
    public function definition(): array
    {
        return [
            'incident_id' => StatusIncident::factory(),
            'body' => fake()->sentence(),
            'status' => StatusIncidentState::Investigating,
        ];
    }
}
