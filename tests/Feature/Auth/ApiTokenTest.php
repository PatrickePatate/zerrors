<?php

namespace Tests\Feature\Auth;

use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiTokenTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_user_can_create_and_revoke_an_api_token(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->post(route('security.tokens.store'), ['name' => 'CI script'])
            ->assertRedirect();

        $this->assertDatabaseHas('personal_access_tokens', ['name' => 'CI script', 'tokenable_id' => $user->id]);

        $token = $user->tokens()->first();

        $this->actingAs($user)
            ->delete(route('security.tokens.destroy', $token->id))
            ->assertRedirect();

        $this->assertDatabaseMissing('personal_access_tokens', ['id' => $token->id]);
    }

    public function test_a_token_can_read_organizations_and_issues_via_the_api(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);

        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson('/api/v1/organizations')
            ->assertOk()
            ->assertJsonFragment(['slug' => $organization->slug]);

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/organizations/{$organization->slug}/projects")
            ->assertOk()
            ->assertJsonFragment(['slug' => $project->slug]);
    }

    public function test_a_token_cannot_read_an_organization_the_user_does_not_belong_to(): void
    {
        $user = User::factory()->create();
        $organization = Organization::factory()->create();
        $token = $user->createToken('test')->plainTextToken;

        $this->withHeader('Authorization', "Bearer {$token}")
            ->getJson("/api/v1/organizations/{$organization->slug}/projects")
            ->assertForbidden();
    }
}
