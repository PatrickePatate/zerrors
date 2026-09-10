<?php

namespace Tests\Feature\Fault;

use App\Enums\NotificationRuleTrigger;
use App\Livewire\NotificationChannelManager;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ProjectNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_add_a_slack_channel(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'slack')
            ->set('newWebhookUrl', 'https://hooks.slack.com/services/x')
            ->call('addChannel')
            ->assertHasNoErrors();

        $channel = $project->notificationChannels()->sole();
        $this->assertSame('https://hooks.slack.com/services/x', $channel->config['webhook_url']);
    }

    public function test_adding_a_channel_validates_its_config(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Livewire::actingAs($owner)
            ->test(NotificationChannelManager::class, ['organization' => $organization, 'project' => $project])
            ->set('newChannelType', 'slack')
            ->set('newWebhookUrl', 'not-a-url')
            ->call('addChannel')
            ->assertHasErrors(['newWebhookUrl']);

        $this->assertSame(0, $project->notificationChannels()->count());
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
