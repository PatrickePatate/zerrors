<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_rename_the_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Old Name']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('organizations.settings.update', $organization), ['name' => 'New Name'])
            ->assertRedirect();

        $this->assertSame('New Name', $organization->fresh()->name);
    }

    public function test_member_cannot_rename_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->patch(route('organizations.settings.update', $organization), ['name' => 'Hacked'])
            ->assertForbidden();
    }

    public function test_owner_can_delete_the_organization_by_typing_its_name(): void
    {
        $organization = Organization::factory()->create(['name' => 'Doomed Inc']);
        $otherOrganization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $otherOrganization->users()->attach($owner->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->delete(route('organizations.settings.destroy', $organization), [
            'confirm_name' => 'Doomed Inc',
        ]);

        $response->assertRedirect(route('dashboard'));
        $this->assertDatabaseMissing('organizations', ['id' => $organization->id]);
    }

    public function test_deleting_the_organization_requires_typing_the_exact_name(): void
    {
        $organization = Organization::factory()->create(['name' => 'Doomed Inc']);
        $otherOrganization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $otherOrganization->users()->attach($owner->id, ['role' => 'member']);

        $response = $this->actingAs($owner)->delete(route('organizations.settings.destroy', $organization), [
            'confirm_name' => 'wrong',
        ]);

        $response->assertSessionHasErrors('confirm_name');
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_owner_cannot_delete_their_only_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Doomed Inc']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->delete(route('organizations.settings.destroy', $organization), [
            'confirm_name' => 'Doomed Inc',
        ]);

        $response->assertSessionHasErrors('confirm_name');
        $this->assertDatabaseHas('organizations', ['id' => $organization->id]);
    }

    public function test_admin_cannot_delete_the_organization(): void
    {
        $organization = Organization::factory()->create(['name' => 'Doomed Inc']);
        $admin = User::factory()->create();
        $organization->users()->attach($admin->id, ['role' => 'admin']);

        $this->actingAs($admin)
            ->delete(route('organizations.settings.destroy', $organization), ['confirm_name' => 'Doomed Inc'])
            ->assertForbidden();
    }

    public function test_member_can_leave_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)
            ->post(route('organizations.leave', $organization))
            ->assertRedirect(route('dashboard'));

        $this->assertNull($organization->fresh()->roleFor($member));
    }

    public function test_last_owner_cannot_leave(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('organizations.leave', $organization))
            ->assertSessionHasErrors('leave');

        $this->assertSame('owner', $organization->fresh()->roleFor($owner));
    }

    public function test_owner_can_change_a_members_role(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($owner)
            ->patch(route('organizations.members.updateRole', [$organization, $member]), ['role' => 'admin'])
            ->assertRedirect();

        $this->assertSame('admin', $organization->fresh()->roleFor($member));
    }

    public function test_admin_cannot_promote_a_member_to_owner(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($admin->id, ['role' => 'admin']);
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($admin)
            ->patch(route('organizations.members.updateRole', [$organization, $member]), ['role' => 'owner'])
            ->assertForbidden();
    }

    public function test_admin_cannot_change_an_owners_role(): void
    {
        $organization = Organization::factory()->create();
        $admin = User::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($admin->id, ['role' => 'admin']);
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($admin)
            ->patch(route('organizations.members.updateRole', [$organization, $owner]), ['role' => 'member'])
            ->assertForbidden();
    }

    public function test_demoting_the_last_owner_is_blocked(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->patch(route('organizations.members.updateRole', [$organization, $owner]), ['role' => 'admin'])
            ->assertSessionHasErrors('user');

        $this->assertSame('owner', $organization->fresh()->roleFor($owner));
    }
}
