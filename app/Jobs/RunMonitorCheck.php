<?php

namespace App\Jobs;

use App\Enums\MonitorStatus;
use App\Events\MonitorStatusChanged;
use App\Models\Monitor;
use App\Services\Monitoring\MonitorCheckRunner;
use Illuminate\Contracts\Queue\ShouldBeUnique;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMonitorCheck implements ShouldBeUnique, ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Monitor $monitor) {}

    /**
     * Keeps a slow-to-respond monitor from being checked by two overlapping
     * jobs at once: the scheduler dispatches every minute regardless of
     * whether the previous check for this monitor has finished, and since
     * current_status/last_checked_at are only updated once the HTTP request
     * completes, a check that takes longer than a minute would otherwise be
     * picked up as "due" again — producing two independent status-change
     * events (and duplicate notification emails) for the same transition.
     */
    public function uniqueId(): string
    {
        return (string) $this->monitor->id;
    }

    /**
     * A ceiling on how long the unique lock is held, in case a job dies
     * without releasing it — must exceed the check's worst-case runtime or
     * a still-running job's lock can expire and let a new one in, bringing
     * back the exact duplicate-notification race this class exists to
     * prevent. Worst case: 3 HTTP attempts (1 try + 2 retries) plus one
     * certificate check, each up to timeout_seconds (capped at 120s), so
     * 4 * timeout_seconds comfortably covers it.
     */
    public function uniqueFor(): int
    {
        return ($this->monitor->timeout_seconds * 4) + 60;
    }

    public function handle(MonitorCheckRunner $runner): void
    {
        $previousStatus = $this->monitor->current_status;

        $check = $runner->run($this->monitor);

        $newStatus = $check->status;

        $this->monitor->update([
            'current_status' => $newStatus,
            'last_checked_at' => $check->checked_at,
            'consecutive_failures' => $newStatus === MonitorStatus::Up ? 0 : $this->monitor->consecutive_failures + 1,
        ]);

        if ($previousStatus !== $newStatus) {
            MonitorStatusChanged::dispatch($this->monitor, $previousStatus, $newStatus);
        }
    }
}
