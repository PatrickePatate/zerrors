<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class GithubWebhookTest extends TestCase
{
    use RefreshDatabase;

    private function pushPayload(string $ref, ?array $headCommit, string $repoFullName = 'acme/api'): array
    {
        return [
            'ref' => $ref,
            'head_commit' => $headCommit,
            'repository' => ['full_name' => $repoFullName],
        ];
    }

    private function signature(array $payload): string
    {
        return 'sha256='.hash_hmac('sha256', json_encode($payload), config('services.github.webhook_secret'));
    }

    public function test_a_push_to_the_production_branch_creates_a_release(): void
    {
        $organization = Organization::factory()->withGithubApp()->create();
        $project = FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'production_branch' => 'main',
        ]);

        $payload = $this->pushPayload('refs/heads/main', [
            'id' => 'abcdef1234567890',
            'message' => "Fix the thing\n\nMore details.",
            'url' => 'https://github.com/acme/api/commit/abcdef1234567890',
            'timestamp' => '2026-01-01T12:00:00Z',
            'author' => ['name' => 'Ada Lovelace'],
        ]);

        $response = $this->postJson(
            '/api/webhooks/github',
            $payload,
            [
                'X-GitHub-Event' => 'push',
                'X-Hub-Signature-256' => $this->signature($payload),
            ]
        );

        $response->assertCreated();
        $response->assertJson(['release' => 'abcdef123456']);

        $this->assertDatabaseHas('releases', [
            'fault_project_id' => $project->id,
            'version' => 'abcdef123456',
            'commit_sha' => 'abcdef1234567890',
            'commit_author' => 'Ada Lovelace',
        ]);
    }

    public function test_a_push_to_another_branch_is_ignored(): void
    {
        $organization = Organization::factory()->withGithubApp()->create();
        FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'production_branch' => 'main',
        ]);

        $payload = $this->pushPayload('refs/heads/feature/foo', ['id' => 'abcdef1234567890']);

        $this->postJson(
            '/api/webhooks/github',
            $payload,
            [
                'X-GitHub-Event' => 'push',
                'X-Hub-Signature-256' => $this->signature($payload),
            ]
        )->assertOk();

        $this->assertDatabaseCount('releases', 0);
    }

    public function test_an_invalid_signature_is_rejected(): void
    {
        $organization = Organization::factory()->withGithubApp()->create();
        FaultProject::factory()->create([
            'organization_id' => $organization->id,
            'github_repo' => 'acme/api',
            'production_branch' => 'main',
        ]);

        $payload = $this->pushPayload('refs/heads/main', ['id' => 'abcdef1234567890']);

        $this->postJson(
            '/api/webhooks/github',
            $payload,
            [
                'X-GitHub-Event' => 'push',
                'X-Hub-Signature-256' => 'sha256=not-the-right-signature',
            ]
        )->assertUnauthorized();

        $this->assertDatabaseCount('releases', 0);
    }

    public function test_the_ping_event_is_acknowledged_without_a_release(): void
    {
        $payload = ['zen' => 'Keep it logically awesome.'];

        $this->postJson(
            '/api/webhooks/github',
            $payload,
            [
                'X-GitHub-Event' => 'ping',
                'X-Hub-Signature-256' => $this->signature($payload),
            ]
        )->assertOk();

        $this->assertDatabaseCount('releases', 0);
    }

    public function test_an_unknown_repository_is_not_found(): void
    {
        $payload = $this->pushPayload('refs/heads/main', ['id' => 'abcdef1234567890'], 'acme/unknown-repo');

        $this->postJson(
            '/api/webhooks/github',
            $payload,
            ['X-GitHub-Event' => 'push', 'X-Hub-Signature-256' => $this->signature($payload)]
        )->assertNotFound();
    }
}
