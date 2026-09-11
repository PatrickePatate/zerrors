<?php

namespace Tests\Feature;

use App\Models\Organization;
use App\Models\User;
use App\Support\Slack\SlackAppClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class SlackAppIntegrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_redirect_requires_organization_membership(): void
    {
        $organization = Organization::factory()->create();
        $stranger = User::factory()->create();

        $this->actingAs($stranger)
            ->get(route('integrations.slack.redirect', $organization))
            ->assertForbidden();
    }

    public function test_redirect_sends_an_owner_to_the_slack_authorize_url(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $response = $this->actingAs($owner)->get(route('integrations.slack.redirect', $organization));

        $response->assertRedirect();
        $this->assertStringStartsWith('https://slack.com/oauth/v2/authorize', $response->headers->get('Location'));
    }

    public function test_callback_stores_the_team_and_bot_token_on_the_organization(): void
    {
        Http::fake([
            'slack.com/api/oauth.v2.access' => Http::response([
                'ok' => true,
                'access_token' => 'xoxb-test-token',
                'team' => ['id' => 'T123', 'name' => 'Acme'],
                'authed_user' => ['id' => 'U123'],
            ]),
        ]);

        $organization = Organization::factory()->create();
        $state = app(SlackAppClient::class)->authorizeUrl($organization->id);
        parse_str(parse_url($state, PHP_URL_QUERY), $query);

        $this->get(route('integrations.slack.callback', [
            'code' => 'test-code',
            'state' => $query['state'],
        ]))->assertRedirect(route('organizations.settings.edit', $organization));

        $organization->refresh();
        $this->assertSame('T123', $organization->slack_team_id);
        $this->assertSame('Acme', $organization->slack_team_name);
        $this->assertSame('xoxb-test-token', $organization->slack_bot_token);
        $this->assertSame('U123', $organization->slack_authed_user_id);
        $this->assertNotNull($organization->slack_connected_at);
    }

    public function test_disconnect_clears_the_slack_connection(): void
    {
        $organization = Organization::factory()->withSlackApp()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);

        $this->actingAs($owner)
            ->delete(route('integrations.slack.disconnect', $organization))
            ->assertRedirect();

        $organization->refresh();
        $this->assertNull($organization->slack_bot_token);
        $this->assertNull($organization->slack_connected_at);
    }
}
