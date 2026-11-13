<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\MergeFaultIssues;
use App\Jobs\Fault\ProcessFaultEvent;
use App\Jobs\Fault\SyncGithubIssueRegression;
use App\Livewire\IssueActions;
use App\Livewire\IssueTimeline;
use App\Livewire\ProjectIssueList;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\FaultIssueFingerprint;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\Release;
use App\Models\User;
use App\Support\Fault\EventFingerprinter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Str;
use Livewire\Livewire;
use Tests\TestCase;

class IssueWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected Organization $organization;

    protected User $owner;

    protected FaultProject $project;

    protected function setUp(): void
    {
        parent::setUp();

        Notification::fake();
        $this->organization = Organization::factory()->create();
        $this->owner = User::factory()->create();
        $this->organization->users()->attach($this->owner->id, ['role' => 'owner']);
        $this->project = FaultProject::factory()->create(['organization_id' => $this->organization->id]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function payload(?string $release = null, string $message = 'boom'): array
    {
        return array_filter([
            'event_id' => Str::random(12),
            'level' => 'error',
            'release' => $release,
            'exception' => ['values' => [['type' => 'RuntimeException'.$message, 'value' => $message]]],
        ]);
    }

    protected function issueFor(array $payload, array $attributes = []): FaultIssue
    {
        return FaultIssue::factory()->create([
            'fault_project_id' => $this->project->id,
            'fingerprint' => EventFingerprinter::for($payload),
            ...$attributes,
        ]);
    }

    protected function ingest(array $payload): void
    {
        ProcessFaultEvent::dispatch($this->project->id, (string) Str::uuid(), $payload);
    }

    protected function actions(FaultIssue $issue)
    {
        return Livewire::actingAs($this->owner)->test(IssueActions::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $issue]);
    }

    public function test_regression_does_not_call_github_synchronously_and_queues_a_sync_job(): void
    {
        Bus::fake([SyncGithubIssueRegression::class]);
        $payload = $this->payload();
        $issue = $this->issueFor($payload, ['status' => 'resolved', 'github_issue_number' => 5]);

        $this->ingest($payload);

        $this->assertSame('unresolved', $issue->fresh()->status);
        Bus::assertDispatched(SyncGithubIssueRegression::class);
        $this->assertTrue($issue->activities()->where('type', 'regression')->exists());
    }

    public function test_no_github_job_when_issue_is_not_linked(): void
    {
        Bus::fake([SyncGithubIssueRegression::class]);
        $payload = $this->payload();
        $this->issueFor($payload, ['status' => 'resolved']);

        $this->ingest($payload);

        Bus::assertNotDispatched(SyncGithubIssueRegression::class);
    }

    public function test_ignore_options_set_snooze_fields(): void
    {
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'times_seen' => 7]);

        $this->actions($issue)->call('updateStatus', 'ignored', '24h');
        $fresh = $issue->fresh();
        $this->assertSame('ignored', $fresh->status);
        $this->assertTrue($fresh->snoozed_until->between(now()->addHours(23), now()->addHours(25)));

        $this->actions($issue)->call('updateStatus', 'ignored', 'x10');
        $this->assertSame(70, $issue->fresh()->snooze_until_count);
        $this->assertNull($issue->fresh()->snoozed_until);

        $this->actions($issue)->call('updateStatus', 'ignored', 'x100');
        $this->assertSame(107, $issue->fresh()->snooze_until_count);
    }

    public function test_expired_snooze_un_ignores_at_ingest_and_flags_resurfaced(): void
    {
        $payload = $this->payload();
        $issue = $this->issueFor($payload, ['status' => 'ignored', 'snoozed_until' => now()->subMinute()]);

        $this->ingest($payload);

        $fresh = $issue->fresh();
        $this->assertSame('unresolved', $fresh->status);
        $this->assertNull($fresh->snoozed_until);
        $this->assertTrue($issue->activities()->where('type', 'resurfaced')->exists());
    }

    public function test_active_snooze_keeps_the_issue_ignored(): void
    {
        $payload = $this->payload();
        $issue = $this->issueFor($payload, ['status' => 'ignored', 'snoozed_until' => now()->addHour()]);

        $this->ingest($payload);

        $this->assertSame('ignored', $issue->fresh()->status);
    }

    public function test_count_snooze_un_ignores_when_threshold_reached(): void
    {
        $payload = $this->payload();
        $issue = $this->issueFor($payload, ['status' => 'ignored', 'times_seen' => 1, 'snooze_until_count' => 3]);
        Redis::connection()->del("fault:issue:{$issue->id}:pending_count");

        $this->ingest($payload);
        $this->assertSame('ignored', $issue->fresh()->status);

        $this->ingest($payload);
        $this->assertSame('unresolved', $issue->fresh()->status);
    }

    public function test_command_un_ignores_expired_snoozes(): void
    {
        $expired = FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'status' => 'ignored', 'snoozed_until' => now()->subMinute()]);
        $active = FaultIssue::factory()->create(['fault_project_id' => $this->project->id, 'status' => 'ignored', 'snoozed_until' => now()->addHour()]);

        $this->artisan('fault:unsnooze-issues')->assertSuccessful();

        $this->assertSame('unresolved', $expired->fresh()->status);
        $this->assertSame('ignored', $active->fresh()->status);
    }

    public function test_resolve_in_next_release_only_regresses_on_a_later_release(): void
    {
        Release::factory()->create(['fault_project_id' => $this->project->id, 'version' => 'v1', 'deployed_at' => now()->subDays(2)]);
        Release::factory()->create(['fault_project_id' => $this->project->id, 'version' => 'v2', 'deployed_at' => now()->addDay()]);
        $payload = $this->payload('v1');
        $issue = $this->issueFor($payload);
        FaultEvent::factory()->create(['fault_issue_id' => $issue->id, 'fault_project_id' => $this->project->id, 'release' => 'v1', 'occurred_at' => now()]);

        $this->actions($issue)->call('updateStatus', 'resolved', 'next');
        $this->assertTrue($issue->fresh()->resolved_in_next_release);
        $this->assertSame($this->owner->id, $issue->fresh()->resolved_by_user_id);

        $this->ingest($payload);
        $this->assertSame('resolved', $issue->fresh()->status);

        $this->ingest($this->payload('v2'));
        $fresh = $issue->fresh();
        $this->assertSame('unresolved', $fresh->status);
        $this->assertFalse($fresh->resolved_in_next_release);
    }

    public function test_resolve_in_current_release_stores_latest_release_and_regresses_on_it_or_later(): void
    {
        Release::factory()->create(['fault_project_id' => $this->project->id, 'version' => 'v1', 'deployed_at' => now()->subDays(3)]);
        Release::factory()->create(['fault_project_id' => $this->project->id, 'version' => 'v2', 'deployed_at' => now()->subDay()]);
        $payload = $this->payload('v1');
        $issue = $this->issueFor($payload);
        FaultEvent::factory()->create(['fault_issue_id' => $issue->id, 'fault_project_id' => $this->project->id, 'release' => 'v1', 'occurred_at' => now()]);

        $this->actions($issue)->call('updateStatus', 'resolved', 'current');
        $this->assertSame('v2', $issue->fresh()->resolved_in_release);

        $this->ingest($payload);
        $this->assertSame('resolved', $issue->fresh()->status);

        $this->ingest($this->payload('v2'));
        $this->assertSame('unresolved', $issue->fresh()->status);
    }

    public function test_plain_resolve_and_missing_release_still_regress(): void
    {
        $payload = $this->payload();
        $issue = $this->issueFor($payload, ['status' => 'resolved', 'resolved_in_next_release' => true, 'resolved_at' => now()]);

        $this->ingest($payload);

        $this->assertSame('unresolved', $issue->fresh()->status);
    }

    public function test_status_changes_assignments_and_audit_are_recorded(): void
    {
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);

        $this->actions($issue)->call('updateStatus', 'resolved')->set('assignedToUserId', (string) $this->owner->id);

        $this->assertSame(['status', 'assignment'], $issue->activities()->orderBy('id')->pluck('type')->all());
        $this->assertDatabaseHas('audit_logs', ['action' => 'issue.status_updated']);
    }

    public function test_non_member_cannot_act(): void
    {
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);

        Livewire::actingAs(User::factory()->create())
            ->test(IssueActions::class, ['organization' => $this->organization, 'project' => $this->project, 'issue' => $issue])
            ->call('updateStatus', 'resolved')
            ->assertForbidden();

        $this->assertSame('unresolved', $issue->fresh()->status);
    }

    public function test_comments_render_markdown_safely_and_only_authors_delete(): void
    {
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);
        $other = User::factory()->create();
        $this->organization->users()->attach($other->id, ['role' => 'member']);
        $args = ['organization' => $this->organization, 'project' => $this->project, 'issue' => $issue];

        Livewire::actingAs($this->owner)->test(IssueTimeline::class, $args)
            ->set('body', '**bold** <script>alert(1)</script> [x](javascript:alert(1))')
            ->call('addComment')
            ->assertSeeHtml('<strong>bold</strong>')
            ->assertDontSeeHtml('<script>')
            ->assertDontSeeHtml('href="javascript:');

        $comment = FaultIssueActivity::where('type', 'comment')->firstOrFail();

        Livewire::actingAs($other)->test(IssueTimeline::class, $args)->call('deleteComment', $comment->id)->assertNotFound();
        $this->assertDatabaseHas('fault_issue_activities', ['id' => $comment->id]);

        Livewire::actingAs($this->owner)->test(IssueTimeline::class, $args)->call('deleteComment', $comment->id);
        $this->assertDatabaseMissing('fault_issue_activities', ['id' => $comment->id]);
    }

    public function test_timeline_is_hidden_on_guest_detail_but_shown_to_members(): void
    {
        $issue = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);

        $this->actingAs($this->owner)
            ->get(route('organizations.issues.show', [$this->organization, $this->project, $issue]))
            ->assertOk()
            ->assertSee('Activity');
    }

    public function test_bulk_actions_apply_and_are_audited_and_recorded(): void
    {
        $issues = FaultIssue::factory()->count(3)->create(['fault_project_id' => $this->project->id]);

        Livewire::actingAs($this->owner)
            ->test(ProjectIssueList::class, ['organization' => $this->organization, 'project' => $this->project])
            ->set('selected', $issues->pluck('id')->all())
            ->call('bulkResolve')
            ->assertSet('selected', []);

        $this->assertSame(3, FaultIssue::where('status', 'resolved')->count());
        $this->assertSame(3, FaultIssueActivity::where('type', 'status')->count());
        $this->assertSame(3, $this->organization->auditLogs()->where('action', 'issue.status_updated')->count());

        Livewire::actingAs($this->owner)
            ->test(ProjectIssueList::class, ['organization' => $this->organization, 'project' => $this->project])
            ->set('selected', $issues->pluck('id')->all())
            ->call('bulkIgnore', '7d')
            ->set('selected', $issues->pluck('id')->all())
            ->call('bulkAssign', $this->owner->id);

        $this->assertSame(3, FaultIssue::where('status', 'ignored')->where('assigned_to_user_id', $this->owner->id)->count());
    }

    public function test_bulk_rejects_cross_project_ids_non_members_and_oversized_selections(): void
    {
        $mine = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);
        $foreign = FaultIssue::factory()->create();
        $args = ['organization' => $this->organization, 'project' => $this->project];

        Livewire::actingAs($this->owner)->test(ProjectIssueList::class, $args)
            ->set('selected', [$mine->id, $foreign->id])->call('bulkResolve')->assertStatus(422);
        $this->assertSame('unresolved', $mine->fresh()->status);

        Livewire::actingAs($this->owner)->test(ProjectIssueList::class, $args)
            ->set('selected', range(1, 101))->call('bulkResolve')->assertStatus(422);

        Livewire::actingAs(User::factory()->create())->test(ProjectIssueList::class, $args)
            ->set('selected', [$mine->id])->call('bulkResolve')->assertForbidden();
    }

    public function test_select_all_on_page_toggles_the_page_ids(): void
    {
        $issues = FaultIssue::factory()->count(3)->create(['fault_project_id' => $this->project->id]);

        Livewire::actingAs($this->owner)
            ->test(ProjectIssueList::class, ['organization' => $this->organization, 'project' => $this->project])
            ->call('toggleSelectPage')
            ->assertCount('selected', 3)
            ->call('toggleSelectPage')
            ->assertSet('selected', []);
    }

    public function test_merge_combines_issues_and_future_events_follow_the_target(): void
    {
        $payloadA = $this->payload(null, 'a');
        $payloadB = $this->payload(null, 'b');
        $target = $this->issueFor($payloadA, ['times_seen' => 10, 'first_seen_at' => now()->subDay()]);
        $source = $this->issueFor($payloadB, ['times_seen' => 4, 'first_seen_at' => now()->subDays(5), 'last_seen_at' => now()->addMinute()]);
        FaultEvent::factory()->count(2)->create(['fault_issue_id' => $source->id, 'fault_project_id' => $this->project->id]);
        $redis = Redis::connection();
        $redis->set("fault:issue:{$source->id}:pending_count", 3);
        $redis->pfadd("fault:issue:{$source->id}:users", ['u1']);
        $redis->del("fault:issue:{$target->id}:pending_count");

        Livewire::actingAs($this->owner)
            ->test(ProjectIssueList::class, ['organization' => $this->organization, 'project' => $this->project])
            ->set('selected', [$target->id, $source->id])
            ->call('bulkMerge');

        $this->assertDatabaseMissing('fault_issues', ['id' => $source->id]);
        $fresh = $target->fresh();
        $this->assertSame(14, $fresh->times_seen);
        $this->assertSame(2, $fresh->events()->count());
        $this->assertTrue($fresh->first_seen_at->lt(now()->subDays(4)));
        $this->assertSame(3, (int) $redis->get("fault:issue:{$target->id}:pending_count"));
        $this->assertSame(1, $redis->pfcount("fault:issue:{$target->id}:users"));
        $this->assertTrue($fresh->activities()->where('type', 'merged')->exists());
        $this->assertDatabaseHas('fault_issue_fingerprints', ['fingerprint' => $source->fingerprint, 'fault_issue_id' => $target->id]);

        $this->ingest($payloadB);

        $this->assertSame(0, FaultIssue::where('fault_project_id', $this->project->id)->where('id', '!=', $target->id)->count());
        $this->assertSame(3, $fresh->events()->count());
    }

    public function test_merge_job_ignores_issues_from_other_projects(): void
    {
        $target = FaultIssue::factory()->create(['fault_project_id' => $this->project->id]);
        $foreign = FaultIssue::factory()->create();

        MergeFaultIssues::dispatchSync($this->project->id, $target->id, [$foreign->id]);

        $this->assertDatabaseHas('fault_issues', ['id' => $foreign->id]);
        $this->assertSame(0, FaultIssueFingerprint::count());
    }
}
