<?php

namespace App\Jobs\Fault;

use App\Models\FaultAttachment;
use App\Models\FaultEvent;
use App\Models\FaultProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;

class ProcessFaultAttachment implements ShouldQueue
{
    use Queueable;

    /**
     * @param  array{event_id: string, filename: string, content_type: ?string, attachment_type: ?string, size: int, path: string}  $data
     */
    public function __construct(public int $projectId, public array $data)
    {
        $this->onQueue('fault-ingest');
    }

    /**
     * Records the already-stored file. Files that cannot be kept (project
     * gone, per-event cap reached across envelopes) are deleted.
     */
    public function handle(): void
    {
        $kept = FaultProject::whereKey($this->projectId)->exists()
            && FaultAttachment::where('fault_project_id', $this->projectId)->where('event_id', $this->data['event_id'])->count()
                < (int) config('fault.max_attachments_per_event');

        if (! $kept) {
            Storage::delete($this->data['path']);

            return;
        }

        FaultAttachment::create([
            ...$this->data,
            'fault_project_id' => $this->projectId,
            'fault_event_id' => FaultEvent::where('fault_project_id', $this->projectId)
                ->where('event_id', $this->data['event_id'])->value('id'),
        ]);
    }
}
