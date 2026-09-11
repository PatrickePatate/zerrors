<?php

namespace Tests\Feature\Fault;

use App\Livewire\IssueActions;
use App\Models\FaultEvent;
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

    public function test_it_unassigns_the_issue(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'assigned_to_user_id' => $owner->id,
        ]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->set('assignedToUserId', '')
            ->assertHasNoErrors();

        $this->assertNull($issue->fresh()->assigned_to_user_id);
    }

    public function test_it_shows_a_quick_peek_of_the_events_user_browser_and_context_url(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);
        $event = FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'payload' => ['user' => ['email' => 'demo@example.com']],
            'request' => [
                'url' => 'https://example.test/api/list',
                'headers' => [
                    'user-agent' => ['Mozilla/5.0 Gecko/20100101 Firefox/154.0'],
                    'x-current-page-url' => ['https://example.test/dashboard'],
                ],
            ],
        ]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue, 'event' => $event])
            ->assertSee('demo@example.com')
            ->assertSee('Firefox')
            ->assertSee('https://example.test/dashboard');
    }

    public function test_it_hides_the_quick_peek_without_an_event(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->assertDontSee('Firefox');
    }

    public function test_it_uses_real_member_ids_as_assignment_option_values(): void
    {
        // The "Assigned to" select is a custom Alpine component: its options
        // are JSON embedded in the rendered HTML, keyed by member id. A prior
        // bug built that array via `[...$members->pluck('name', 'id')]`,
        // which silently discards integer keys and renumbers options from 0,
        // so picking a member sent the wrong id (or one belonging to nobody).
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $html = Livewire::actingAs($owner)
            ->test(IssueActions::class, ['organization' => $organization, 'project' => $project, 'issue' => $issue])
            ->html();

        preg_match("/JSON\.parse\('(.+?)'\)/", $html, $matches);
        $items = json_decode(json_decode('"'.$matches[1].'"'), true);

        $memberOption = collect($items)->firstWhere('title', $member->name);

        $this->assertSame((string) $member->id, $memberOption['value']);
    }

    public function test_it_creates_a_linked_github_issue(): void
    {
        Http::fake([
            'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'ghs_installation_token'], 201),
            'api.github.com/repos/*' => Http::response(['html_url' => 'https://github.com/acme/api/issues/7', 'number' => 7], 201),
        ]);

        $organization = Organization::factory()->withGithubApp()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
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
            'api.github.com/app/installations/*/access_tokens' => Http::response(['token' => 'ghs_installation_token'], 201),
            'api.github.com/repos/*' => Http::response([
                'html_url' => 'https://github.com/acme/api/commit/abcdef1234567890',
                'commit' => ['message' => 'Fix the null pointer', 'author' => ['name' => 'Ada Lovelace', 'date' => '2026-01-01T12:00:00Z']],
                'files' => [['filename' => 'app/Foo.php', 'status' => 'modified', 'additions' => 3, 'deletions' => 1]],
                'stats' => ['additions' => 3, 'deletions' => 1],
            ], 200),
        ]);

        $organization = Organization::factory()->withGithubApp()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
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
