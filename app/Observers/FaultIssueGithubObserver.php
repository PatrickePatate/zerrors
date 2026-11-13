<?php

namespace App\Observers;

use App\Models\FaultIssue;
use App\Support\Fault\GithubIssueSync;
use Throwable;

class FaultIssueGithubObserver
{
    /**
     * Queues a push of the status change to the linked GitHub issue, so the
     * HTTP call never blocks the request that saved it. Webhook-driven changes
     * are saved with saveQuietly() so they never fire this listener (no
     * ping-pong loop).
     */
    public function updated(FaultIssue $issue): void
    {
        if (! $issue->wasChanged('status') || ! $issue->github_issue_number) {
            return;
        }

        $issueId = $issue->id;

        dispatch(function () use ($issueId): void {
            $issue = FaultIssue::find($issueId);

            if ($issue === null) {
                return;
            }

            try {
                app(GithubIssueSync::class)->syncStatus($issue);
            } catch (Throwable $exception) {
                report($exception);
            }
        });
    }
}
