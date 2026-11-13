<?php

namespace App\Observers;

use App\Jobs\Fault\TriageFaultIssue;
use App\Models\FaultIssue;

class FaultIssueTriageObserver
{
    /**
     * Queue AI triage for brand-new issues of organizations that enabled it.
     */
    public function created(FaultIssue $issue): void
    {
        if ($issue->project?->organization?->canTriageIssues()) {
            TriageFaultIssue::dispatch($issue->id);
        }
    }
}
