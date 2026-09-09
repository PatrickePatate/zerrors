<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectPlatformTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_project_can_be_created_with_a_platform(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('organizations.projects.store', $organization), ['name' => 'API', 'platform' => 'laravel'])
            ->assertRedirect();

        $this->assertDatabaseHas('fault_projects', ['name' => 'API', 'platform' => 'laravel']);
    }

    public function test_owner_can_update_the_project_platform_and_github_settings(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id, 'platform' => 'other']);

        $this->actingAs($owner)
            ->patch(route('organizations.projects.settings.update', [$organization, $project]), [
                'platform' => 'php',
                'github_repo' => 'acme/api',
                'github_token' => 'ghp_secret',
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('php', $project->platform);
        $this->assertSame('acme/api', $project->github_repo);
        $this->assertSame('ghp_secret', $project->github_token);
    }

    public function test_member_cannot_update_project_settings(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->patch(route('organizations.projects.settings.update', [$organization, $project]), ['platform' => 'php'])
            ->assertForbidden();
    }
}
