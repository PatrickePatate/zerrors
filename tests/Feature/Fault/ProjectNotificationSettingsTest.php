<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectNotificationSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_configure_slack_telegram_and_email_alerts(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($owner)
            ->patch(route('organizations.projects.notifications.update', [$organization, $project]), [
                'slack_webhook_url' => 'https://hooks.slack.com/services/x',
                'telegram_bot_token' => '123456:ABC',
                'telegram_chat_id' => '-100123456',
                'notify_email' => 'alerts@example.com',
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('https://hooks.slack.com/services/x', $project->slack_webhook_url);
        $this->assertSame('123456:ABC', $project->telegram_bot_token);
        $this->assertSame('-100123456', $project->telegram_chat_id);
        $this->assertSame('alerts@example.com', $project->notify_email);
        $this->assertTrue($project->hasSlackConfigured());
        $this->assertTrue($project->hasTelegramConfigured());
        $this->assertTrue($project->hasEmailAlertConfigured());
    }

    public function test_blank_telegram_token_keeps_the_existing_one(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'telegram_bot_token' => 'existing-token',
            'telegram_chat_id' => '111',
        ]);

        $this->actingAs($owner)
            ->patch(route('organizations.projects.notifications.update', [$organization, $project]), [
                'telegram_chat_id' => '222',
            ])
            ->assertRedirect();

        $project->refresh();
        $this->assertSame('existing-token', $project->telegram_bot_token);
        $this->assertSame('222', $project->telegram_chat_id);
    }

    public function test_member_cannot_configure_notifications(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $this->actingAs($member)
            ->patch(route('organizations.projects.notifications.update', [$organization, $project]), [
                'slack_webhook_url' => 'https://hooks.slack.com/services/x',
            ])
            ->assertForbidden();
    }
}
