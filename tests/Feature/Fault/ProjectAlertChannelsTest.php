<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\ProcessFaultEvent;
use App\Models\FaultProject;
use App\Models\Organization;
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

    public function test_a_new_issue_posts_to_a_configured_slack_webhook(): void
    {
        Http::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'slack_webhook_url' => 'https://hooks.slack.com/services/x',
        ]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertSent(fn ($request) => $request->url() === 'https://hooks.slack.com/services/x'
            && str_contains($request['text'], 'New issue'));
    }

    public function test_a_new_issue_posts_to_a_configured_telegram_chat(): void
    {
        Http::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'telegram_bot_token' => '123456:ABC',
            'telegram_chat_id' => '-100999',
        ]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Http::assertSent(fn ($request) => $request->url() === 'https://api.telegram.org/bot123456:ABC/sendMessage'
            && $request['chat_id'] === '-100999');
    }

    public function test_a_new_issue_emails_the_configured_project_address(): void
    {
        Notification::fake();

        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'notify_email' => 'alerts@example.com',
        ]);

        ProcessFaultEvent::dispatch($project->id, (string) Str::uuid(), $this->payload(Str::random(10)));

        Notification::assertSentOnDemand(
            IssueCreatedNotification::class,
            fn ($notification, $channels, $notifiable) => $notifiable->routes['mail'] === 'alerts@example.com'
        );
    }
}
