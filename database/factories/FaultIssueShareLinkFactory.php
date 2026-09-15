<?php

namespace Database\Factories;

use App\Models\FaultIssue;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

class FaultIssueShareLinkFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fault_issue_id' => FaultIssue::factory(),
            'created_by_user_id' => User::factory(),
            'visibility' => 'public',
            'password_hash' => null,
            'expires_at' => null,
            'revoked_at' => null,
        ];
    }

    public function password(string $password = 'secret123'): static
    {
        return $this->state(fn () => [
            'visibility' => 'password',
            'password_hash' => bcrypt($password),
        ]);
    }

    public function temporary(): static
    {
        return $this->state(fn () => [
            'visibility' => 'temporary',
            'expires_at' => now()->addDay(),
        ]);
    }

    public function expired(): static
    {
        return $this->state(fn () => [
            'visibility' => 'temporary',
            'expires_at' => now()->subDay(),
        ]);
    }

    public function revoked(): static
    {
        return $this->state(fn () => [
            'revoked_at' => now(),
        ]);
    }
}
