<?php

namespace Tests\Feature\Fault;

use App\Enums\NotificationRuleTrigger;
use App\Livewire\NotificationChannelManager;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_slack_channel(): void
    {
        Http::fake([
            'slack.com/api/conversations.list*' => Http::response([
                'ok' => true,
                'channels' => [['id' => 'C123', 'name' => 'alerts']],
            ]),
        ]);

        $organization = Organization::factory()->withSlackApp()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'slack')
            ->set('newSlackChannelId', 'C123')
            ->call('addChannel')
            ->assertHasNoErrors();

        $channel = $project->notificationChannels()->sole();
        $this->assertSame('C123', $channel->config['channel_id']);
        $this->assertSame('alerts', $channel->config['channel_name']);
    }

    public function test_adding_a_channel_validates_its_config(): void
    {
        $organization = Organization::factory()->withSlackApp()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'slack')
            ->set('newSlackChannelId', '')
            ->call('addChannel')
            ->assertHasErrors(['newSlackChannelId']);

        $this->assertSame(0, $project->notificationChannels()->count());
    }

    public function test_connect_slack_prompt_is_shown_when_slack_is_not_connected(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'slack')
            ->assertSee('Connect Slack');
    }

    public function test_owner_can_toggle_a_rule_and_set_occurrence_thresholds(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->slack('https://hooks.slack.com/services/x')->create();

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->call('toggleRule', $channel->id, NotificationRuleTrigger::EveryEvent->value)
            ->set("thresholdInputs.{$channel->id}", '1, 10, 10, 100')
            ->call('updateThresholds', $channel->id);

        $channel->refresh();
        $this->assertTrue($channel->rules->firstWhere('trigger', NotificationRuleTrigger::EveryEvent)->enabled);
        $thresholdRule = $channel->rules->firstWhere('trigger', NotificationRuleTrigger::OccurrenceThreshold);
        $this->assertSame([1, 10, 100], $thresholdRule->thresholds);
        $this->assertTrue($thresholdRule->enabled);
    }

    public function test_owner_can_delete_a_channel(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $channel = NotificationChannel::factory()->for($project, 'project')->slack('https://hooks.slack.com/services/x')->create();

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->call('deleteChannel', $channel->id);

        $this->assertSame(0, $project->notificationChannels()->count());
    }

    public function test_member_cannot_manage_notification_channels(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($member)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->assertStatus(403);
    }
}
