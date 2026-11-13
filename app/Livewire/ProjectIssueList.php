<?php

namespace App\Livewire;

use App\Jobs\Fault\MergeFaultIssues;
use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Database\ContainsSearch;
use App\Support\Fault\IssueMerger;
use App\Support\Fault\IssueWorkflow;
use App\Support\Organization\AuditLogger;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Livewire\Attributes\Locked;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

class ProjectIssueList extends Component
{
    use AuthorizesOrganizationAccess;
    use WithPagination;

    #[Locked]
    public Organization $organization;

    #[Locked]
    public FaultProject $project;

    #[Url(as: 'q', history: true)]
    public string $search = '';

    #[Url(history: true)]
    public string $level = '';

    #[Url(history: true)]
    public string $status = '';

    #[Url(history: true)]
    public string $assigned = '';

    #[Url(history: true)]
    public string $handled = '';

    /** @var list<int> */
    public array $selected = [];

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
        $this->reset('search', 'level', 'status', 'assigned', 'handled');
    }

    public function updateIssueStatus(int $issueId, string $status): void
    {
        abort_unless(in_array($status, ['unresolved', 'resolved', 'ignored'], true), 422);
        $this->authorizeMember();

        $this->applyStatus($this->project->issues()->findOrFail($issueId), $status);
    }

    public function assignIssue(int $issueId, ?int $userId): void
    {
        $this->authorizeMember();

        if ($userId !== null && ! $this->organization->users()->where('user_id', $userId)->exists()) {
            return;
        }

        app(IssueWorkflow::class)->assign($this->organization, $this->project->issues()->findOrFail($issueId), auth()->user(), $userId);
    }

    public function toggleSelectPage(): void
    {
        $pageIds = $this->filteredQuery()->orderByDesc('last_seen_at')->forPage($this->getPage(), 25)->pluck('id')->map(fn ($id) => (int) $id)->all();

        $this->selected = array_diff($pageIds, $this->selected) === []
            ? array_values(array_diff($this->selected, $pageIds))
            : array_values(array_unique([...$this->selected, ...$pageIds]));
    }

    public function clearSelection(): void
    {
        $this->selected = [];
    }

    public function bulkResolve(string $mode = 'now'): void
    {
        $this->bulkStatus('resolved', $mode);
    }

    public function bulkIgnore(string $until = 'forever'): void
    {
        $this->bulkStatus('ignored', $until);
    }

    public function bulkUnresolve(): void
    {
        $this->bulkStatus('unresolved');
    }

    public function bulkAssign(?int $userId): void
    {
        $issues = $this->selectedIssues();

        if ($userId !== null && ! $this->organization->users()->where('user_id', $userId)->exists()) {
            abort(422);
        }

        $workflow = app(IssueWorkflow::class);
        foreach ($issues as $issue) {
            $workflow->assign($this->organization, $issue, auth()->user(), $userId);
        }

        $this->selected = [];
    }

    /**
     * Merge the selected issues into the one with the most events.
     */
    public function bulkMerge(): void
    {
        $issues = $this->selectedIssues();
        abort_if($issues->count() < 2, 422);

        $target = $issues->sortByDesc('times_seen')->first();

        MergeFaultIssues::dispatch(
            $this->project->id,
            $target->id,
            $issues->pluck('id')->reject(fn ($id) => $id === $target->id)->values()->all(),
            auth()->id(),
        );

        app(AuditLogger::class)->log($this->organization, auth()->user(), 'issue.merged', $target->title, [
            'target_id' => $target->id,
            'merged_ids' => $issues->pluck('id')->reject(fn ($id) => $id === $target->id)->values()->all(),
        ]);

        $this->selected = [];
    }

    protected function bulkStatus(string $status, string $option = 'now'): void
    {
        $issues = $this->selectedIssues();

        foreach ($issues as $issue) {
            $this->applyStatus($issue, $status, $option);
        }

        $this->selected = [];
    }

    protected function applyStatus(FaultIssue $issue, string $status, string $option = 'now'): void
    {
        $workflow = app(IssueWorkflow::class);

        match ($status) {
            'resolved' => $workflow->resolve($this->organization, $issue, auth()->user(), $option),
            'ignored' => $workflow->ignore($this->organization, $issue, auth()->user(), $option === 'now' ? 'forever' : $option),
            'unresolved' => $workflow->unresolve($this->organization, $issue, auth()->user()),
        };
    }

    /**
     * Load the selected issues, rejecting the action if any id is missing from
     * this project or the selection is empty or too large.
     *
     * @return Collection<int, FaultIssue>
     */
    protected function selectedIssues(): Collection
    {
        $this->authorizeMember();

        $ids = collect($this->selected)->map(fn ($id) => (int) $id)->unique()->values();

        abort_if($ids->isEmpty() || $ids->count() > IssueMerger::MAX_ISSUES, 422);

        $issues = $this->project->issues()->whereIn('id', $ids)->get();

        abort_unless($issues->count() === $ids->count(), 422);

        return $issues;
    }

    protected function authorizeMember(): void
    {
        $this->authorizeWrite($this->organization);
        abort_unless($this->project->organization_id === $this->organization->id, 404);
    }

    protected function filteredQuery(): HasMany
    {
        $term = trim($this->search);

        return $this->project->issues()
            ->when(mb_strlen($term) >= 2, fn ($q) => ContainsSearch::apply($q, ['title', 'culprit', 'type'], $term))
            ->when($this->level !== '', fn ($q) => $q->where('level', $this->level))
            ->when($this->status !== '', fn ($q) => $q->where('status', $this->status))
            ->when($this->assigned === 'me', fn ($q) => $q->where('assigned_to_user_id', auth()->id()))
            ->when($this->assigned === 'unassigned', fn ($q) => $q->whereNull('assigned_to_user_id'))
            ->when($this->handled !== '', fn ($q) => $q->where(
                'latest_event_handled',
                $this->handled === 'handled'
            ));
    }

    public function render()
    {
        $issues = $this->filteredQuery()
            ->with(['assignee', 'latestEvent:'.FaultEvent::BADGE_COLUMNS])
            ->orderByDesc('last_seen_at')
            ->paginate(25);

        return view('livewire.project-issue-list', [
            'issues' => $issues,
            'members' => $this->organization->users()->orderBy('name')->get(),
            'hasReleases' => $this->project->releases()->exists(),
        ]);
    }
}
