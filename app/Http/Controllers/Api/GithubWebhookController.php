<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaultProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class GithubWebhookController extends Controller
{
    /**
     * Receives GitHub's "push" webhook. Configure it on the repository as:
     * Payload URL: $project->githubWebhookUrl(), Content type: application/json,
     * Secret: $project->github_webhook_secret, event: "Just the push event".
     *
     * A release is recorded automatically whenever a commit lands on the
     * project's configured production branch (which is what a merged PR
     * ultimately produces: a push to that branch).
     */
    public function handle(Request $request, string $publicKey)
    {
        $project = $this->authenticate($request, $publicKey);

        if ($request->header('X-GitHub-Event') === 'ping') {
            return response()->json(['message' => 'pong']);
        }

        abort_unless($request->header('X-GitHub-Event') === 'push', Response::HTTP_BAD_REQUEST, 'Unsupported event');

        $payload = $this->payload($request);

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
     * Resolves the project from the public key in the URL, then verifies GitHub's
     * HMAC signature over the raw request body using the project's webhook secret.
     */
    protected function authenticate(Request $request, string $publicKey): FaultProject
    {
        $project = Cache::remember(
            "fault:project:webhook:{$publicKey}",
            300,
            fn () => FaultProject::where('public_key', $publicKey)->first()
        );

        abort_unless($project && $project->hasGithubWebhookConfigured(), Response::HTTP_NOT_FOUND);

        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_unless($signature !== '', Response::HTTP_UNAUTHORIZED, 'Missing signature');

        $expected = 'sha256='.hash_hmac('sha256', $request->getContent(), $project->github_webhook_secret);

        abort_unless(hash_equals($expected, $signature), Response::HTTP_UNAUTHORIZED, 'Invalid signature');

        return $project;
    }
}
