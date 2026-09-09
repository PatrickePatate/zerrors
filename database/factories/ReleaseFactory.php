<?php

namespace Database\Factories;

use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReleaseFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'version' => 'v'.$this->faker->numberBetween(1, 9).'.'.$this->faker->numberBetween(0, 20).'.'.$this->faker->numberBetween(0, 20),
            'notes' => $this->faker->sentence(),
            'deployed_at' => now(),
        ];
    }
}
