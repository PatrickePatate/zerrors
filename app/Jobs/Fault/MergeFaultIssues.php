<?php

namespace App\Jobs\Fault;

use App\Models\FaultIssue;
use App\Models\User;
use App\Support\Fault\IssueMerger;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;

class MergeFaultIssues implements ShouldQueue
{
    use Queueable;

    public int $tries = 1;

    /**
     * @param  list<int>  $sourceIds
     */
    public function __construct(
        public int $projectId,
        public int $targetId,
        public array $sourceIds,
        public ?int $userId = null,
    ) {}

    public function handle(IssueMerger $merger): void
    {
        $target = FaultIssue::where('fault_project_id', $this->projectId)->find($this->targetId);

        if (! $target) {
            return;
        }

        $sources = FaultIssue::where('fault_project_id', $this->projectId)->whereIn('id', $this->sourceIds)->get();

        $merger->merge($target, $sources, $this->userId ? User::find($this->userId) : null);
    }
}
