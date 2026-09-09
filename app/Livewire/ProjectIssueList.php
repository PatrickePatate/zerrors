<?php

namespace App\Livewire;

use App\Models\FaultProject;
use App\Models\Organization;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectIssueList extends Component
{
    use WithPagination;

    public Organization $organization;

    public FaultProject $project;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $level = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $assigned = '';

    public function mount(Organization $organization, FaultProject $project): void
    {
        $this->organization = $organization;
        $this->project = $project;
    }

    public function updating(): void
    {
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'level', 'status', 'assigned');
    }

    public function render()
    {
        $issues = $this->project->issues()
            ->with('assignee')
            ->when($this->search !== '', fn ($q) => $q->where(fn ($q) => $q
                ->where('title', 'like', "%{$this->search}%")
                ->orWhere('culprit', 'like', "%{$this->search}%")
                ->orWhere('type', 'like', "%{$this->search}%")
            ))
            ->when($this->level !== '', fn ($q) => $q->where('level', $this->level))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->assigned === 'me', fn ($q) => $q->where('assigned_to_user_id', auth()->id()))
            ->when($this->assigned === 'unassigned', fn ($q) => $q->whereNull('assigned_to_user_id'))
            ->orderByDesc('last_seen_at')
            ->paginate(25);

        return view('livewire.project-issue-list', ['issues' => $issues]);
    }
}
