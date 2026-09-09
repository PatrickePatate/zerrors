<?php

namespace Database\Factories;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FaultEventFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'fault_issue_id' => FaultIssue::factory(),
            'event_id' => (string) Str::uuid(),
            'level' => 'error',
            'message' => $this->faker->sentence(),
            'payload' => ['message' => 'test'],
            'occurred_at' => now(),
        ];
    }
}
