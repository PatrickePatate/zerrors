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

    public $uniqueFor = 300;

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
