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

    /**
     * Overrides the queue worker's/Horizon supervisor's default job timeout
     * (60s), which is far shorter than this job's own worst-case runtime.
     * Under Horizon's `auto` balancing, a job still running past the
     * supervisor timeout is treated as "hanging" and force-killed on scale
     * down — abandoning it mid-run so it gets redelivered and reprocessed,
     * sending a duplicate notification for the same status change. Setting
     * this per-job timeout keeps Horizon from ever mistaking a legitimately
     * slow check for a hung one.
     */
    public int $timeout;

    public function __construct(public Monitor $monitor)
    {
        $this->timeout = $this->worstCaseCheckSeconds();
    }

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
     * prevent.
     */
    public function uniqueFor(): int
    {
        return $this->worstCaseCheckSeconds();
    }

    /**
     * 3 HTTP attempts (1 try + 2 retries) plus one certificate check, each up
     * to timeout_seconds (capped at 120s), so 4 * timeout_seconds comfortably
     * covers it; +60s buffer for the DB write and queue overhead around it.
     */
    private function worstCaseCheckSeconds(): int
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
