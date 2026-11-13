<?php

namespace App\Support\Fault;

use App\Models\FaultIssue;
use App\Support\Github\GithubAppClient;
use Illuminate\Http\Client\PendingRequest;
use Illuminate\Support\Facades\Http;
use RuntimeException;

class GithubIssueSync
{
    public function __construct(protected GithubAppClient $github) {}

    /**
     * Closes the linked GitHub issue when the Zerrors issue is resolved and
     * reopens it when unresolved. No-op for issues without a linked GitHub issue
     * or for other statuses.
     */
    public function syncStatus(FaultIssue $issue): void
    {
        $state = match ($issue->status) {
            'resolved' => 'closed',
            'unresolved' => 'open',
            default => null,
        };

        if ($state === null || ! $this->isLinked($issue)) {
            return;
        }

        $payload = ['state' => $state];

        if ($state === 'closed') {
            $payload['state_reason'] = 'completed';
        }

        $this->request($issue)->patch($this->issueUrl($issue), $payload)->throw();
    }

    /**
     * Comments on the linked GitHub issue that the issue was seen again.
     */
    public function commentRegression(FaultIssue $issue): void
    {
        if (! $this->isLinked($issue)) {
            return;
        }

        $release = $issue->events()->latest('occurred_at')->value('release');
        $where = $release ? "release `{$release}`" : 'a new release';

        $this->request($issue)->post($this->issueUrl($issue).'/comments', [
            'body' => "Seen again in {$where}, {$issue->times_seen} occurrence(s) so far.",
        ])->throw();
    }

    protected function isLinked(FaultIssue $issue): bool
    {
        $project = $issue->project;

        return $issue->github_issue_number
            && $project->hasGithubConfigured()
            && $project->organization->github_installation_id;
    }

    protected function issueUrl(FaultIssue $issue): string
    {
        return "https://api.github.com/repos/{$issue->project->github_repo}/issues/{$issue->github_issue_number}";
    }

    protected function request(FaultIssue $issue): PendingRequest
    {
        $token = $this->github->installationToken($issue->project->organization->github_installation_id);

        if ($token === '') {
            throw new RuntimeException('Missing GitHub installation token.');
        }

        return Http::withToken($token)->acceptJson();
    }
}
