<?php

namespace Database\Factories;

use App\Models\FaultFeedback;
use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<FaultFeedback>
 */
class FaultFeedbackFactory extends Factory
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
            'event_id' => (string) Str::uuid(),
            'name' => fake()->name(),
            'email' => fake()->safeEmail(),
            'comments' => fake()->sentence(),
        ];
    }
}
