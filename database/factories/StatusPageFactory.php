<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;

class StatusPageFactory extends Factory
{
    public function definition(): array
    {
        return [
            'organization_id' => Organization::factory(),
            'slug' => fake()->unique()->slug(2),
            'title' => fake()->company().' Status',
            'description' => null,
            'is_public' => true,
        ];
    }

    public function private(): static
    {
        return $this->state(fn () => ['is_public' => false]);
    }
}
