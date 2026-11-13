<?php

namespace Database\Factories;

use App\Models\Release;
use Illuminate\Database\Eloquent\Factories\Factory;

class ReleaseCommitFactory extends Factory
{
    public function definition(): array
    {
        return [
            'release_id' => Release::factory(),
            'sha' => fake()->sha1(),
            'message' => fake()->sentence(),
            'author' => fake()->name(),
            'url' => fake()->url(),
            'files' => ['app/Example.php'],
        ];
    }
}
