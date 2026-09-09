<?php

namespace Database\Factories;

use App\Models\Organization;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class FaultProjectFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->word().' app';

        return [
            'organization_id' => Organization::factory(),
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(6)),
            'public_key' => Str::random(32),
            'secret_key' => null,
            'retention_days' => 90,
        ];
    }
}
