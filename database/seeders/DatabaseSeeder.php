<?php

namespace Database\Seeders;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Database\Console\Seeds\WithoutModelEvents;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    use WithoutModelEvents;

    /**
     * Seed the application's database.
     */
    public function run(): void
    {
        // User::factory(10)->create();

        $user = User::factory()->create([
            'name' => 'Test User',
            'email' => 'test@example.com',
        ]);

        $organization = Organization::factory()->create(['name' => 'main']);
        $organization->users()->attach($user->id, ['role' => 'owner']);

        FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'name' => 'test',
            'platform' => 'laravel',
        ]);

        $this->call(FaultIssueSeeder::class);
    }
}
