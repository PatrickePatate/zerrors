<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Ai\IssueAnalyzer;
use App\Support\Fault\GithubIssueCreator;
use Illuminate\Http\Request;
use Illuminate\View\View;
use RuntimeException;

class IssueController extends Controller
{
    public function show(Organization $organization, FaultProject $project, FaultIssue $issue, ?string $event = null): View
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $currentEvent = $this->resolveEvent($issue, $event);

        return view('fault.issues.show', [
            'organization' => $organization,
            'project' => $project,
            'issue' => $issue,
            'currentEvent' => $currentEvent,
            'eventNavigation' => $currentEvent ? $this->eventNavigation($issue, $currentEvent) : null,
            'events' => $issue->events()->latest('occurred_at')->paginate(20),
            'members' => $organization->users()->orderBy('name')->get(),
        ]);
    }

    /**
     * Resolve the event to display: the `latest`/`oldest` keyword, a specific event id, or (by
     * default) the most recently seen event for the issue.
     */
    private function resolveEvent(FaultIssue $issue, ?string $event): ?FaultEvent
    {
        return match ($event) {
            null, 'latest' => $issue->events()->orderByDesc('occurred_at')->orderByDesc('id')->first(),
            'oldest' => $issue->events()->orderBy('occurred_at')->orderBy('id')->first(),
            default => $issue->events()->where('id', $event)->firstOrFail(),
        };
    }

    /**
     * Build the oldest/newest/previous/next events around the current one, plus its
     * 1-based position among all of the issue's events, for the event navigation UI.
     *
     * @return array{oldest: ?FaultEvent, newest: ?FaultEvent, previous: ?FaultEvent, next: ?FaultEvent, position: int, total: int}
     */
    private function eventNavigation(FaultIssue $issue, FaultEvent $current): array
    {
        $before = fn () => $issue->events()->where(fn ($q) => $q->where('occurred_at', '<', $current->occurred_at)
            ->orWhere(fn ($q2) => $q2->where('occurred_at', $current->occurred_at)->where('id', '<', $current->id)));

        $after = fn () => $issue->events()->where(fn ($q) => $q->where('occurred_at', '>', $current->occurred_at)
            ->orWhere(fn ($q2) => $q2->where('occurred_at', $current->occurred_at)->where('id', '>', $current->id)));

        return [
            'oldest' => $issue->events()->orderBy('occurred_at')->orderBy('id')->first(),
            'newest' => $issue->events()->orderByDesc('occurred_at')->orderByDesc('id')->first(),
            'previous' => $before()->orderByDesc('occurred_at')->orderByDesc('id')->first(),
            'next' => $after()->orderBy('occurred_at')->orderBy('id')->first(),
            'position' => $before()->count() + 1,
            'total' => $issue->events()->count(),
        ];
    }

    public function update(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $data = $request->validate(['status' => ['required', 'in:unresolved,resolved,ignored']]);

        $issue->update($data);

        return back();
    }

    public function assign(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $data = $request->validate(['assigned_to_user_id' => ['nullable', 'integer']]);
        $userId = $data['assigned_to_user_id'] ?? null;

        if ($userId !== null) {
            abort_unless($organization->users()->where('user_id', $userId)->exists(), 422);
        }

        $issue->update(['assigned_to_user_id' => $userId]);

        return back();
    }

    public function analyze(Organization $organization, FaultProject $project, FaultIssue $issue, IssueAnalyzer $analyzer)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        try {
            $analyzer->analyze($issue);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['ai' => 'The AI provider request failed: '.$e->getMessage()]);
        }

        return back()->with('status', 'Analysis complete.');
    }

    public function deepen(Organization $organization, FaultProject $project, FaultIssue $issue, IssueAnalyzer $analyzer)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        try {
            $analyzer->deepen($issue);
        } catch (RuntimeException $e) {
            return back()->withErrors(['ai' => $e->getMessage()]);
        } catch (\Throwable $e) {
            report($e);

            return back()->withErrors(['ai' => 'The AI provider request failed: '.$e->getMessage()]);
        }

        return back()->with('status', 'Deeper explanation ready.');
    }

    public function createGithubIssue(Organization $organization, FaultProject $project, FaultIssue $issue, GithubIssueCreator $creator)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        try {
            $creator->create($issue);
        } catch (RuntimeException $e) {
            return back()->withErrors(['github' => $e->getMessage()]);
        }

        return back()->with('status', 'GitHub issue created.');
    }
}
