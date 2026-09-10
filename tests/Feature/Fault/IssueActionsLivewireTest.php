<?php

namespace Tests\Feature\Fault;

use App\Livewire\IssueActions;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\Release;
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

    public function test_it_fetches_the_commit_linked_to_the_issues_release(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'html_url' => 'https://github.com/acme/api/commit/abcdef1234567890',
                'commit' => ['message' => 'Fix the null pointer', 'author' => ['name' => 'Ada Lovelace', 'date' => '2026-01-01T12:00:00Z']],
                'files' => [['filename' => 'app/Foo.php', 'status' => 'modified', 'additions' => 3, 'deletions' => 1]],
                'stats' => ['additions' => 3, 'deletions' => 1],
            ], 200),
        ]);

        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'github_token' => 'ghp_secret',
        ]);
        $release = Release::factory()->create([
            'fault_project_id' => $project->id,
            'version' => 'v1.4.2',
            'commit_sha' => 'abcdef1234567890',
        ]);
        $issue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'first_seen_release' => 'v1.4.2',
        ]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->call('fetchCommit')
            ->assertSet('commitError', null);

        $release->refresh();
        $this->assertSame('Fix the null pointer', $release->commit_message);
        $this->assertSame('Ada Lovelace', $release->commit_author);
        $this->assertSame(3, $release->commit_additions);
    }

    public function test_it_reports_an_error_when_the_issue_has_no_linked_commit(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'first_seen_release' => null]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->call('fetchCommit')
            ->assertSet('commitError', fn ($value) => ! empty($value));
    }
}
