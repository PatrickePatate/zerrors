<?php

namespace Tests\Feature\Fault;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueFilteringTest extends TestCase
{
    use RefreshDatabase;

    public function test_issues_can_be_searched_by_title(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'PaymentFailedException']);
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'ValidationException']);

        $response = $this->actingAs($user)->get(
            route('organizations.projects.show', [$organization, $project]).'?q=Payment'
        );

        $response->assertOk()->assertSeeText('PaymentFailedException')->assertDontSeeText('ValidationException');
    }

    public function test_issues_can_be_filtered_by_status(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'ResolvedOne', 'status' => 'resolved']);
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'UnresolvedOne', 'status' => 'unresolved']);

        $response = $this->actingAs($user)->get(
            route('organizations.projects.show', [$organization, $project]).'?status=resolved'
        );

        $response->assertOk()->assertSeeText('ResolvedOne')->assertDontSeeText('UnresolvedOne');
    }

    public function test_issues_can_be_filtered_by_unassigned(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'AssignedOne', 'assigned_to_user_id' => $user->id]);
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'FreeOne', 'assigned_to_user_id' => null]);

        $response = $this->actingAs($user)->get(
            route('organizations.projects.show', [$organization, $project]).'?assigned=unassigned'
        );

        $response->assertOk()->assertSeeText('FreeOne')->assertDontSeeText('AssignedOne');
    }
}
