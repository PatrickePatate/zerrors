<?php

namespace Tests\Feature\Fault;

use App\Livewire\IssueActions;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class IssueActionsLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_updates_the_issue_status(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'unresolved']);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->call('updateStatus', 'resolved')
            ->assertSet('issue.status', 'resolved');

        $this->assertSame('resolved', $issue->fresh()->status);
    }

    public function test_it_assigns_the_issue_to_a_valid_member(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->set('assignedToUserId', (string) $member->id);

        $this->assertSame($member->id, $issue->fresh()->assigned_to_user_id);
    }

    public function test_it_creates_a_linked_github_issue(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response(['html_url' => 'https://github.com/acme/api/issues/7', 'number' => 7], 201),
        ]);

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'github_token' => 'ghp_secret',
        ]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->call('createGithubIssue')
            ->assertSet('githubError', null);

        $this->assertSame(7, $issue->fresh()->github_issue_number);
    }
}
