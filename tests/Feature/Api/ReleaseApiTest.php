<?php

namespace Tests\Feature\Api;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Tests\TestCase;

class ReleaseApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_releases_can_be_announced_idempotently_and_listed(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'admin']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $url = "/api/v1/organizations/{$organization->slug}/projects/{$project->slug}/releases";

        Sanctum::actingAs($user, ['read', 'write']);

        $this->postJson($url, ['version' => 'v1.2.3', 'commit_sha' => 'abc123', 'commit_url' => 'https://example.com/c/abc123'])
            ->assertCreated()
            ->assertJsonPath('data.version', 'v1.2.3');

        $this->postJson($url, ['version' => 'v1.2.3', 'commit_message' => 'Fix'])
            ->assertOk()
            ->assertJsonPath('data.commit_message', 'Fix');

        $this->assertSame(1, $project->releases()->count());
        $this->assertNotNull($project->releases()->first()->deployed_at);

        $this->getJson($url)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.version', 'v1.2.3');
    }

    public function test_release_validation_errors(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'admin']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Sanctum::actingAs($user, ['write']);

        $this->postJson("/api/v1/organizations/{$organization->slug}/projects/{$project->slug}/releases", ['commit_url' => 'nope'])
            ->assertJsonValidationErrors(['version', 'commit_url']);
    }

    public function test_plain_members_cannot_announce_releases(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        Sanctum::actingAs($user, ['read', 'write']);

        $this->postJson("/api/v1/organizations/{$organization->slug}/projects/{$project->slug}/releases", ['version' => 'v9'])
            ->assertForbidden();

        $this->assertDatabaseMissing('releases', ['version' => 'v9']);
    }
}
