<?php

namespace Tests\Feature\Fault;

use App\Livewire\IssueShareModal;
use App\Models\FaultIssue;
use App\Models\FaultIssueShareLink;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IssueShareLinkTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_create_a_public_share_link(): void
    {
        [$organization, $actor, $project, $issue] = $this->makeIssue();

        Livewire::actingAs($actor)
            ->test(IssueShareModal::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->set('visibility', 'public')
            ->call('createLink')
            ->assertHasNoErrors();

        $link = $issue->shareLinks()->first();
        $this->assertNotNull($link);
        $this->assertSame('public', $link->visibility);
        $this->assertNull($link->password_hash);
        $this->assertNull($link->expires_at);
    }

    public function test_a_member_can_create_a_password_protected_link(): void
    {
        [$organization, $actor, $project, $issue] = $this->makeIssue();

        Livewire::actingAs($actor)
            ->test(IssueShareModal::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->set('visibility', 'password')
            ->set('password', 'sharesecret')
            ->call('createLink')
            ->assertHasNoErrors();

        $link = $issue->shareLinks()->first();
        $this->assertSame('password', $link->visibility);
        $this->assertNotNull($link->password_hash);
        $this->assertNotSame('sharesecret', $link->password_hash);
        $this->assertTrue($link->checkPassword('sharesecret'));
    }

    public function test_a_member_can_create_a_temporary_link_with_expiry(): void
    {
        [$organization, $actor, $project, $issue] = $this->makeIssue();

        Livewire::actingAs($actor)
            ->test(IssueShareModal::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->set('visibility', 'temporary')
            ->set('duration', '86400')
            ->call('createLink')
            ->assertHasNoErrors();

        $link = $issue->shareLinks()->first();
        $this->assertSame('temporary', $link->visibility);
        $this->assertNotNull($link->expires_at);
        $this->assertTrue($link->expires_at->between(now()->addDay()->subMinute(), now()->addDay()->addMinute()));
    }

    public function test_guest_can_view_a_public_share_link_without_authentication(): void
    {
        [, , , $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->create();

        $this->get(route('share.show', $link->token))
            ->assertOk()
            ->assertSee($issue->title)
            ->assertDontSee('Resolve')
            ->assertDontSee('Assign to');
    }

    public function test_guest_sees_password_prompt_until_unlocked(): void
    {
        [, , , $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->password('correct-password')->create();

        $this->get(route('share.show', $link->token))
            ->assertOk()
            ->assertDontSee($issue->title)
            ->assertSee('password protected');
    }

    public function test_guest_with_wrong_password_is_rejected(): void
    {
        [, , , $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->password('correct-password')->create();

        $this->post(route('share.unlock', $link->token), ['password' => 'wrong'])
            ->assertSessionHasErrors('password');

        $this->get(route('share.show', $link->token))->assertDontSee($issue->title);
    }

    public function test_guest_with_correct_password_can_view_issue(): void
    {
        [, , , $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->password('correct-password')->create();

        $this->post(route('share.unlock', $link->token), ['password' => 'correct-password'])
            ->assertRedirect(route('share.show', $link->token));

        $this->get(route('share.show', $link->token))
            ->assertOk()
            ->assertSee($issue->title);
    }

    public function test_expired_temporary_link_shows_expired_page(): void
    {
        [, , , $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->expired()->create();

        $this->get(route('share.show', $link->token))
            ->assertOk()
            ->assertSee('expired')
            ->assertDontSee($issue->title);
    }

    public function test_revoked_link_shows_invalid_page(): void
    {
        [, , , $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->revoked()->create();

        $this->get(route('share.show', $link->token))
            ->assertOk()
            ->assertSee('invalid')
            ->assertDontSee($issue->title);
    }

    public function test_creator_can_revoke_a_share_link(): void
    {
        [$organization, $actor, $project, $issue] = $this->makeIssue();
        $link = FaultIssueShareLink::factory()->for($issue, 'issue')->for($actor, 'creator')->create();

        Livewire::actingAs($actor)
            ->test(IssueShareModal::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->call('revoke', $link->id);

        $this->assertNotNull($link->fresh()->revoked_at);
    }

    public function test_unauthenticated_user_cannot_reach_the_issue_page_hosting_the_share_modal(): void
    {
        [$organization, , $project, $issue] = $this->makeIssue();

        $this->get(route('organizations.issues.show', [$organization, $project, $issue]))
            ->assertRedirect(route('login'));
    }

    /**
     * @return array{0: Organization, 1: User, 2: FaultProject, 3: FaultIssue}
     */
    private function makeIssue(): array
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $organization->users()->attach($actor->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        return [$organization, $actor, $project, $issue];
    }
}
