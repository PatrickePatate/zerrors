<?php

namespace App\Livewire;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\Organization;
use Livewire\Component;

class RecentIssueList extends Component
{
    public Organization $organization;

    public int $limit = 10;

    public function mount(Organization $organization, int $limit = 10): void
    {
        $this->organization = $organization;
        $this->limit = $limit;
    }

    public function updateIssueStatus(int $issueId, string $status): void
    {
        abort_unless(in_array($status, ['unresolved', 'resolved', 'ignored'], true), 422);

        $issue = $this->issueQuery()->findOrFail($issueId);
        $issue->update(['status' => $status]);
    }

    public function assignIssue(int $issueId, ?int $userId): void
    {
        if ($userId !== null && ! $this->organization->users()->where('user_id', $userId)->exists()) {
            return;
        }

        $issue = $this->issueQuery()->findOrFail($issueId);
        $issue->update(['assigned_to_user_id' => $userId]);
    }

    protected function issueQuery()
    {
        return FaultIssue::whereIn('fault_project_id', $this->organization->projects()->pluck('id'));
    }

    public function render()
    {
        $issues = $this->issueQuery()
            ->with(['project', 'assignee', 'latestEvent:'.FaultEvent::BADGE_COLUMNS])
            ->orderByDesc('last_seen_at')
            ->limit($this->limit)
            ->get();

        return view('livewire.recent-issue-list', [
            'issues' => $issues,
            'members' => $this->organization->users()->orderBy('name')->get(),
        ]);
    }
}
