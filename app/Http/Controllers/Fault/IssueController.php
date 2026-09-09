<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
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
    public function show(Organization $organization, FaultProject $project, FaultIssue $issue): View
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($issue->fault_project_id === $project->id, 404);

        $events = $issue->events()->latest('occurred_at')->paginate(20);

        return view('fault.issues.show', [
            'organization' => $organization,
            'project' => $project,
            'issue' => $issue,
            'events' => $events,
            'members' => $organization->users()->orderBy('name')->get(),
        ]);
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
