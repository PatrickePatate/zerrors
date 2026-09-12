<?php

namespace App\Jobs;

use App\Enums\MonitorStatus;
use App\Events\MonitorStatusChanged;
use App\Models\Monitor;
use App\Services\Monitoring\MonitorCheckRunner;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;

class RunMonitorCheck implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public function __construct(public Monitor $monitor) {}

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
