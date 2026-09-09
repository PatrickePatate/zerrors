<?php

namespace Tests\Feature\Organization;

use App\Livewire\OrganizationAiSettings;
use App\Livewire\OrganizationGeneralSettings;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class OrganizationSettingsLivewireTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_update_general_settings(): void
    {
        $organization = Organization::factory()->create(['name' => 'Old name']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(OrganizationGeneralSettings::class, ['organization' => $organization])
            ->set('name', 'New name')
            ->set('alertsEnabled', true)
            ->set('require2fa', true)
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true);

        $organization->refresh();
        $this->assertSame('New name', $organization->name);
        $this->assertTrue($organization->alerts_enabled);
        $this->assertTrue($organization->require_2fa);
    }

    public function test_member_cannot_update_general_settings(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);

        Livewire::actingAs($member)
            ->test(OrganizationGeneralSettings::class, ['organization' => $organization])
            ->set('name', 'Hacked')
            ->call('save')
            ->assertStatus(403);
    }

    public function test_owner_can_connect_an_ai_provider(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(OrganizationAiSettings::class, ['organization' => $organization])
            ->set('aiProvider', 'anthropic')
            ->set('aiApiKey', 'sk-test')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('saved', true)
            ->assertSet('aiApiKey', '');

        $organization->refresh();
        $this->assertSame('anthropic', $organization->ai_provider);
        $this->assertSame('sk-test', $organization->ai_api_key);
    }

    public function test_owner_can_disconnect_the_ai_provider(): void
    {
        $organization = Organization::factory()->create(['ai_provider' => 'anthropic', 'ai_api_key' => 'sk-test']);
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        Livewire::actingAs($owner)
            ->test(OrganizationAiSettings::class, ['organization' => $organization])
            ->call('disconnect');

        $organization->refresh();
        $this->assertNull($organization->ai_provider);
        $this->assertNull($organization->ai_api_key);
    }
}
