<?php

namespace Tests\Feature\Fault;

use App\Livewire\ProjectIssueList;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Livewire\Livewire;
use Tests\TestCase;

class IssueScalingTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array{0: Organization, 1: User, 2: FaultProject}
     */
    private function setUpProject(string $role = 'owner'): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => $role]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        return [$organization, $user, $project];
    }

    public function test_the_event_histogram_is_bucketed_in_the_database_and_cached(): void
    {
        Cache::flush();
        [$organization, $user, $project] = $this->setUpProject();
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        foreach ([10, 10, 5, 0] as $daysAgo) {
            FaultEvent::factory()->create([
                'fault_project_id' => $project->id,
                'fault_issue_id' => $issue->id,
                'occurred_at' => now()->subDays($daysAgo),
            ]);
        }

        $response = $this->actingAs($user)->get(route('organizations.issues.show', [$organization, $project, $issue]));

        $response->assertOk();
        $histogram = $response->viewData('eventHistogram');
        $this->assertCount(48, $histogram);
        $this->assertSame(4, array_sum(array_column($histogram, 'count')));
        $this->assertSame(2, $histogram[0]['count']);
        $this->assertSame(1, $histogram[47]['count']);

        FaultEvent::factory()->create(['fault_project_id' => $project->id, 'fault_issue_id' => $issue->id, 'occurred_at' => now()]);
        $cached = $this->actingAs($user)->get(route('organizations.issues.show', [$organization, $project, $issue]));
        $this->assertSame(4, array_sum(array_column($cached->viewData('eventHistogram'), 'count')));
    }

    public function test_event_filter_options_are_capped_at_fifty(): void
    {
        [$organization, $user, $project] = $this->setUpProject();
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        foreach (range(1, 60) as $i) {
            FaultEvent::factory()->create([
                'fault_project_id' => $project->id,
                'fault_issue_id' => $issue->id,
                'release' => sprintf('v%03d', $i),
            ]);
        }

        $response = $this->actingAs($user)->get(route('organizations.issues.show', [$organization, $project, $issue]));

        $this->assertCount(50, $response->viewData('eventFilterOptions')['release']);
    }

    public function test_livewire_status_and_assignment_changes_are_audit_logged(): void
    {
        [$organization, $user, $project] = $this->setUpProject();
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'status' => 'unresolved']);

        Livewire::actingAs($user)
            ->test(ProjectIssueList::class, ['organization' => $organization, 'project' => $project])
            ->call('updateIssueStatus', $issue->id, 'resolved')
            ->call('assignIssue', $issue->id, $user->id);

        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'issue.status_updated']);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'issue.assigned']);
        $log = $organization->auditLogs()->where('action', 'issue.status_updated')->first();
        $this->assertSame(['from' => 'unresolved', 'to' => 'resolved'], $log->meta);
    }

    public function test_controller_assignment_is_audit_logged(): void
    {
        [$organization, $user, $project] = $this->setUpProject('member');
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($user)->patch(
            route('organizations.issues.assign', [$organization, $project, $issue]),
            ['assigned_to_user_id' => $user->id]
        );

        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'issue.assigned']);
    }

    public function test_search_escapes_like_wildcards_and_ignores_single_characters(): void
    {
        [$organization, $user, $project] = $this->setUpProject();
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'Rate 100% exceeded', 'culprit' => 'a', 'type' => 'a']);
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'Other thing', 'culprit' => 'b', 'type' => 'b']);

        Livewire::actingAs($user)
            ->test(ProjectIssueList::class, ['organization' => $organization, 'project' => $project])
            ->set('search', '0%')
            ->assertSee('Rate 100% exceeded')
            ->assertDontSee('Other thing')
            ->set('search', '%')
            ->assertSee('Rate 100% exceeded')
            ->assertSee('Other thing');
    }

    public function test_retention_days_can_be_updated_by_an_owner_and_is_validated(): void
    {
        [$organization, $owner, $project] = $this->setUpProject();
        $url = route('organizations.projects.settings.update', [$organization, $project]);

        $this->actingAs($owner)->patch($url, ['platform' => 'php', 'retention_days' => 0])->assertSessionHasNoErrors();
        $this->assertSame(0, $project->fresh()->retention_days);

        $this->actingAs($owner)->patch($url, ['platform' => 'php', 'retention_days' => 4000])
            ->assertSessionHasErrors('retention_days');
        $this->actingAs($owner)->patch($url, ['platform' => 'php', 'retention_days' => -1])
            ->assertSessionHasErrors('retention_days');
    }

    public function test_retention_days_cannot_be_updated_by_a_member(): void
    {
        [$organization, $member, $project] = $this->setUpProject('member');

        $this->actingAs($member)->patch(
            route('organizations.projects.settings.update', [$organization, $project]),
            ['platform' => 'php', 'retention_days' => 5]
        )->assertForbidden();
    }

    public function test_the_project_page_renders_the_retention_field(): void
    {
        [$organization, $owner, $project] = $this->setUpProject();

        $this->actingAs($owner)->get(route('organizations.projects.show', [$organization, $project]))
            ->assertOk()->assertSee('name="retention_days"', false)->assertSeeText('keep events forever');
    }
}
