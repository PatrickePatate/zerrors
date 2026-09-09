<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\Release;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;

class ReleaseController extends Controller
{
    public function store(Request $request, Organization $organization, FaultProject $project)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:255'],
            'notes' => ['nullable', 'string', 'max:2000'],
        ]);

        $release = $project->releases()->create($data + ['deployed_at' => now()]);

        app(AuditLogger::class)->log($organization, $request->user(), 'release.created', $release->version, [
            'project' => $project->name,
        ]);

        return back()->with('status', "Release {$release->version} marked as deployed.");
    }

    public function destroy(Request $request, Organization $organization, FaultProject $project, Release $release)
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless($release->fault_project_id === $project->id, 404);
        abort_unless(in_array($organization->roleFor($request->user()), ['owner', 'admin'], true), 403);

        $release->delete();

        return back()->with('status', 'Release removed.');
    }
}
