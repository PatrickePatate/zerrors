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

    public function test_owner_of_both_organizations_can_move_a_project(): void
    {
        $source = Organization::factory()->create();
        $destination = Organization::factory()->create();
        $owner = User::factory()->create();
        $source->users()->attach($owner->id, ['role' => 'owner']);
        $destination->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $source->id]);

        $this->actingAs($owner)
            ->post(route('organizations.projects.transfer', [$source, $project]), [
                'organization_id' => $destination->id,
            ])
            ->assertRedirect(route('organizations.projects.show', [$destination, $project]));

        $this->assertSame($destination->id, $project->fresh()->organization_id);
    }

    public function test_admin_cannot_move_a_project(): void
    {
        $source = Organization::factory()->create();
        $destination = Organization::factory()->create();
        $admin = User::factory()->create();
        $source->users()->attach($admin->id, ['role' => 'admin']);
        $destination->users()->attach($admin->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $source->id]);

        $this->actingAs($admin)
            ->post(route('organizations.projects.transfer', [$source, $project]), [
                'organization_id' => $destination->id,
            ])
            ->assertForbidden();

        $this->assertSame($source->id, $project->fresh()->organization_id);
    }

    public function test_owner_cannot_move_a_project_to_an_organization_they_do_not_own(): void
    {
        $source = Organization::factory()->create();
        $destination = Organization::factory()->create();
        $owner = User::factory()->create();
        $source->users()->attach($owner->id, ['role' => 'owner']);
        $destination->users()->attach($owner->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $source->id]);

        $this->actingAs($owner)
            ->post(route('organizations.projects.transfer', [$source, $project]), [
                'organization_id' => $destination->id,
            ])
            ->assertForbidden();

        $this->assertSame($source->id, $project->fresh()->organization_id);
    }
}
