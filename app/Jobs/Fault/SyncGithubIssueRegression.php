<?php

namespace App\Jobs\Fault;

use App\Models\FaultIssue;
use App\Support\Fault\GithubIssueSync;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class SyncGithubIssueRegression implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    public int $backoff = 30;

    public function __construct(public int $issueId) {}

    public function handle(GithubIssueSync $sync): void
    {
        $issue = FaultIssue::find($this->issueId);

        if (! $issue || ! $issue->github_issue_number) {
            return;
        }

        $sync->commentRegression($issue);
        $sync->syncStatus($issue);
    }
}
