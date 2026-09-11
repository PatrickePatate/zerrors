<?php

namespace App\Support\Fault;

use App\Models\Release;
use App\Support\Github\GithubAppClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubCommitFetcher
{
    public function __construct(protected GithubAppClient $github) {}

    /**
     * Fetches the commit's full details (message, author, changed files) from the
     * GitHub API and caches them on the release, so an issue linked to it can
     * display the commit that most likely introduced the regression.
     */
    public function fetch(Release $release): Release
    {
        $project = $release->project;

        if (! $project->hasGithubConfigured()) {
            throw new RuntimeException('No GitHub repository connected for this project yet.');
        }

        if (! $release->commit_sha) {
            throw new RuntimeException('This release has no commit SHA to look up.');
        }

        $response = Http::withToken($this->github->installationToken($project->organization->github_installation_id))
            ->acceptJson()
            ->get("https://api.github.com/repos/{$project->github_repo}/commits/{$release->commit_sha}");

        if ($response->failed()) {
            throw new RuntimeException('GitHub API request failed: '.($response->json('message') ?? $response->status()));
        }

        $files = collect($response->json('files', []))
            ->map(fn (array $file) => [
                'filename' => $file['filename'],
                'status' => $file['status'],
                'additions' => $file['additions'],
                'deletions' => $file['deletions'],
            ])
            ->all();

        $release->update([
            'commit_url' => $response->json('html_url'),
            'commit_message' => $response->json('commit.message'),
            'commit_author' => $response->json('commit.author.name') ?? $response->json('author.login'),
            'committed_at' => $response->json('commit.author.date'),
            'commit_files' => $files,
            'commit_additions' => $response->json('stats.additions'),
            'commit_deletions' => $response->json('stats.deletions'),
        ]);

        return $release;
    }
}
