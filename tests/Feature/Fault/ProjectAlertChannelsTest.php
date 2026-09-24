<?php

namespace Tests\Feature\Fault;

use App\Enums\NotificationRuleTrigger;
use App\Jobs\Fault\ProcessFaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Models\User;
use App\Notifications\Fault\IssueAlertNotification;
use App\Notifications\Fault\IssueCreatedNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

class ProjectAlertChannelsTest extends TestCase
{
    use RefreshDatabase;

    protected function payload(string $eventId): array
    {
        return [
            'event_id' => $eventId,
            'level' => 'error',
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ];
    }

    public function test_a_new_issue_posts_to_a_slack_channel_with_a_new_issue_rule(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request['channel'] === 'C123' && str_contains($request['text'], 'New issue'));
    }

    public function test_a_new_issue_posts_to_a_telegram_channel_with_a_new_issue_rule(): void
    {
        Http::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->telegram('123456:ABC', '-100999')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bot123456:ABC/sendMessage'
            && $request['chat_id'] === '-100999');
    }

    public function test_a_new_issue_emails_a_channel_with_a_new_issue_rule(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->email('alerts@example.com')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Notification::assertSentOnDemand(
            IssueAlertNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'alerts@example.com'
        );
    }

    public function test_an_org_member_configured_on_a_matching_project_channel_is_not_also_sent_the_org_wide_alert(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => true]);
        $subscribedMember = User::factory()->create(['email' => 'shared@example.com']);
        $organization->users()->attach($subscribedMember->id, ['role' => 'member']);
        $otherMember = User::factory()->create(['email' => 'other@example.com']);
        $organization->users()->attach($otherMember->id, ['role' => 'member']);

        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->email('shared@example.com')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        // The member sharing an address with the project channel gets only the
        // channel's email; other org members still get the org-wide alert.
        Notification::assertSentOnDemand(IssueAlertNotification::class);
        Notification::assertSentTo($otherMember, IssueCreatedNotification::class);
        Notification::assertNotSentTo($subscribedMember, IssueCreatedNotification::class);
        Notification::assertSentTimes(IssueCreatedNotification::class, 1);
    }

    public function test_a_disabled_channel_never_fires(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create(['enabled' => false]);
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertNothingSent();
    }

    public function test_a_disabled_rule_never_fires(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue, 'enabled' => false]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertNothingSent();
    }

    public function test_an_every_event_rule_fires_on_every_occurrence(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::EveryEvent]);

        $fingerprint = ['same-issue'];
        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10), 'level' => 'error', 'fingerprint' => $fingerprint,
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ]);
        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10), 'level' => 'error', 'fingerprint' => $fingerprint,
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ]);

        Http::assertSentCount(2);
    }

    public function test_a_channel_with_both_new_issue_and_every_event_rules_only_fires_once_on_the_first_occurrence(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::EveryEvent]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertSentCount(1);
    }

    public function test_a_new_project_default_channel_does_not_double_email_on_the_first_occurrence(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => true]);
        $owner = User::factory()->create(['email' => 'owner@example.com']);
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->post(route('organizations.projects.store', $organization), ['name' => 'API', 'platform' => 'laravel'])
            ->assertRedirect();

        $project = FaultProject::where('name', 'API')->sole();

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        // The org-wide "New issue" alert covers the first occurrence; the default
        // project channel's occurrence-threshold rule must not also fire for it.
        Notification::assertSentTimes(IssueCreatedNotification::class, 1);
        Notification::assertSentTimes(IssueAlertNotification::class, 0);
    }

    public function test_an_occurrence_threshold_rule_only_fires_on_matching_counts(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create([
            'trigger' => NotificationRuleTrigger::OccurrenceThreshold,
            'thresholds' => [1, 3],
        ]);

        $fingerprint = ['same-issue'];
        for ($i = 0; $i < 3; $i++) {
            ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
                'event_id' => Str::random(10), 'level' => 'error', 'fingerprint' => $fingerprint,
                'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
            ]);
        }

        // 1st and 3rd occurrence match the thresholds list; the 2nd does not.
        Http::assertSentCount(2);
    }

    public function test_a_regression_rule_does_not_fire_for_a_plain_repeat_of_an_unresolved_issue(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::Regression]);

        $fingerprint = ['same-issue'];
        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10), 'level' => 'error', 'fingerprint' => $fingerprint,
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ]);
        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10), 'level' => 'error', 'fingerprint' => $fingerprint,
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ]);

        Http::assertNothingSent();
    }

    public function test_a_regression_rule_fires_when_a_resolved_issue_reoccurs(): void
    {
        Http::fake();

        $organization = Organization::factory()->withSlackApp()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        FaultIssue::factory()->create([
            'fault_project_id' => $project->id,
            'status' => 'resolved',
            'fingerprint' => sha1('same-issue'),
        ]);
        $channel = NotificationChannel::factory()->for($project, 'project')
            ->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::Regression]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), [
            'event_id' => Str::random(10), 'level' => 'error', 'fingerprint' => ['same-issue'],
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'again']]],
        ]);

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && str_contains($request['text'], 'Issue regressed'));
    }
}
