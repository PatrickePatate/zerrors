<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\ProcessFaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Fault\IssueCreatedNotification;
use App\Notifications\Fault\IssueRegressedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class IssueAlertsTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(string $eventId, string $message = 'boom'): array
    {
        return [
            'event_id' => $eventId,
            'level' => 'error',
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => $message]]],
        ];
    }

    public function test_a_brand_new_issue_notifies_organization_members(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Notification::assertSentTo($member, IssueCreatedNotification::class);
        Notification::assertNotSentTo($member, IssueRegressedNotification::class);
    }

    public function test_a_resolved_issue_that_recurs_is_flagged_as_a_regression(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'status' => 'resolved',
            'fingerprint' => sha1('same-issue'),
        ]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10),
            'level' => 'error',
            'fingerprint' => ['same-issue'],
            'exception' => ['values' => [['type' => $issue->type, 'value' => 'again']]],
        ]);

        $issue->refresh();
        $this->assertSame('unresolved', $issue->status);
        $this->assertNotNull($issue->regressed_at);
        Notification::assertSentTo($member, IssueRegressedNotification::class);
    }

    public function test_an_ignored_issue_stays_ignored_when_it_recurs(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'status' => 'ignored',
            'fingerprint' => sha1('ignored-issue'),
        ]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10),
            'level' => 'error',
            'fingerprint' => ['ignored-issue'],
            'exception' => ['values' => [['type' => $issue->type, 'value' => 'again']]],
        ]);

        $issue->refresh();
        $this->assertSame('ignored', $issue->status);
        $this->assertNull($issue->regressed_at);
    }

    public function test_no_alerts_are_sent_when_the_organization_disabled_them(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Notification::assertNothingSent();
    }
}
