<?php

namespace Tests\Feature\Fault;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueAssignmentTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_member_can_assign_an_issue_to_another_member(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $assignee = User::factory()->create();
        $organization->users()->attach($actor->id, ['role' => 'member']);
        $organization->users()->attach($assignee->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($actor)->patch(
            route('organizations.issues.assign', [$organization, $project, $issue]),
            ['assigned_to_user_id' => $assignee->id]
        )->assertRedirect();

        $this->assertSame($assignee->id, $issue->fresh()->assigned_to_user_id);
    }

    public function test_an_issue_can_be_unassigned(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $organization->users()->attach($actor->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'assigned_to_user_id' => $actor->id]);

        $this->actingAs($actor)->patch(
            route('organizations.issues.assign', [$organization, $project, $issue]),
            ['assigned_to_user_id' => '']
        );

        $this->assertNull($issue->fresh()->assigned_to_user_id);
    }

    public function test_cannot_assign_to_a_user_outside_the_organization(): void
    {
        $organization = Organization::factory()->create();
        $actor = User::factory()->create();
        $outsider = User::factory()->create();
        $organization->users()->attach($actor->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($actor)->patch(
            route('organizations.issues.assign', [$organization, $project, $issue]),
            ['assigned_to_user_id' => $outsider->id]
        )->assertStatus(422);

        $this->assertNull($issue->fresh()->assigned_to_user_id);
    }
}
