<?php

namespace Tests\Feature\Organization;

use App\Livewire\MembersManager;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Livewire\Livewire;
use Tests\TestCase;

class MembersManagerLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_a_member(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $organization])
            ->set('email', 'new@example.com')
            ->set('role', 'member')
            ->call('invite')
            ->assertHasNoErrors()
            ->assertSet('inviteLink', fn ($link) => str_contains($link, '/invites/'));

        $this->assertDatabaseHas('organization_invites', ['email' => 'new@example.com']);
    }

    public function test_it_rejects_inviting_an_existing_member(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $existing = User::factory()->create(['email' => 'existing@example.com']);
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($existing->id, ['role' => 'member']);

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $organization])
            ->set('email', 'existing@example.com')
            ->set('role', 'member')
            ->call('invite')
            ->assertHasErrors('email');
    }

    public function test_owner_can_change_a_members_role(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $organization])
            ->call('updateRole', $member->id, 'admin');

        $this->assertSame('admin', $organization->fresh()->roleFor($member));
    }

    public function test_it_prevents_demoting_the_last_owner(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $organization])
            ->call('updateRole', $owner->id, 'member')
            ->assertSet('removalError', 'An organization needs at least one owner.');

        $this->assertSame('owner', $organization->fresh()->roleFor($owner));
    }

    public function test_owner_can_remove_a_member(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $organization])
            ->call('removeMember', $member->id);

        $this->assertNull($organization->fresh()->roleFor($member));
    }

    public function test_owner_can_revoke_a_pending_invite(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $invite = $organization->invites()->create(['email' => 'invitee@example.com', 'role' => 'member']);

        Livewire::actingAs($owner)
            ->test(MembersManager::class, ['organization' => $organization])
            ->call('revokeInvite', $invite->id);

        $this->assertModelMissing($invite);
    }

    public function test_member_cannot_manage_members(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $other = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $organization->users()->attach($other->id, ['role' => 'member']);

        Livewire::actingAs($member)
            ->test(MembersManager::class, ['organization' => $organization])
            ->call('removeMember', $other->id)
            ->assertStatus(403);
    }
}
