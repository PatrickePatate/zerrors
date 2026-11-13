<?php

namespace Tests\Feature\Fault;

use App\Enums\NotificationChannelType;
use App\Livewire\NotificationChannelManager;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Models\User;
use App\Support\Fault\IssueAlertNotifier;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class AlertChannelsDiscordWebhookTest extends TestCase
{
    use RefreshDatabase;

    protected function issueWithChannel(callable $channelState): array
    {
        $organization = Organization::factory()->create(['alerts_enabled' => false]);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = $channelState(NotificationChannel::factory()->for($project, 'project'))->create();
        $channel->rules()->create(['trigger' => 'new_issue']);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id, 'times_seen' => 3]);

        return [$issue, $channel];
    }

    public function test_discord_channel_posts_content_to_the_webhook(): void
    {
        Http::fake();

        [$issue] = $this->issueWithChannel(fn ($factory) => $factory->discord('https://discord.com/api/webhooks/1/abc'));

        app(IssueAlertNotifier::class)->notify($issue, true, false);

        Http::assertSent(fn ($request) => $request->url() === 'https://discord.com/api/webhooks/1/abc'
            && str_contains($request['content'], 'New issue'));
    }

    public function test_discord_channel_with_a_non_discord_host_is_not_posted_to(): void
    {
        Http::fake();

        [$issue] = $this->issueWithChannel(fn ($factory) => $factory->discord('https://internal.example.com/api/webhooks/1/abc'));

        app(IssueAlertNotifier::class)->notify($issue, true, false);

        Http::assertNothingSent();
    }

    public function test_webhook_channel_posts_a_signed_json_body(): void
    {
        Http::fake();

        [$issue] = $this->issueWithChannel(fn ($factory) => $factory->webhook('https://8.8.8.8/hook', 's3cret'));

        app(IssueAlertNotifier::class)->notify($issue, true, false);

        Http::assertSent(function ($request) use ($issue) {
            $body = $request->body();

            return $request->url() === 'https://8.8.8.8/hook'
                && $request->hasHeader('X-Zerrors-Signature', 'sha256='.hash_hmac('sha256', $body, 's3cret'))
                && $request['event'] === 'New issue'
                && $request['issue']['id'] === $issue->id
                && $request['issue']['times_seen'] === 3
                && $request['project']['slug'] === $issue->project->slug
                && $request['organization']['slug'] === $issue->project->organization->slug
                && isset($request['issue']['url'], $request['timestamp']);
        });
    }

    public function test_webhook_without_a_secret_sends_no_signature(): void
    {
        Http::fake();

        [$issue] = $this->issueWithChannel(fn ($factory) => $factory->webhook('https://8.8.8.8/hook'));

        app(IssueAlertNotifier::class)->notify($issue, true, false);

        Http::assertSent(fn ($request) => ! $request->hasHeader('X-Zerrors-Signature'));
    }

    public function test_webhook_to_a_private_address_is_blocked_at_send_time(): void
    {
        Http::fake();

        [$issue] = $this->issueWithChannel(fn ($factory) => $factory->webhook('http://127.0.0.1/hook'));

        app(IssueAlertNotifier::class)->notify($issue, true, false);

        Http::assertNothingSent();
    }

    public function test_manager_creates_discord_and_webhook_channels(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'discord')
            ->set('newDiscordWebhookUrl', 'https://discord.com/api/webhooks/1/abc')
            ->call('addChannel')
            ->assertHasNoErrors()
            ->set('newChannelType', 'webhook')
            ->set('newWebhookUrl', 'https://8.8.8.8/hook')
            ->set('newWebhookSecret', 'shh')
            ->call('addChannel')
            ->assertHasNoErrors();

        $this->assertSame(
            [NotificationChannelType::Discord, NotificationChannelType::Webhook],
            $project->notificationChannels()->orderBy('id')->get()->pluck('type')->all(),
        );
        $this->assertSame('shh', $project->notificationChannels()->where('name', 'Webhook')->first()->config['secret']);
    }

    public function test_manager_rejects_invalid_discord_and_webhook_urls(): void
    {
        $organization = Organization::factory()->create();
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'discord')
            ->set('newDiscordWebhookUrl', 'https://evil.example.com/api/webhooks/1/abc')
            ->call('addChannel')
            ->assertHasErrors('newDiscordWebhookUrl')
            ->set('newDiscordWebhookUrl', 'http://discord.com/api/webhooks/1/abc')
            ->call('addChannel')
            ->assertHasErrors('newDiscordWebhookUrl')
            ->set('newChannelType', 'webhook')
            ->set('newWebhookUrl', 'http://127.0.0.1/hook')
            ->call('addChannel')
            ->assertHasErrors('newWebhookUrl');

        $this->assertSame(0, $project->notificationChannels()->count());
    }
}
