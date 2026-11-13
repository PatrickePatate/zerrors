<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\ReleaseResource;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Support\Organization\AuditLogger;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Gate;

class ReleaseApiController extends Controller
{
    public function index(Request $request, Organization $organization, FaultProject $project)
    {
        $this->authorizeAccess($request, $organization, $project);

        return ReleaseResource::collection(
            $project->releases()->orderByDesc('deployed_at')->orderByDesc('id')->paginate(50)
        );
    }

    public function store(Request $request, Organization $organization, FaultProject $project)
    {
        $this->authorizeAccess($request, $organization, $project);
        Gate::authorize('manage', $organization);

        $data = $request->validate([
            'version' => ['required', 'string', 'max:255'],
            'commit_sha' => ['nullable', 'string', 'max:255'],
            'commit_url' => ['nullable', 'url', 'max:2048'],
            'commit_message' => ['nullable', 'string', 'max:65535'],
            'commit_author' => ['nullable', 'string', 'max:255'],
            'deployed_at' => ['nullable', 'date'],
        ]);

        $attributes = collect($data)->except('version')->all();
        $attributes['deployed_at'] ??= now();

        $release = $project->releases()->updateOrCreate(['version' => $data['version']], $attributes);

        if ($release->wasRecentlyCreated) {
            app(AuditLogger::class)->log($organization, $request->user(), 'release.created', $release->version, [
                'source' => 'api',
            ]);
        }

        return (new ReleaseResource($release))
            ->response()
            ->setStatusCode($release->wasRecentlyCreated ? Response::HTTP_CREATED : Response::HTTP_OK);
    }

    protected function authorizeAccess(Request $request, Organization $organization, FaultProject $project): void
    {
        Gate::authorize('view', $organization);
        abort_unless($project->organization_id === $organization->id, 404);
    }
}
