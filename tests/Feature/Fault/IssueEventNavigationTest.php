<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IssueEventNavigationTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_issue_page_defaults_to_the_newest_event(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(2),
            'message' => 'oldest message',
        ]);
        $newest = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now(),
            'message' => 'newest message',
        ]);

        $this->actingAs($user)
            ->get(route('organizations.issues.show', [$organization, $project, $issue]))
            ->assertOk()
            ->assertSeeText('Event 2 of 2')
            ->assertSeeText('newest message');
    }

    public function test_it_can_navigate_to_a_specific_event_and_shows_correct_position(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $oldest = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(2),
            'message' => 'oldest message',
        ]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDay(),
            'message' => 'middle message',
        ]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now(),
            'message' => 'newest message',
        ]);

        $this->actingAs($user)
            ->get(route('organizations.issues.events.show', [$organization, $project, $issue, $oldest->id]))
            ->assertOk()
            ->assertSeeText('Event 1 of 3')
            ->assertSeeText('oldest message');
    }

    public function test_the_oldest_and_latest_keywords_jump_to_the_extremes(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now()->subDays(2),
            'message' => 'oldest message',
        ]);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'occurred_at' => now(),
            'message' => 'newest message',
        ]);

        $this->actingAs($user)
            ->get(route('organizations.issues.events.show', [$organization, $project, $issue, 'oldest']))
            ->assertOk()
            ->assertSeeText('oldest message');

        $this->actingAs($user)
            ->get(route('organizations.issues.events.show', [$organization, $project, $issue, 'latest']))
            ->assertOk()
            ->assertSeeText('newest message');
    }

    public function test_requesting_an_event_from_another_issue_is_not_found(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);
        $otherIssue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $otherEvent = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $otherIssue->id,
        ]);

        $this->actingAs($user)
            ->get(route('organizations.issues.events.show', [$organization, $project, $issue, $otherEvent->id]))
            ->assertNotFound();
    }
}
