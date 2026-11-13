<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\Release;
use App\Models\ReleaseCommit;
use App\Observers\FaultIssueGithubObserver;
use App\Support\Fault\GithubIssueSync;
use App\Support\Fault\SuspectCommitFinder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubSyncTest extends TestCase
{
    use RefreshDatabase;

    private FaultProject $project;

    private FaultIssue $issue;

    protected function setUp(): void
    {
        parent::setUp();

        $organization = Organization::factory()->withGithubApp()->create();
        $this->project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'production_branch' => 'main',
        ]);
        $this->issue = FaultIssue::factory()->create([
            'fault_project_id' => $this->project->id,
            'status' => 'unresolved',
            'github_issue_number' => 7,
        ]);

        Cache::put("github-installation-token:{$organization->github_installation_id}", 'tok', 600);
    }

    private function send(array $payload, string $event = 'issues', ?string $delivery = null)
    {
        $payload['repository'] = ['full_name' => 'acme/api'];
        $body = json_encode($payload);
        $headers = [
            'X-GitHub-Event' => $event,
            'X-Hub-Signature-256' => 'sha256='.hash_hmac('sha256', $body, config('services.github.webhook_secret')),
        ];

        if ($delivery) {
            $headers['X-GitHub-Delivery'] = $delivery;
        }

        return $this->call('POST', '/api/webhooks/github', [], [], [], $this->transformHeadersToServerVars($headers + ['CONTENT_TYPE' => 'application/json']), $body);
    }

    public function test_closed_and_reopened_actions_update_the_issue_without_calling_github(): void
    {
        Http::fake();
        FaultIssue::observe(FaultIssueGithubObserver::class);

        $this->send(['action' => 'closed', 'issue' => ['number' => 7]])->assertOk();
        $this->assertSame('resolved', $this->issue->fresh()->status);

        $this->send(['action' => 'reopened', 'issue' => ['number' => 7]])->assertOk();
        $this->assertSame('unresolved', $this->issue->fresh()->status);

        Http::assertNothingSent();
    }

    public function test_unknown_issues_and_other_actions_are_ignored(): void
    {
        $this->send(['action' => 'closed', 'issue' => ['number' => 999]])->assertOk();
        $this->send(['action' => 'labeled', 'issue' => ['number' => 7]])->assertOk();

        $this->assertSame('unresolved', $this->issue->fresh()->status);
    }

    public function test_unsupported_events_return_ok_and_duplicates_are_ignored(): void
    {
        $this->send([], 'star')->assertOk();

        $this->send(['action' => 'closed', 'issue' => ['number' => 7]], 'issues', 'd-1')->assertOk();
        $this->issue->fresh()->forceFill(['status' => 'unresolved'])->saveQuietly();
        $this->send(['action' => 'closed', 'issue' => ['number' => 7]], 'issues', 'd-1')->assertOk();

        $this->assertSame('unresolved', $this->issue->fresh()->status);
    }

    public function test_push_stores_release_commits(): void
    {
        $this->send([
            'ref' => 'refs/heads/main',
            'head_commit' => ['id' => 'abcdef1234567890', 'message' => 'x'],
            'commits' => [[
                'id' => 'abcdef1234567890', 'message' => "Fix\n\nbody", 'url' => 'https://example.test/c',
                'author' => ['name' => 'Ada'], 'added' => ['a.php'], 'modified' => ['app/Foo.php'], 'removed' => [],
            ]],
        ], 'push')->assertCreated();

        $commit = ReleaseCommit::first();
        $this->assertSame('Ada', $commit->author);
        $this->assertSame(['a.php', 'app/Foo.php'], $commit->files);
    }

    private function issueWithRelease(string $framePath): void
    {
        $release = Release::factory()->create(['fault_project_id' => $this->project->id, 'version' => 'v1']);
        ReleaseCommit::factory()->create(['release_id' => $release->id, 'files' => ['app/Foo.php']]);
        $this->issue->update(['first_seen_release' => 'v1']);
        FaultEvent::factory()->create([
            'fault_project_id' => $this->project->id,
            'fault_issue_id' => $this->issue->id,
            'payload' => ['exception' => ['values' => [['stacktrace' => ['frames' => [
                ['filename' => $framePath, 'in_app' => true],
            ]]]]]],
        ]);
    }

    public function test_suspect_commits_match_on_path_suffix(): void
    {
        $this->issueWithRelease('/var/www/app/app/Foo.php');

        $found = app(SuspectCommitFinder::class)->find($this->issue->fresh());

        $this->assertCount(1, $found);
        $this->assertSame('app/Foo.php', $found->first()->matched_file);
    }

    public function test_suspect_commits_are_empty_without_a_match(): void
    {
        $this->issueWithRelease('/var/www/app/app/Bar.php');

        $this->assertCount(0, app(SuspectCommitFinder::class)->find($this->issue->fresh()));
    }

    public function test_sync_status_patches_the_github_issue(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 200)]);

        $this->issue->forceFill(['status' => 'resolved']);
        app(GithubIssueSync::class)->syncStatus($this->issue);

        Http::assertSent(fn ($r) => $r->method() === 'PATCH'
            && str_ends_with($r->url(), '/repos/acme/api/issues/7')
            && $r['state'] === 'closed');
    }

    public function test_comment_regression_posts_a_comment(): void
    {
        Http::fake(['api.github.com/*' => Http::response([], 201)]);

        app(GithubIssueSync::class)->commentRegression($this->issue);

        Http::assertSent(fn ($r) => str_ends_with($r->url(), '/issues/7/comments') && str_contains($r['body'], 'Seen again'));
    }
}
