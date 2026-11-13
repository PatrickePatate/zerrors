<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\FaultProject;
use App\Models\Organization;
use App\Models\SourceMap;
use App\Support\Fault\InvalidSourceMapException;
use App\Support\Fault\SourceMapDecoder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Storage;

class SourceMapApiController extends Controller
{
    public function index(Request $request, Organization $organization, FaultProject $project, string $version): JsonResponse
    {
        $this->authorizeAccess($request, $organization, $project);

        $maps = $project->sourceMaps()->where('release', $version)->orderBy('artifact_name')->get();

        return response()->json(['data' => $maps->map(fn (SourceMap $map) => $this->present($map))->all()]);
    }

    public function store(Request $request, Organization $organization, FaultProject $project, string $version): JsonResponse
    {
        $this->authorizeAccess($request, $organization, $project, manage: true);

        $maxKilobytes = (int) ceil(config('fault.max_source_map_bytes') / 1024);

        $data = $request->validate([
            'file' => ['required', 'file', "max:{$maxKilobytes}"],
            'name' => ['required', 'string', 'max:1024'],
        ]);

        $name = $data['name'];
        $hash = SourceMap::hashArtifactName($name);
        $existing = $project->sourceMaps()->where('release', $version)->where('artifact_hash', $hash)->first();

        if (! $existing && $project->sourceMaps()->where('release', $version)->count() >= config('fault.max_source_maps_per_release')) {
            return response()->json(['message' => 'This release already has the maximum number of source maps.'], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $contents = (string) file_get_contents($request->file('file')->getRealPath());

        try {
            SourceMapDecoder::fromJson($contents);
        } catch (InvalidSourceMapException $e) {
            return response()->json(['message' => $e->getMessage(), 'errors' => ['file' => [$e->getMessage()]]], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $path = SourceMap::storagePath($project->id, $version, $name);
        Storage::put($path, $contents);

        $map = $project->sourceMaps()->updateOrCreate(
            ['release' => $version, 'artifact_hash' => $hash],
            ['artifact_name' => $name, 'path' => $path, 'size' => strlen($contents), 'sha1' => sha1($contents)],
        );

        Cache::forget(SourceMap::existenceCacheKey($project->id, $version));

        return response()->json(['data' => $this->present($map)], $existing ? Response::HTTP_OK : Response::HTTP_CREATED);
    }

    public function destroy(Request $request, Organization $organization, FaultProject $project, string $version, int $sourceMap): Response
    {
        $this->authorizeAccess($request, $organization, $project, manage: true);

        $project->sourceMaps()->where('release', $version)->findOrFail($sourceMap)->delete();

        return response()->noContent();
    }

    /**
     * @return array{id: int, release: string, name: string, size: int, sha1: string, created_at: ?string}
     */
    private function present(SourceMap $map): array
    {
        return [
            'id' => $map->id,
            'release' => $map->release,
            'name' => $map->artifact_name,
            'size' => (int) $map->size,
            'sha1' => $map->sha1,
            'created_at' => $map->updated_at?->toIso8601String(),
        ];
    }

    protected function authorizeAccess(Request $request, Organization $organization, FaultProject $project, bool $manage = false): void
    {
        Gate::authorize($manage ? 'manage' : 'view', $organization);
        abort_unless($project->organization_id === $organization->id, 404);
    }
}
