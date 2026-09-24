<?php

namespace App\Jobs;

use App\Enums\MonitorStatus;
use App\Events\MonitorStatusChanged;
use App\Models\Monitor;
use App\Services\Monitoring\MonitorCheckRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\Middleware\WithoutOverlapping;
use Illuminate\Queue\SerializesModels;

class RunMonitorCheck implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Monitor $monitor) {}

    /**
     * A monitor's `last_checked_at` (which the dispatcher uses to decide
     * whether it's due) is only updated once this job finishes, so a check
     * that runs long can otherwise get dispatched again before it's done.
     * Two concurrent runs would then both read the same stale previous
     * status and could both fire a status-changed event.
     *
     * @return array<int, object>
     */
    public function middleware(): array
    {
        return [(new WithoutOverlapping($this->monitor->id))->dontRelease()];
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
