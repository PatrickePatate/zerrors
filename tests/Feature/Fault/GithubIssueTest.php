<?php

namespace Tests\Feature\Fault;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class GithubIssueTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_creates_a_linked_github_issue(): void
    {
        Http::fake([
            'api.github.com/*' => Http::response([
                'html_url' => 'https://github.com/acme/api/issues/42',
                'number' => 42,
            ], 201),
        ]);

        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'github_token' => 'ghp_secret',
        ]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($user)
            ->post(route('organizations.issues.github', [$organization, $project, $issue]))
            ->assertRedirect();

        $issue->refresh();
        $this->assertSame('https://github.com/acme/api/issues/42', $issue->github_issue_url);
        $this->assertSame(42, $issue->github_issue_number);

        Http::assertSent(fn ($request) => $request->url() === 'https://api.github.com/repos/acme/api/issues'
            && $request->hasHeader('Authorization', 'Bearer ghp_secret'));
    }

    public function test_it_fails_gracefully_without_github_configured(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);
        $project = FaultProject::factory()->create(['organization_id' => $organization->id]);
        $issue = FaultIssue::factory()->create(['fault_project_id' => $project->id]);

        $this->actingAs($user)
            ->post(route('organizations.issues.github', [$organization, $project, $issue]))
            ->assertRedirect()
            ->assertSessionHasErrors('github');
    }
}
