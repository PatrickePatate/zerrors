<?php

namespace Tests\Feature\Organization;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AccessControlTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login(): void
    {
        $organization = Organization::factory()->create();

        $this->get(route('organizations.projects.index', $organization))->assertRedirect(route('login'));
    }

    public function test_non_member_cannot_view_another_organizations_projects(): void
    {
        $organization = Organization::factory()->create();
        $outsider = User::factory()->create();

        $this->actingAs($outsider)
            ->get(route('organizations.projects.index', $organization))
            ->assertForbidden();
    }

    public function test_member_can_view_their_organizations_projects(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->get(route('organizations.projects.show', [$organization, $project]))
            ->assertOk()
            ->assertSeeText($project->name);
    }

    public function test_owner_cannot_remove_the_last_owner(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)
            ->delete(route('organizations.members.destroy', [$organization, $owner]));

        $response->assertSessionHasErrors('user');
        $this->assertSame('owner', $organization->fresh()->roleFor($owner));
    }
}
