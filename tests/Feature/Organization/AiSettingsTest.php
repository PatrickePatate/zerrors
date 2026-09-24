<?php

namespace Tests\Feature\Organization;

use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AiSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_connect_an_ai_provider(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)->patch(route('organizations.settings.ai.update', $organization), [
            'ai_provider' => 'anthropic',
            'ai_api_key' => 'sk-test-key',
            'ai_model' => 'claude-3-5-sonnet',
        ])->assertRedirect();

        $organization->refresh();
        $this->assertSame('anthropic', $organization->ai_provider);
        $this->assertSame('sk-test-key', $organization->ai_api_key);
        $this->assertTrue($organization->hasAiConfigured());
    }

    public function test_owner_can_connect_openrouter_as_an_ai_provider(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)->patch(route('organizations.settings.ai.update', $organization), [
            'ai_provider' => 'openrouter',
            'ai_api_key' => 'sk-or-test-key',
            'ai_model' => 'openai/gpt-4o',
        ])->assertRedirect();

        $organization->refresh();
        $this->assertSame('openrouter', $organization->ai_provider);
        $this->assertSame('sk-or-test-key', $organization->ai_api_key);
        $this->assertTrue($organization->hasAiConfigured());
    }

    public function test_saving_ai_settings_without_a_key_keeps_the_existing_one(): void
    {
        $organization = Organization::factory()->create([
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-original',
        ]);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)->patch(route('organizations.settings.ai.update', $organization), [
            'ai_provider' => 'openai',
            'ai_api_key' => '',
            'ai_model' => 'gpt-4o',
        ]);

        $this->assertSame('sk-original', $organization->fresh()->ai_api_key);
        $this->assertSame('gpt-4o', $organization->fresh()->ai_model);
    }

    public function test_member_cannot_configure_ai_settings(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);

        $this->actingAs($member)->patch(route('organizations.settings.ai.update', $organization), [
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-test',
        ])->assertForbidden();
    }

    public function test_owner_can_disconnect_the_ai_provider(): void
    {
        $organization = Organization::factory()->create([
            'ai_provider' => 'openai',
            'ai_api_key' => 'sk-original',
        ]);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)->delete(route('organizations.settings.ai.destroy', $organization));

        $this->assertFalse($organization->fresh()->hasAiConfigured());
    }
}
