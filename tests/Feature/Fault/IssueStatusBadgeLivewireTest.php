<?php

namespace Tests\Feature\Fault;

use App\Livewire\IssueStatusBadge;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class IssueStatusBadgeLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_shows_the_issues_current_status(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'unresolved']);

        Livewire::actingAs($owner)
            ->test(IssueStatusBadge::class, ['issue' => $issue])
            ->assertSee('Unresolved');
    }

    public function test_it_refreshes_when_notified_the_matching_issue_changed_status(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'unresolved']);

        $issue->update(['status' => 'resolved']);

        Livewire::actingAs($owner)
            ->test(IssueStatusBadge::class, ['issue' => $issue])
            ->dispatch('issue-status-updated', issueId: $issue->id)
            ->assertSet('issue.status', 'resolved')
            ->assertSee('Resolved');
    }

    public function test_it_ignores_the_event_when_it_is_for_a_different_issue(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'unresolved']);
        $otherIssue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'resolved']);

        Livewire::actingAs($owner)
            ->test(IssueStatusBadge::class, ['issue' => $issue])
            ->dispatch('issue-status-updated', issueId: $otherIssue->id)
            ->assertSet('issue.status', 'unresolved')
            ->assertSee('Unresolved');
    }
}
