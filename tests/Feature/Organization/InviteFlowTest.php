<?php

namespace Tests\Feature\Organization;

use App\Mail\OrganizationInviteMail;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class InviteFlowTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_invite_a_new_member(): void
    {
        Mail::fake();

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->post(route('organizations.invites.store', $organization), [
            'email' => 'new@example.com',
            'role' => 'member',
        ]);

        $response->assertSessionHas('invite_link');
        $this->assertDatabaseHas('organization_invites', ['organization_id' => $organization->id, 'email' => 'new@example.com']);
        Mail::assertQueued(OrganizationInviteMail::class, fn ($mail) => $mail->hasTo('new@example.com'));
    }

    public function test_member_cannot_invite(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);

        $response = $this->actingAs($member)->post(route('organizations.invites.store', $organization), [
            'email' => 'new@example.com',
            'role' => 'member',
        ]);

        $response->assertForbidden();
    }

    public function test_accepting_an_invite_as_a_new_user_joins_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $invite = $organization->invites()->create(['email' => 'invitee@example.com', 'role' => 'admin']);

        $this->get(route('invites.accept', $invite->token))->assertOk();

        $response = $this->post('/register', [
            'name' => 'Invitee',
            'email' => 'invitee@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'invite' => $invite->token,
        ]);

        $response->assertRedirect(route('dashboard'));

        $user = User::where('email', 'invitee@example.com')->firstOrFail();
        $this->assertSame('admin', $organization->fresh()->roleFor($user));
        $this->assertNotNull($invite->fresh()->accepted_at);
    }

    public function test_expired_invite_shows_invalid_page(): void
    {
        $organization = Organization::factory()->create();
        $invite = $organization->invites()->create([
            'email' => 'late@example.com',
            'role' => 'member',
            'expires_at' => now()->subDay(),
        ]);

        $this->get(route('invites.accept', $invite->token))
            ->assertOk()
            ->assertSeeText('This invite is no longer valid');
    }
}
