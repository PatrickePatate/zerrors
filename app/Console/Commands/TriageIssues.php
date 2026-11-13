<?php

namespace App\Console\Commands;

use App\Jobs\Fault\TriageFaultIssue;
use App\Models\FaultIssue;
use Illuminate\Console\Command;

class TriageIssues extends Command
{
    protected $signature = 'zerrors:triage-issues {--project= : Only triage issues of this project ID} {--limit=100 : Maximum issues to queue}';

    protected $description = 'Queue AI triage for unresolved issues that have not been triaged yet';

    public function handle(): int
    {
        $queued = 0;

        FaultIssue::query()
            ->with('project.organization')
            ->where('status', 'unresolved')
            ->whereNull('triaged_at')
            ->when($this->option('project'), fn ($query, $project) => $query->where('fault_project_id', $project))
            ->latest('last_seen_at')
            ->limit((int) $this->option('limit'))
            ->get()
            ->each(function (FaultIssue $issue) use (&$queued): void {
                if ($issue->project->organization->canTriageIssues()) {
                    TriageFaultIssue::dispatch($issue->id);
                    $queued++;
                }
            });

        $this->info("Queued {$queued} issue(s) for triage.");

        return self::SUCCESS;
    }
}
