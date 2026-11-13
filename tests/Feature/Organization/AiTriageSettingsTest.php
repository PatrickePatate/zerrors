<?php

namespace Tests\Feature\Organization;

use App\Livewire\OrganizationAiSettings;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class AiTriageSettingsTest extends TestCase
{
    use RefreshDatabase;

    protected function organizationWithRole(string $role): array
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => $role]);

        return [$organization, $user];
    }

    public function test_owner_can_enable_triage_with_a_separate_key(): void
    {
        [$organization, $owner] = $this->organizationWithRole('owner');

        Livewire::actingAs($owner)->test(OrganizationAiSettings::class, ['organization' => $organization])
            ->assertSet('aiTriageModel', '~typesafe/jev-latest')
            ->set('aiProvider', 'anthropic')
            ->set('aiTriageEnabled', true)
            ->set('aiTriageApiKey', 'sk-or-secret')
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('aiTriageApiKey', '');

        $organization->refresh();
        $this->assertTrue($organization->ai_triage_enabled);
        $this->assertNull($organization->ai_triage_model);
        $this->assertSame('sk-or-secret', $organization->ai_triage_api_key);
    }

    public function test_key_field_is_hidden_for_openrouter_and_custom_model_is_saved(): void
    {
        [$organization, $owner] = $this->organizationWithRole('admin');

        Livewire::actingAs($owner)->test(OrganizationAiSettings::class, ['organization' => $organization])
            ->set('aiProvider', 'openrouter')
            ->assertDontSee('OpenRouter API key')
            ->set('aiTriageModel', '~acme/custom')
            ->call('save');

        $this->assertSame('~acme/custom', $organization->fresh()->ai_triage_model);
    }

    public function test_validation_and_authorization(): void
    {
        [$organization, $owner] = $this->organizationWithRole('owner');

        Livewire::actingAs($owner)->test(OrganizationAiSettings::class, ['organization' => $organization])
            ->set('aiProvider', 'openai')
            ->set('aiTriageModel', str_repeat('a', 256))
            ->call('save')
            ->assertHasErrors(['aiTriageModel']);

        [$other, $member] = $this->organizationWithRole('member');

        Livewire::actingAs($member)->test(OrganizationAiSettings::class, ['organization' => $other])
            ->set('aiProvider', 'openai')
            ->call('save')
            ->assertForbidden();
    }
}
