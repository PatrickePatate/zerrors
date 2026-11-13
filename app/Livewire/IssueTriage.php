<?php

namespace App\Livewire;

use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\FaultIssue;
use App\Support\Ai\IssueTriager;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Component;
use Throwable;

class IssueTriage extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
    public FaultIssue $issue;

    public function mount(FaultIssue $issue): void
    {
        $this->issue = $issue;
    }

    public function rerun(IssueTriager $triager): void
    {
        $organization = $this->issue->project->organization;

        $this->authorizeWrite($organization);

        if (! $organization->canTriageIssues()) {
            return;
        }

        try {
            $triager->triage($this->issue);
        } catch (Throwable $exception) {
            report($exception);
        }

        $this->issue->refresh();
    }

    /**
     * @return Collection<int, FaultIssue>
     */
    protected function duplicates()
    {
        $issue = $this->issue;

        if ($issue->type === null && $issue->culprit === null) {
            return collect();
        }

        return FaultIssue::query()
            ->where('fault_project_id', $issue->fault_project_id)
            ->where('status', 'unresolved')
            ->whereKeyNot($issue->id)
            ->when(
                $issue->type !== null,
                fn ($query) => $query->where('type', $issue->type)->where('culprit', $issue->culprit),
                fn ($query) => $query->whereNull('type')->where('title', $issue->title),
            )
            ->latest('last_seen_at')
            ->limit(3)
            ->get(['id', 'fault_project_id', 'title']);
    }

    public function render()
    {
        $organization = $this->issue->project->organization;

        return view('livewire.issue-triage', [
            'organization' => $organization,
            'project' => $this->issue->project,
            'duplicates' => $this->duplicates(),
            'canTriage' => $organization->canTriageIssues(),
        ]);
    }
}
