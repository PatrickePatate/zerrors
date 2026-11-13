<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\ProcessFaultEvent;
use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class IngestHardeningTest extends TestCase
{
    use RefreshDatabase;

    private function send(FaultProject $project, string $body, array $headers = [], string $path = 'envelope', ?string $key = null)
    {
        $server = ['HTTP_X-Sentry-Auth' => 'Sentry sentry_key='.($key ?? $project->public_key)];

        foreach ($headers as $name => $value) {
            $server['HTTP_'.$name] = $value;
        }

        return $this->call('POST', "/api/{$project->id}/{$path}/", server: $server, content: $body);
    }

    private function logEnvelope(int $count): string
    {
        $items = array_map(fn (int $i) => ['body' => "log {$i}", 'level' => 'info', 'timestamp' => time()], range(1, $count));

        return json_encode(['event_id' => 'x'])."\n".json_encode(['type' => 'log'])."\n".json_encode(['items' => $items])."\n";
    }

    public function test_oversize_raw_body_is_rejected_with_413(): void
    {
        config(['fault.max_body_bytes' => 100]);
        $project = FaultProject::factory()->create();

        $this->send($project, str_repeat('a', 101))->assertStatus(413);
    }

    public function test_gzip_bomb_is_rejected_with_413(): void
    {
        config(['fault.max_decompressed_bytes' => 1000]);
        $project = FaultProject::factory()->create();

        $this->send($project, gzencode(str_repeat('a', 1_000_000)), ['Content-Encoding' => 'gzip'])->assertStatus(413);
        $this->send($project, gzcompress(str_repeat('a', 1_000_000)), ['Content-Encoding' => 'deflate'])->assertStatus(413);
    }

    public function test_gzip_and_deflate_bodies_within_limits_are_accepted(): void
    {
        Bus::fake([ProcessFaultEvent::class]);
        $project = FaultProject::factory()->create();
        $body = $this->logEnvelope(1);

        $this->send($project, gzencode($body), ['Content-Encoding' => 'gzip'])->assertOk();
        $this->send($project, gzcompress($body), ['Content-Encoding' => 'deflate'])->assertOk();
        $this->send($project, gzdeflate($body), ['Content-Encoding' => 'deflate'])->assertOk();

        Bus::assertDispatchedTimes(ProcessFaultEvent::class, 3);
    }

    public function test_rotated_key_is_rejected_immediately(): void
    {
        Bus::fake([ProcessFaultEvent::class]);
        $project = FaultProject::factory()->create();
        $oldKey = $project->public_key;

        $this->send($project, $this->logEnvelope(1))->assertOk();

        $project->update(['public_key' => 'rotatedkey123']);

        $this->send($project, $this->logEnvelope(1), key: $oldKey)->assertStatus(401);
        $this->send($project, $this->logEnvelope(1), key: 'rotatedkey123')->assertOk();
    }

    public function test_deleted_project_is_rejected_immediately(): void
    {
        Bus::fake([ProcessFaultEvent::class]);
        $project = FaultProject::factory()->create();

        $this->send($project, $this->logEnvelope(1))->assertOk();

        $project->delete();

        $this->send($project, $this->logEnvelope(1))->assertStatus(401);
    }

    public function test_envelope_items_beyond_the_cap_are_dropped(): void
    {
        Bus::fake([ProcessFaultEvent::class]);
        config(['fault.max_envelope_items' => 5]);
        $project = FaultProject::factory()->create();

        $this->send($project, $this->logEnvelope(20))->assertOk();

        Bus::assertDispatchedTimes(ProcessFaultEvent::class, 5);
    }

    public function test_event_rate_limit_returns_429_with_retry_headers(): void
    {
        Bus::fake([ProcessFaultEvent::class]);
        config(['fault.ingest_events_rate_limit' => 5]);
        $project = FaultProject::factory()->create();
        RateLimiter::clear("fault-ingest-events:{$project->id}");

        $this->send($project, $this->logEnvelope(5))->assertOk();

        $this->send($project, $this->logEnvelope(1))
            ->assertStatus(429)
            ->assertHeader('Retry-After')
            ->assertHeader('X-Sentry-Rate-Limits');

        Bus::assertDispatchedTimes(ProcessFaultEvent::class, 5);
    }

    public function test_cors_headers_are_present_for_browser_sdks(): void
    {
        Bus::fake([ProcessFaultEvent::class]);
        $project = FaultProject::factory()->create();

        $this->call('POST', "/api/{$project->id}/envelope/?sentry_key={$project->public_key}", server: [
            'HTTP_ORIGIN' => 'https://app.example.com',
            'CONTENT_TYPE' => 'text/plain;charset=UTF-8',
        ], content: $this->logEnvelope(1))
            ->assertOk()
            ->assertHeader('Access-Control-Allow-Origin', '*');

        $this->call('OPTIONS', "/api/{$project->id}/envelope/", server: [
            'HTTP_ORIGIN' => 'https://app.example.com',
            'HTTP_ACCESS_CONTROL_REQUEST_METHOD' => 'POST',
            'HTTP_ACCESS_CONTROL_REQUEST_HEADERS' => 'x-sentry-auth,content-type,content-encoding',
        ])
            ->assertSuccessful()
            ->assertHeader('Access-Control-Allow-Origin', '*');
    }

    public function test_a_non_uuid_event_id_never_reaches_the_attachment_path(): void
    {
        Storage::fake();
        $project = FaultProject::factory()->create();
        $evil = '../../../../etc/passwd/////////';
        $body = json_encode(['event_id' => $evil])."\n"
            .json_encode(['type' => 'attachment', 'filename' => 'a.txt', 'length' => 3])."\nabc\n";

        $this->send($project, $body)->assertOk();

        $files = Storage::allFiles();
        $this->assertCount(1, $files);
        $this->assertStringNotContainsString('..', $files[0]);
    }
}
