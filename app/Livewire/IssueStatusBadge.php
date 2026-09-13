<?php

namespace App\Livewire;

use App\Models\FaultIssue;
use Livewire\Attributes\On;
use Livewire\Component;

class IssueStatusBadge extends Component
{
    public FaultIssue $issue;

    public function mount(FaultIssue $issue): void
    {
        $this->issue = $issue;
    }

    #[On('issue-status-updated')]
    public function refreshStatus(int $issueId): void
    {
        if ($issueId === $this->issue->id) {
            $this->issue->refresh();
        }
    }

    public function render()
    {
        return view('livewire.issue-status-badge');
    }
}
