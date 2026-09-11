<?php

namespace Tests\Feature\Fault;

use App\Enums\NotificationRuleTrigger;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Support\Fault\IssueAlertNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class IssueAlertNotifierSlackTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_to_slack_via_the_organizations_bot_token(): void
    {
        Http::fake([
            'slack.com/api/chat.postMessage' => Http::response(['ok' => true]),
        ]);

        $organization = Organization::factory()->withSlackApp()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        app(IssueAlertNotifier::class)->issueCreated($issue);

        Http::assertSent(fn ($request) => $request->url() === 'https://slack.com/api/chat.postMessage'
            && $request->hasHeader('Authorization', 'Bearer '.$organization->slack_bot_token)
            && $request['channel'] === 'C123');
    }

    public function test_it_skips_gracefully_when_slack_is_not_connected(): void
    {
        Http::fake();

        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->slack('C123', 'alerts')->create();
        $channel->rules()->create(['trigger' => NotificationRuleTrigger::NewIssue]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        app(IssueAlertNotifier::class)->issueCreated($issue);

        Http::assertNothingSent();
    }
}
