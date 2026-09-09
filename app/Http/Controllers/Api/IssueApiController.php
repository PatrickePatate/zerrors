<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaultIssueResource;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\Http\Request;

class IssueApiController extends Controller
{
    public function index(Request $request, Organization $organization, FaultProject $project)
    {
        $this->authorizeAccess($request, $organization, $project);

        return FaultIssueResource::collection(
            $project->issues()->orderByDesc('last_seen_at')->paginate(50)
        );
    }

    public function show(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        $this->authorizeAccess($request, $organization, $project);
        abort_unless($issue->fault_project_id === $project->id, 404);

        return new FaultIssueResource($issue);
    }

    protected function authorizeAccess(Request $request, Organization $organization, FaultProject $project): void
    {
        abort_unless($organization->roleFor($request->user()) !== null, 403);
        abort_unless($project->organization_id === $organization->id, 404);
    }
}
