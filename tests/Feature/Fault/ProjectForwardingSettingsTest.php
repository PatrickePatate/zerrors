<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectForwardingSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_owner_can_configure_a_forwarding_dsn(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($owner)->patch(
            route('organizations.projects.forwarding.update', [$organization, $project]),
            ['forward_enabled' => '1', 'forward_dsn' => 'https://publickey@o0.ingest.sentry.io/123'],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertTrue($project->forward_enabled);
        $this->assertSame('https://publickey@o0.ingest.sentry.io/123', $project->forward_dsn);
    }

    public function test_an_invalid_dsn_is_rejected(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($owner)->patch(
            route('organizations.projects.forwarding.update', [$organization, $project]),
            ['forward_enabled' => '1', 'forward_dsn' => 'not-a-dsn'],
        );

        $response->assertSessionHasErrors('forward_dsn');
        $this->assertNull($project->refresh()->forward_dsn);
    }

    public function test_member_cannot_update_forwarding_settings(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($member)->patch(
            route('organizations.projects.forwarding.update', [$organization, $project]),
            ['forward_enabled' => '1', 'forward_dsn' => 'https://publickey@o0.ingest.sentry.io/123'],
        );

        $response->assertForbidden();
    }
}
