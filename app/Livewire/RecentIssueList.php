<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\Organization;
use App\Support\Fault\IssueWorkflow;
use Livewire\Attributes\Locked;
use Livewire\Component;

class RecentIssueList extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
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

        $this->authorizeMember();
        $issue = $this->issueQuery()->findOrFail($issueId);
        $workflow = app(IssueWorkflow::class);

        match ($status) {
            'resolved' => $workflow->resolve($this->organization, $issue, auth()->user()),
            'ignored' => $workflow->ignore($this->organization, $issue, auth()->user()),
            'unresolved' => $workflow->unresolve($this->organization, $issue, auth()->user()),
        };
    }

    public function assignIssue(int $issueId, ?int $userId): void
    {
        if ($userId !== null && ! $this->organization->users()->where('user_id', $userId)->exists()) {
            return;
        }

        $this->authorizeMember();
        app(IssueWorkflow::class)->assign($this->organization, $this->issueQuery()->findOrFail($issueId), auth()->user(), $userId);
    }

    protected function authorizeMember(): void
    {
        $this->authorizeWrite($this->organization);
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
