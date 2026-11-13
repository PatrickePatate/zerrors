<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\FaultEventDetailResource;
use App\Http\Resources\FaultEventResource;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Models\Organization;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class EventApiController extends Controller
{
    public function index(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue)
    {
        $this->authorizeAccess($request, $organization, $project, $issue);

        return FaultEventResource::collection(
            $issue->events()
                ->select(['id', 'fault_issue_id', 'event_id', 'level', 'message', 'environment', 'release', 'occurred_at'])
                ->orderByDesc('occurred_at')
                ->orderByDesc('id')
                ->paginate(50)
        );
    }

    public function show(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue, FaultEvent $event)
    {
        $this->authorizeAccess($request, $organization, $project, $issue);
        abort_unless($event->fault_issue_id === $issue->id, 404);

        return new FaultEventDetailResource($event);
    }

    protected function authorizeAccess(Request $request, Organization $organization, FaultProject $project, FaultIssue $issue): void
    {
        Gate::authorize('view', $organization);
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);
    }
}
