<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class OrganizationFactory extends Factory
{
    public function definition(): array
    {
        $name = $this->faker->unique()->company();

        return [
            'name' => $name,
            'slug' => Str::slug($name).'-'.Str::lower(Str::random(5)),
        ];
    }

    public function withGithubApp(): static
    {
        return $this->state([
            'github_installation_id' => (string) $this->faker->randomNumber(8),
            'github_account_login' => $this->faker->userName(),
            'github_account_type' => 'Organization',
            'github_connected_at' => now(),
        ]);
    }

    public function withSlackApp(): static
    {
        return $this->state([
            'slack_team_id' => 'T'.Str::upper(Str::random(8)),
            'slack_team_name' => $this->faker->company(),
            'slack_bot_token' => 'xoxb-'.Str::random(24),
            'slack_authed_user_id' => 'U'.Str::upper(Str::random(8)),
            'slack_connected_at' => now(),
        ]);
    }
}
