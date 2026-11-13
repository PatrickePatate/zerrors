<?php

namespace App\Jobs\Fault;

use App\Models\FaultProject;
use App\Support\Fault\CronMonitorTracker;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessCronCheckIn implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array<string, mixed>  $data
     */
    public function __construct(public int $projectId, public array $data)
    {
        $this->onQueue('fault-ingest');
    }

    public function handle(CronMonitorTracker $tracker): void
    {
        $project = FaultProject::find($this->projectId);

        if ($project) {
            $tracker->record($project, $this->data);
        }
    }
}
