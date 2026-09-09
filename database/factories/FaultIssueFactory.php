<?php

namespace Database\Factories;

use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaultIssueFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'fingerprint' => $this->faker->sha1(),
            'type' => 'Exception',
            'title' => $this->faker->sentence(4),
            'culprit' => $this->faker->word().'::'.$this->faker->word().'()',
            'level' => 'error',
            'status' => 'unresolved',
            'times_seen' => 1,
            'first_seen_at' => now(),
            'last_seen_at' => now(),
        ];
    }
}
