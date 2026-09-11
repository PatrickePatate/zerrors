<?php

namespace Tests\Feature\Fault;

use App\Livewire\RecentIssueList;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecentIssueListLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_lists_recent_issues_across_all_projects_in_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $projectA = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $projectB = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issueA = FaultIssue::factory()->create(['fault_project_id' => $projectA->id, 'title' => 'Issue in A', 'last_seen_at' => now()]);
        $issueB = FaultIssue::factory()->create(['fault_project_id' => $projectB->id, 'title' => 'Issue in B', 'last_seen_at' => now()->subMinute()]);

        Livewire::actingAs($owner)
            ->test(RecentIssueList::class, ['organization' => $organization])
            ->assertSee('Issue in A')
            ->assertSee('Issue in B');
    }

    public function test_it_updates_the_status_of_an_issue_from_another_project(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'unresolved']);

        Livewire::actingAs($owner)
            ->test(RecentIssueList::class, ['organization' => $organization])
            ->call('updateIssueStatus', $issue->id, 'resolved');

        $this->assertSame('resolved', $issue->fresh()->status);
    }

    public function test_it_assigns_an_issue_to_a_valid_member(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(RecentIssueList::class, ['organization' => $organization])
            ->call('assignIssue', $issue->id, $member->id);

        $this->assertSame($member->id, $issue->fresh()->assigned_to_user_id);
    }

    public function test_it_refuses_to_assign_a_user_outside_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $outsider = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(RecentIssueList::class, ['organization' => $organization])
            ->call('assignIssue', $issue->id, $outsider->id);

        $this->assertNull($issue->fresh()->assigned_to_user_id);
    }
}
