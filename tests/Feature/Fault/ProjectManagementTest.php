<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectManagementTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rotate_the_project_dsn_key(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $originalKey = $project->public_key;

        $this->actingAs($owner)
            ->post(route('organizations.projects.rotateKey', [$organization, $project]))
            ->assertRedirect();

        $this->assertNotSame($originalKey, $project->fresh()->public_key);
    }

    public function test_member_cannot_rotate_the_project_dsn_key(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $originalKey = $project->public_key;

        $this->actingAs($member)
            ->post(route('organizations.projects.rotateKey', [$organization, $project]))
            ->assertForbidden();

        $this->assertSame($originalKey, $project->fresh()->public_key);
    }
}
