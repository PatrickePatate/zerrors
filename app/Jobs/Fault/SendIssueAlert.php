<?php

namespace App\Jobs\Fault;

use App\Models\FaultIssue;
use App\Support\Fault\IssueAlertNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;

/**
 * Delivers the alerts for one processed event. Kept off the fault-ingest
 * queue so slow Slack/Telegram/mail calls never delay ingestion.
 */
class SendIssueAlert implements ShouldQueue
{
    use InteractsWithQueue, Queueable;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  int  $timesSeen  The exact occurrence count computed at ingest time, so
     *                          occurrence thresholds match even if the issue row has
     *                          since been flushed or bumped further.
     */
    public function __construct(
        public int $issueId,
        public bool $wasNew,
        public bool $isRegression,
        public int $timesSeen,
    ) {
        $this->onQueue('default');
    }

    public function handle(IssueAlertNotifier $notifier): void
    {
        $issue = FaultIssue::with('project.organization')->find($this->issueId);

        if (! $issue) {
            return;
        }

        $issue->times_seen = $this->timesSeen;

        $notifier->notify($issue, $this->wasNew, $this->isRegression);
    }
}
