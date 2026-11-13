<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\ForwardFaultEvent;
use App\Jobs\Fault\ProcessFaultEvent;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultIssueShareLink;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\Release;
use App\Models\ReleaseCommit;
use App\Support\Database\ContainsSearch;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class ProcessFaultEventRetryAndLeakTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_unique_violation_on_event_id_returns_early_without_counting_or_forwarding(): void
    {
        Bus::fake([ForwardFaultEvent::class]);
        $project = FaultProject::factory()->create(['forward_enabled' => true, 'forward_dsn' => 'https://key@o1.ingest.sentry.io/1']);
        $payload = ['level' => 'error', 'fingerprint' => ['dup'], 'user' => ['id' => 'u1'], 'exception' => ['values' => [['type' => 'E', 'value' => 'x']]]];

        // The pre-check passes (no row yet) but the insert races a concurrent worker.
        FaultEvent::creating(function (FaultEvent $event): void {
            static $fired = false;
            if (! $fired) {
                $fired = true;
                FaultEvent::withoutEvents(fn () => FaultEvent::factory()->create([
                    'fault_project_id' => $event->fault_project_id,
                    'fault_issue_id' => $event->fault_issue_id,
                    'event_id' => $event->event_id,
                ]));
            }
        });

        (new ProcessFaultEvent($project->id, 'dupevent0001', $payload))->handle();

        $issue = FaultIssue::where('fault_project_id', $project->id)->firstOrFail();
        $this->assertSame(1, FaultEvent::where('event_id', 'dupevent0001')->count());
        $this->assertSame(0, (int) Redis::connection()->get("fault:issue:{$issue->id}:pending_count"));
        $this->assertSame(0, Redis::connection()->pfcount("fault:issue:{$issue->id}:users"));
        Bus::assertNotDispatched(ForwardFaultEvent::class);
    }

    public function test_a_retried_event_is_counted_once(): void
    {
        $project = FaultProject::factory()->create();
        $payload = ['level' => 'error', 'fingerprint' => ['retry'], 'exception' => ['values' => [['type' => 'E', 'value' => 'x']]]];

        (new ProcessFaultEvent($project->id, 'retryevent01', $payload))->handle();
        (new ProcessFaultEvent($project->id, 'retryevent01', $payload))->handle();

        $issue = FaultIssue::where('fault_project_id', $project->id)->firstOrFail();
        $this->assertSame(1, (int) Redis::connection()->get("fault:issue:{$issue->id}:pending_count"));
    }

    public function test_pruning_empty_issues_removes_their_redis_keys(): void
    {
        $project = FaultProject::factory()->create(['retention_days' => 30]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'last_seen_at' => now()->subDays(60)]);
        $redis = Redis::connection();
        $redis->pfadd("fault:issue:{$issue->id}:users", ['u1']);
        $redis->set("fault:issue:{$issue->id}:pending_count", 4);
        $redis->set("fault:issue:{$issue->id}:pending_last_seen", 123);

        $this->artisan('zerrors:prune-events')->assertExitCode(0);

        $this->assertModelMissing($issue);
        foreach (['users', 'pending_count', 'pending_last_seen'] as $suffix) {
            $this->assertSame(0, (int) $redis->exists("fault:issue:{$issue->id}:{$suffix}"));
        }
    }

    public function test_contains_search_is_case_insensitive_and_escapes_wildcards(): void
    {
        $project = FaultProject::factory()->create();
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'Disk 100% FULL', 'culprit' => 'a']);
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'under_score!', 'culprit' => 'b']);
        FaultIssue::factory()->create(['fault_project_id' => $project->id, 'title' => 'plain', 'culprit' => 'c']);

        $search = fn (string $term) => FaultIssue::query()->tap(fn ($q) => ContainsSearch::apply($q, ['title'], $term))->pluck('title')->all();

        $this->assertSame(['Disk 100% FULL'], $search('disk 100%'));
        $this->assertSame(['under_score!'], $search('r_s'));
        $this->assertSame(['under_score!'], $search('e!'));
        $this->assertSame([], $search('%u'));
    }

    public function test_organization_secrets_are_not_serialized(): void
    {
        $organization = Organization::factory()->create(['ai_api_key' => 'sk-secret', 'ai_triage_api_key' => 'sk-triage', 'slack_bot_token' => 'xoxb-secret']);

        $array = $organization->fresh()->toArray();

        $this->assertArrayNotHasKey('ai_api_key', $array);
        $this->assertArrayNotHasKey('ai_triage_api_key', $array);
        $this->assertArrayNotHasKey('slack_bot_token', $array);
    }

    public function test_public_share_page_hides_suspect_commit_authors(): void
    {
        $this->withoutVite();
        $project = FaultProject::factory()->create();
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'first_seen_release' => '1.0.0']);
        FaultEvent::factory()->create([
            'fault_project_id' => $project->id,
            'fault_issue_id' => $issue->id,
            'exception' => ['values' => [['type' => 'E', 'value' => 'x', 'stacktrace' => ['frames' => [['filename' => 'app/Example.php', 'in_app' => true]]]]]],
        ]);
        $release = Release::factory()->create(['fault_project_id' => $project->id, 'version' => '1.0.0']);
        ReleaseCommit::factory()->create(['release_id' => $release->id, 'author' => 'Jane Secretname', 'files' => ['app/Example.php']]);
        $link = FaultIssueShareLink::factory()->create(['fault_issue_id' => $issue->id]);

        $this->get(route('share.show', $link->token))->assertOk()->assertDontSee('Jane Secretname');
    }
}
