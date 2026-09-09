<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\Release;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ReleaseTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_mark_a_release_as_deployed(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($owner)
            ->post(route('organizations.projects.releases.store', [$organization, $project]), ['version' => 'v1.2.3'])
            ->assertRedirect();

        $this->assertDatabaseHas('releases', ['fault_project_id' => $project->id, 'version' => 'v1.2.3']);
    }

    public function test_member_cannot_mark_a_release(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->post(route('organizations.projects.releases.store', [$organization, $project]), ['version' => 'v1.2.3'])
            ->assertForbidden();
    }

    public function test_owner_can_remove_a_release(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $release = Release::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($owner)
            ->delete(route('organizations.projects.releases.destroy', [$organization, $project, $release]))
            ->assertRedirect();

        $this->assertModelMissing($release);
    }
}
