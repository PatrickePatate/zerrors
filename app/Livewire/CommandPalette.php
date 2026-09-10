<?php

namespace App\Livewire;

use App\Models\FaultIssue;
use App\Models\Organization;
use Illuminate\Support\Collection;
use Livewire\Component;

class CommandPalette extends Component
{
    public ?Organization $organization = null;

    public string $query = '';

    public function mount(?Organization $organization = null): void
    {
        $this->organization = $organization;
    }

    /**
     * Static navigation actions, scoped to the current organization when one is active.
     *
     * @return Collection<int, array{label: string, subtitle: string, url: string, icon: string}>
     */
    protected function actions(): Collection
    {
        if (! $this->organization) {
            return collect([
                ['label' => 'Go to dashboard', 'subtitle' => 'Switch organization', 'url' => route('dashboard'), 'icon' => 'lucide-home'],
            ]);
        }

        $organization = $this->organization;

        return collect([
            ['label' => 'New project', 'subtitle' => 'Create a project', 'url' => route('organizations.projects.index', $organization).'?new=1', 'icon' => 'lucide-plus'],
            ['label' => 'Projects', 'subtitle' => 'View all projects', 'url' => route('organizations.projects.index', $organization), 'icon' => 'lucide-folder'],
            ['label' => 'Members', 'subtitle' => 'Manage organization members', 'url' => route('organizations.members.index', $organization), 'icon' => 'lucide-users'],
            ['label' => 'Settings', 'subtitle' => 'Organization settings', 'url' => route('organizations.settings.edit', $organization), 'icon' => 'lucide-settings'],
            ['label' => 'Audit log', 'subtitle' => 'Recent organization activity', 'url' => route('organizations.audit.index', $organization), 'icon' => 'lucide-scroll-text'],
        ]);
    }

    /**
     * @return Collection<int, array{label: string, subtitle: string, url: string, icon: string}>
     */
    protected function matchingProjects(): Collection
    {
        if (! $this->organization || $this->query === '') {
            return collect();
        }

        return $this->organization->projects()
            ->where('name', 'like', "%{$this->query}%")
            ->orderBy('name')
            ->limit(5)
            ->get()
            ->map(fn ($project) => [
                'label' => $project->name,
                'subtitle' => 'Project',
                'url' => route('organizations.projects.show', [$this->organization, $project]),
                'icon' => 'lucide-folder',
            ]);
    }

    /**
     * @return Collection<int, array{label: string, subtitle: string, url: string, icon: string}>
     */
    protected function matchingIssues(): Collection
    {
        if (! $this->organization || $this->query === '') {
            return collect();
        }

        return FaultIssue::query()
            ->whereHas('project', fn ($q) => $q->where('organization_id', $this->organization->id))
            ->with('project')
            ->where(fn ($q) => $q
                ->where('title', 'like', "%{$this->query}%")
                ->orWhere('culprit', 'like', "%{$this->query}%")
            )
            ->orderByDesc('last_seen_at')
            ->limit(8)
            ->get()
            ->map(fn ($issue) => [
                'label' => $issue->title,
                'subtitle' => $issue->project->name.($issue->culprit ? " · {$issue->culprit}" : ''),
                'url' => route('organizations.issues.show', [$this->organization, $issue->project, $issue]),
                'icon' => 'lucide-bug',
            ]);
    }

    public function render()
    {
        $query = trim($this->query);

        $groups = [
            'Issues' => $this->matchingIssues(),
            'Projects' => $this->matchingProjects(),
            'Actions' => $query === ''
                ? $this->actions()
                : $this->actions()->filter(fn ($action) => str_contains(strtolower($action['label']), strtolower($query)))->values(),
        ];

        $groups = collect($groups)->filter(fn ($items) => $items->isNotEmpty());

        return view('livewire.command-palette', ['groups' => $groups]);
    }
}
