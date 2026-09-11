<?php

namespace App\Support\Fault;

use App\Models\FaultIssue;
use App\Support\Github\GithubAppClient;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubIssueCreator
{
    public function __construct(protected GithubAppClient $github) {}

    public function create(FaultIssue $issue): FaultIssue
    {
        $project = $issue->project;

        if (! $project->hasGithubConfigured()) {
            throw new RuntimeException('No GitHub repository connected for this project yet.');
        }

        $latestEvent = $issue->events()->latest('occurred_at')->first();

        $issueUrl = route('organizations.issues.show', [
            'organization' => $project->organization,
            'project' => $project,
            'issue' => $issue,
        ]);

        $body = "**{$issue->title}**\n\n"
            .($issue->culprit ? "Culprit: `{$issue->culprit}`\n" : '')
            ."Level: {$issue->level} · seen {$issue->times_seen} time(s)\n\n"
            .($latestEvent?->message ? "```\n{$latestEvent->message}\n```\n\n" : '')
            ."[View in Zerrors]({$issueUrl})\n\n"
            .'Reported by Zerrors.';

        $response = Http::withToken($this->github->installationToken($project->organization->github_installation_id))
            ->acceptJson()
            ->post("https://api.github.com/repos/{$project->github_repo}/issues", [
                'title' => $issue->title,
                'body' => $body,
            ]);

        if ($response->failed()) {
            throw new RuntimeException('GitHub API request failed: '.($response->json('message') ?? $response->status()));
        }

        $issue->update([
            'github_issue_url' => $response->json('html_url'),
            'github_issue_number' => $response->json('number'),
        ]);

        return $issue;
    }
}
