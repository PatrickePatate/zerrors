<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaultIssueResource;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Database\ContainsSearch;
use App\Support\Fault\IssueWorkflow;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

class IssueApiController extends Controller
{
    public function index(Request $request, Organization $organization, FaultProject $project)
    {
        $this->authorizeAccess($request, $organization, $project);

        $filters = $request->validate([
            'status' => ['nullable', Rule::in(['unresolved', 'resolved', 'ignored'])],
            'level' => ['nullable', 'string', 'max:50'],
            'q' => ['nullable', 'string', 'max:255'],
            'since' => ['nullable', 'date'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $query = $project->issues()->orderByDesc('last_seen_at');

        if (! empty($filters['status'])) {
            $query->where('status', $filters['status']);
        }

        if (! empty($filters['level'])) {
            $query->where('level', $filters['level']);
        }

        if (isset($filters['q']) && $filters['q'] !== '') {
            ContainsSearch::apply($query, ['title', 'culprit', 'type'], $filters['q']);
        }

        if (! empty($filters['since'])) {
            $query->where('last_seen_at', '>=', $filters['since']);
        }

        return FaultIssueResource::collection($query->paginate((int) ($filters['per_page'] ?? 50)));
    }

    public function show(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        $this->authorizeAccess($request, $organization, $project);
        abort_unless($issue->fault_project_id === $project->id, 404);

        return new FaultIssueResource($issue);
    }

    public function update(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        $this->authorizeAccess($request, $organization, $project);
        Gate::authorize('write', $organization);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $data = $request->validate([
            'status' => ['sometimes', Rule::in(['unresolved', 'resolved', 'ignored'])],
            'assigned_to_user_id' => [
                'sometimes', 'nullable', 'integer',
                Rule::exists('organization_user', 'user_id')->where('organization_id', $organization->id),
            ],
        ]);

        $workflow = app(IssueWorkflow::class);

        if (array_key_exists('status', $data) && $data['status'] !== $issue->status) {
            match ($data['status']) {
                'resolved' => $workflow->resolve($organization, $issue, $request->user()),
                'ignored' => $workflow->ignore($organization, $issue, $request->user()),
                'unresolved' => $workflow->unresolve($organization, $issue, $request->user()),
            };
        }

        if (array_key_exists('assigned_to_user_id', $data) && $data['assigned_to_user_id'] !== $issue->assigned_to_user_id) {
            $workflow->assign($organization, $issue, $request->user(), $data['assigned_to_user_id']);
        }

        return new FaultIssueResource($issue->refresh());
    }

    protected function authorizeAccess(Request $request, Organization $organization, FaultProject $project): void
    {
        Gate::authorize('view', $organization);
        abort_unless($project->organization_id === $organization->id, 404);
    }
}
