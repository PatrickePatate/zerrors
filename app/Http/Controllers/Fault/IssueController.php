<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Ai\IssueAnalyzer;
use App\Support\Database\ContainsSearch;
use App\Support\Fault\GithubIssueCreator;
use App\Support\Fault\IssueWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\View\View;
use RuntimeException;

class IssueController extends Controller
{
    /**
     * Event list filters: request key => events column.
     */
    private const EVENT_FILTERS = ['browser', 'os', 'environment', 'release'];

    public function show(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue, ?string $event = null): View
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $currentEvent = $this->resolveEvent($issue, $event);

        $filters = $request->validate([
            'ip' => ['nullable', 'string', 'max:45'],
            ...array_fill_keys(self::EVENT_FILTERS, ['nullable', 'string', 'max:255']),
        ]);
        $filters = array_filter($filters, fn ($value) => $value !== null && $value !== '');

        $eventsQuery = $issue->events()->latest('occurred_at');

        foreach (self::EVENT_FILTERS as $column) {
            $eventsQuery->when($filters[$column] ?? null, fn ($q, $value) => $q->where($column, $value));
        }

        $eventsQuery->when($filters['ip'] ?? null, fn ($q, $ip) => ContainsSearch::apply($q, ['client_ip'], $ip));

        return view('fault.issues.show', [
            'organization' => $organization,
            'project' => $project,
            'issue' => $issue,
            'currentEvent' => $currentEvent,
            'eventNavigation' => $currentEvent ? $this->eventNavigation($issue, $currentEvent) : null,
            'events' => $eventsQuery->paginate(20)->withQueryString(),
            'eventFilters' => $filters,
            'eventFilterOptions' => collect(self::EVENT_FILTERS)->mapWithKeys(fn (string $column) => [
                $column => $issue->events()->whereNotNull($column)->distinct()->orderBy($column)->limit(50)->pluck($column),
            ]),
            'eventHistogram' => $this->eventHistogram($issue),
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
        $counts = $issue->events()
            ->selectRaw(
                'count(*) as total, sum(case when occurred_at < ? or (occurred_at = ? and id < ?) then 1 else 0 end) as before_count',
                [$current->occurred_at, $current->occurred_at, $current->id]
            )
            ->first();

        $total = (int) $counts->total;
        $beforeCount = (int) $counts->before_count;
        $afterCount = max(0, $total - $beforeCount - 1);

        $previous = null;
        $oldest = $current;
        if ($beforeCount > 0) {
            $previous = $issue->events()
                ->where(fn ($q) => $q->where('occurred_at', '<', $current->occurred_at)
                    ->orWhere(fn ($q2) => $q2->where('occurred_at', $current->occurred_at)->where('id', '<', $current->id)))
                ->orderByDesc('occurred_at')->orderByDesc('id')->first();
            $oldest = $beforeCount === 1
                ? $previous
                : $issue->events()->orderBy('occurred_at')->orderBy('id')->first();
        }

        $next = null;
        $newest = $current;
        if ($afterCount > 0) {
            $next = $issue->events()
                ->where(fn ($q) => $q->where('occurred_at', '>', $current->occurred_at)
                    ->orWhere(fn ($q2) => $q2->where('occurred_at', $current->occurred_at)->where('id', '>', $current->id)))
                ->orderBy('occurred_at')->orderBy('id')->first();
            $newest = $afterCount === 1
                ? $next
                : $issue->events()->orderByDesc('occurred_at')->orderByDesc('id')->first();
        }

        return [
            'oldest' => $oldest,
            'newest' => $newest,
            'previous' => $previous,
            'next' => $next,
            'position' => $beforeCount + 1,
            'total' => $total,
        ];
    }

    /**
     * Bucket the issue's events over its lifetime (first event → now) for the compact
     * events-over-time bar chart.
     *
     * @return list<array{start: int, end: int, count: int}>
     */
    private function eventHistogram(FaultIssue $issue, int $bucketCount = 48): array
    {
        return Cache::remember("fault-issue:{$issue->id}:histogram", 60, function () use ($issue, $bucketCount): array {
            $range = $issue->events()
                ->toBase()
                ->whereNotNull('occurred_at')
                ->selectRaw('min(occurred_at) as first_at, max(occurred_at) as last_at')
                ->first();

            if ($range === null || $range->first_at === null) {
                return [];
            }

            $start = strtotime($range->first_at.' UTC');
            $end = max(time(), strtotime($range->last_at.' UTC')) + 1;
            $bucketWidth = max(60, (int) ceil(($end - $start) / $bucketCount));

            $epoch = $this->epochExpression($issue);
            $bucketIndex = "floor(({$epoch} - {$start}) / {$bucketWidth})";

            $counts = $issue->events()
                ->toBase()
                ->whereNotNull('occurred_at')
                ->selectRaw("{$bucketIndex} as bucket, count(*) as aggregate")
                ->groupByRaw($bucketIndex)
                ->pluck('aggregate', 'bucket');

            $buckets = [];
            for ($i = 0; $i < $bucketCount; $i++) {
                $buckets[] = ['start' => $start + $i * $bucketWidth, 'end' => $start + ($i + 1) * $bucketWidth, 'count' => 0];
            }

            foreach ($counts as $bucket => $count) {
                $buckets[max(0, min($bucketCount - 1, (int) $bucket))]['count'] += (int) $count;
            }

            return $buckets;
        });
    }

    /**
     * SQL expression yielding the unix epoch (UTC) of the events.occurred_at column for the connection driver.
     */
    private function epochExpression(FaultIssue $issue): string
    {
        return match ($issue->getConnection()->getDriverName()) {
            'mysql', 'mariadb' => "TIMESTAMPDIFF(SECOND, '1970-01-01 00:00:00', occurred_at)",
            'pgsql' => 'EXTRACT(EPOCH FROM occurred_at)',
            default => "CAST(strftime('%s', occurred_at) AS INTEGER)",
        };
    }

    public function update(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        Gate::authorize('write', $organization);

        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $data = $request->validate(['status' => ['required', 'in:unresolved,resolved,ignored']]);

        $workflow = app(IssueWorkflow::class);

        match ($data['status']) {
            'resolved' => $workflow->resolve($organization, $issue, $request->user()),
            'ignored' => $workflow->ignore($organization, $issue, $request->user()),
            'unresolved' => $workflow->unresolve($organization, $issue, $request->user()),
        };

        return back();
    }

    public function assign(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        Gate::authorize('write', $organization);

        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $data = $request->validate(['assigned_to_user_id' => ['nullable', 'integer']]);
        $userId = $data['assigned_to_user_id'] ?? null;

        if ($userId !== null) {
            abort_unless($organization->users()->where('user_id', $userId)->exists(), 422);
        }

        app(IssueWorkflow::class)->assign($organization, $issue, $request->user(), $userId);

        return back();
    }

    public function analyze(Organization $organization, FaultProject $project, FaultIssue $issue, IssueAnalyzer $analyzer)
    {
        Gate::authorize('write', $organization);

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
        Gate::authorize('write', $organization);

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
        Gate::authorize('write', $organization);

        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        try {
            $creator->create($issue);
            FaultIssueActivity::record($issue, 'github_linked', ['number' => $issue->github_issue_number, 'url' => $issue->github_issue_url], request()->user());
        } catch (RuntimeException $e) {
            return back()->withErrors(['github' => $e->getMessage()]);
        }

        return back()->with('status', 'GitHub issue created.');
    }
}
