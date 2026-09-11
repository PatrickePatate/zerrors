<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Github\GithubAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_requires_organization_membership(): void
    {
        $organization = Organization::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('integrations.github.redirect', $organization))
            ->assertForbidden();
    }

    public function test_redirect_sends_an_owner_to_the_github_install_url(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->get(route('integrations.github.redirect', $organization));

        $response->assertRedirect();
        $this->assertStringStartsWith(
            'https://github.com/apps/'.config('services.github.slug').'/installations/new',
            $response->headers->get('Location')
        );
    }

    public function test_callback_stores_the_installation_on_the_organization(): void
    {
        Http::fake([
            'api.github.com/app/installations/*' => Http::response([
                'account' => ['login' => 'acme', 'type' => 'Organization'],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $state = app(GithubAppClient::class)->installUrl($organization->id);
        $state = urldecode(explode('state=', $state)[1]);

        $this->get(route('integrations.github.callback', [
            'installation_id' => '12345',
            'setup_action' => 'install',
            'state' => $state,
        ]))->assertRedirect(route('organizations.settings.edit', $organization));

        $organization->refresh();
        $this->assertSame('12345', $organization->github_installation_id);
        $this->assertSame('acme', $organization->github_account_login);
        $this->assertSame('Organization', $organization->github_account_type);
        $this->assertNotNull($organization->github_connected_at);
    }

    public function test_disconnect_clears_the_installation(): void
    {
        $organization = Organization::factory()->withGithubApp()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->delete(route('integrations.github.disconnect', $organization))
            ->assertRedirect();

        $organization->refresh();
        $this->assertNull($organization->github_installation_id);
        $this->assertNull($organization->github_connected_at);
    }
}
