<?php

namespace App\Jobs\Fault;

use App\Models\FaultEvent;
use App\Models\FaultFeedback;
use App\Models\FaultProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class ProcessFaultFeedback implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{event_id: ?string, name: ?string, email: ?string, comments: string}  $data
     */
    public function __construct(public int $projectId, public array $data)
    {
        $this->onQueue('fault-ingest');
    }

    /**
     * Stores the feedback, linking it to its event and issue when the event
     * is already known. When it arrives later, ProcessFaultEvent links it.
     */
    public function handle(): void
    {
        if (! FaultProject::whereKey($this->projectId)->exists()) {
            return;
        }

        $event = $this->data['event_id']
            ? FaultEvent::where('fault_project_id', $this->projectId)->where('event_id', $this->data['event_id'])->first(['id', 'fault_issue_id'])
            : null;

        FaultFeedback::create([
            'fault_project_id' => $this->projectId,
            'fault_event_id' => $event?->id,
            'fault_issue_id' => $event?->fault_issue_id,
            'event_id' => $this->data['event_id'],
            'name' => $this->data['name'],
            'email' => $this->data['email'],
            'comments' => $this->data['comments'],
        ]);
    }
}
