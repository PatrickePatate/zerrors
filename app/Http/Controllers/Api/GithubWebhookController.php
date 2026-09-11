<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaultProject;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GithubWebhookController extends Controller
{
    /**
     * Receives GitHub's "push" webhook, App-wide. Configured once on the GitHub
     * App itself (Payload URL: /api/webhooks/github, Content type: application/json,
     * Secret: services.github.webhook_secret, event: "Just the push event").
     *
     * A release is recorded automatically whenever a commit lands on the
     * project's configured production branch (which is what a merged PR
     * ultimately produces: a push to that branch).
     */
    public function handle(Request $request)
    {
        $this->verifySignature($request);

        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        abort_unless($request->header('X-GitHub-Event') === 'push', Response::HTTP_BAD_REQUEST, 'Unsupported event');

        $payload = $this->payload($request);

        $project = $this->resolveProject($payload);

        $branch = Str::after((string) ($payload['ref'] ?? ''), 'refs/heads/');

        if ($branch === '' || $branch !== $project->production_branch) {
            return response()->json(['message' => "Ignored: push was to \"{$branch}\", not the production branch."]);
        }

        $headCommit = $payload['head_commit'] ?? null;

        abort_unless(is_array($headCommit) && ! empty($headCommit['id']), Response::HTTP_UNPROCESSABLE_ENTITY, 'Missing head_commit in payload');

        $release = $project->releases()->updateOrCreate(
            ['version' => Str::substr($headCommit['id'], 0, 12)],
            [
                'deployed_at' => now(),
                'commit_sha' => $headCommit['id'],
                'commit_url' => $headCommit['url'] ?? null,
                'commit_message' => $headCommit['message'] ?? null,
                'commit_author' => $headCommit['author']['name'] ?? null,
                'committed_at' => $headCommit['timestamp'] ?? null,
            ]
        );

        return response()->json(['release' => $release->version], Response::HTTP_CREATED);
    }

    /**
     * Decodes the push event body. GitHub sends JSON directly when the webhook's
     * content type is configured as "application/json", but defaults to
     * "application/x-www-form-urlencoded" (the JSON nested inside a "payload"
     * form field) unless that's changed when the webhook is created — support
     * both so a webhook left on GitHub's default still creates releases.
     *
     * @return array<string, mixed>
     */
    protected function payload(Request $request): array
    {
        if ($request->has('payload') && is_string($request->input('payload'))) {
            return json_decode($request->input('payload'), true) ?? [];
        }

        return $request->json()->all();
    }

    /**
     * Resolves the project by matching the pushed repository's full name against
     * `github_repo`. When the payload carries an installation id, the matching
     * project's organization must also have that installation connected, so
     * two organizations that (mistakenly) configured the same repo name don't
     * collide.
     */
    protected function resolveProject(array $payload): FaultProject
    {
        $repoFullName = $payload['repository']['full_name'] ?? null;

        abort_unless(is_string($repoFullName) && $repoFullName !== '', Response::HTTP_UNPROCESSABLE_ENTITY, 'Missing repository in payload');

        $installationId = $payload['installation']['id'] ?? null;

        $query = FaultProject::query()
            ->join('organizations', 'organizations.id', '=', 'fault_projects.organization_id')
            ->where('fault_projects.github_repo', $repoFullName)
            ->whereNotNull('organizations.github_installation_id');

        if ($installationId !== null) {
            $query->where('organizations.github_installation_id', (string) $installationId);
        }

        $project = $query->select('fault_projects.*')->first();

        abort_unless($project !== null, Response::HTTP_NOT_FOUND, 'No project configured for this repository');

        return $project;
    }

    /**
     * Verifies GitHub's HMAC signature over the raw request body using the
     * GitHub App's shared webhook secret.
     */
    protected function verifySignature(Request $request): void
    {
        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_unless($signature !== '', Response::HTTP_UNAUTHORIZED, 'Missing signature');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), (string) config('services.github.webhook_secret'));

        abort_unless(hash_equals($expected, $signature), Response::HTTP_UNAUTHORIZED, 'Invalid signature');
    }
}
