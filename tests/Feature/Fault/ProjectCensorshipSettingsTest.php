<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProjectCensorshipSettingsTest extends TestCase
{
    use RefreshDatabase;

    public function test_project_uses_the_default_censored_headers_by_default(): void
    {
        $project = FaultProject::factory()->create(['censored_headers' => null]);

        $this->assertSame(FaultProject::DEFAULT_CENSORED_HEADERS, $project->censoredHeaders());
    }

    public function test_owner_can_configure_a_custom_censored_header_list(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($owner)->patch(
            route('organizations.projects.censorship.update', [$organization, $project]),
            ['censored_headers' => "Authorization\nX-Api-Token\nAuthorization"],
        );

        $response->assertRedirect();
        $response->assertSessionHasNoErrors();

        $project->refresh();
        $this->assertSame(['Authorization', 'X-Api-Token'], $project->censoredHeaders());
    }

    public function test_owner_can_reset_censorship_to_the_default(): void
    {
        $organization = Organization::factory()->create();
        $owner = User::factory()->create();
        $organization->users()->attach($owner->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'censored_headers' => ['X-Api-Token'],
        ]);

        $response = $this->actingAs($owner)->patch(
            route('organizations.projects.censorship.update', [$organization, $project]),
            ['reset' => '1'],
        );

        $response->assertRedirect();
        $this->assertNull($project->refresh()->censored_headers);
        $this->assertSame(FaultProject::DEFAULT_CENSORED_HEADERS, $project->censoredHeaders());
    }

    public function test_member_cannot_update_censorship_settings(): void
    {
        $organization = Organization::factory()->create();
        $member = User::factory()->create();
        $organization->users()->attach($member->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($member)->patch(
            route('organizations.projects.censorship.update', [$organization, $project]),
            ['censored_headers' => 'Authorization'],
        );

        $response->assertForbidden();
    }
}
