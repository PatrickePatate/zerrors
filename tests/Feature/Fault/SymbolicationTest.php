<?php

namespace Tests\Feature\Fault;

use App\Enums\FaultPlatform;
use App\Jobs\Fault\SymbolicateFaultEvent;
use App\Models\FaultEvent;
use App\Models\FaultProject;
use App\Models\SourceMap;
use App\Support\Fault\StacktraceFrame;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\Support\SourceMapDecoderTest;

class SymbolicationTest extends TestCase
{
    use RefreshDatabase;

    /**
     * @return array<string, mixed>
     */
    private function exception(string $absPath = 'https://example.com/assets/app.js?v=3'): array
    {
        return ['values' => [['type' => 'Error', 'value' => 'boom', 'stacktrace' => ['frames' => [
            ['filename' => $absPath, 'abs_path' => $absPath, 'function' => 'a', 'lineno' => 1, 'colno' => 21, 'in_app' => true],
            ['filename' => 'https://example.com/other.js', 'lineno' => 1, 'colno' => 5],
        ]]]]];
    }

    private function storeMap(FaultProject $project, string $name = '~/assets/app.js', string $release = '1.0.0'): SourceMap
    {
        Storage::fake();
        $contents = SourceMapDecoderTest::sampleMap();
        $path = SourceMap::storagePath($project->id, $release, $name);
        Storage::put($path, $contents);

        return SourceMap::factory()->create([
            'fault_project_id' => $project->id, 'release' => $release, 'artifact_name' => $name,
            'artifact_hash' => SourceMap::hashArtifactName($name), 'path' => $path, 'size' => strlen($contents),
        ]);
    }

    private function event(FaultProject $project, array $attributes = []): FaultEvent
    {
        return FaultEvent::factory()->create($attributes + [
            'fault_project_id' => $project->id, 'release' => '1.0.0', 'exception' => $this->exception(),
        ]);
    }

    public function test_the_job_rewrites_frames_and_keeps_the_minified_originals(): void
    {
        Bus::fake();
        $project = FaultProject::factory()->create(['platform' => FaultPlatform::JavaScript]);
        $this->storeMap($project);
        $event = $this->event($project);

        (new SymbolicateFaultEvent($event->id))->handle();

        $frames = $event->fresh()->exception['values'][0]['stacktrace']['frames'];
        $frame = $frames[0];

        $this->assertSame('src/app.js', $frame['filename']);
        $this->assertSame(3, $frame['lineno']);
        $this->assertSame(5, $frame['colno']);
        $this->assertSame('a', $frame['function']);
        $this->assertSame('  throw boom();', $frame['context_line']);
        $this->assertSame(['line one', 'line two'], $frame['pre_context']);
        $this->assertSame(['line four', 'line five'], $frame['post_context']);
        $this->assertSame([
            'filename' => 'https://example.com/assets/app.js?v=3', 'lineno' => 1, 'colno' => 21, 'function' => 'a',
        ], $frame['data']['minified']);
        $this->assertSame('https://example.com/other.js', $frames[1]['filename']);
    }

    public function test_running_the_job_twice_keeps_the_first_minified_originals(): void
    {
        Bus::fake();
        $project = FaultProject::factory()->create(['platform' => FaultPlatform::React]);
        $this->storeMap($project);
        $event = $this->event($project);

        (new SymbolicateFaultEvent($event->id))->handle();
        (new SymbolicateFaultEvent($event->id))->handle();

        $frame = $event->fresh()->exception['values'][0]['stacktrace']['frames'][0];
        $this->assertSame(1, $frame['data']['minified']['lineno']);
        $this->assertSame(3, $frame['lineno']);
    }

    public function test_node_modules_sources_are_marked_not_in_app(): void
    {
        $project = FaultProject::factory()->create(['platform' => FaultPlatform::JavaScript]);
        Storage::fake();
        $map = json_encode(['version' => 3, 'sources' => ['../node_modules/lib/index.js'], 'names' => [], 'mappings' => SourceMapDecoderTest::vlq([0, 0, 0, 0])]);
        Storage::put('maps/x.map', $map);
        SourceMap::factory()->create([
            'fault_project_id' => $project->id, 'release' => '1.0.0', 'artifact_name' => '/assets/app.js',
            'path' => 'maps/x.map', 'size' => strlen($map),
        ]);
        $event = $this->event($project);

        (new SymbolicateFaultEvent($event->id))->handle();

        $frame = $event->fresh()->exception['values'][0]['stacktrace']['frames'][0];
        $this->assertFalse($frame['in_app']);
        $this->assertStringContainsString('node_modules', $frame['filename']);
    }

    public function test_oversized_maps_are_skipped(): void
    {
        $project = FaultProject::factory()->create(['platform' => FaultPlatform::JavaScript]);
        $this->storeMap($project);
        config(['fault.max_source_map_bytes' => 10]);
        $event = $this->event($project);

        (new SymbolicateFaultEvent($event->id))->handle();

        $this->assertSame('https://example.com/assets/app.js?v=3', $event->fresh()->exception['values'][0]['stacktrace']['frames'][0]['filename']);
    }

    public function test_the_job_is_dispatched_only_for_js_projects_with_maps_for_the_release(): void
    {
        $js = FaultProject::factory()->create(['platform' => FaultPlatform::JavaScript]);
        $php = FaultProject::factory()->create(['platform' => FaultPlatform::Php]);
        $noMaps = FaultProject::factory()->create(['platform' => FaultPlatform::Vue]);
        $this->storeMap($js);
        $this->storeMap($php);

        Bus::fake();
        $this->event($php);
        $this->event($noMaps);
        $this->event($js, ['release' => '2.0.0']);
        $this->event($js, ['release' => null]);
        Bus::assertNotDispatched(SymbolicateFaultEvent::class);

        $event = $this->event($js);
        Bus::assertDispatched(SymbolicateFaultEvent::class, fn ($job) => $job->eventId === $event->id);
    }

    public function test_stacktrace_frames_expose_the_minified_location(): void
    {
        $frame = StacktraceFrame::fromPayload([
            'filename' => 'src/app.js', 'lineno' => 3,
            'data' => ['minified' => ['filename' => 'https://example.com/app.js', 'lineno' => 1, 'colno' => 21]],
        ]);

        $this->assertSame('https://example.com/app.js:1:21', $frame->minifiedLocation);
    }
}
