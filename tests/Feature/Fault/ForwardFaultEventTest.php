<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\ForwardFaultEvent;
use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

class ForwardFaultEventTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_posts_the_event_to_the_configured_dsn_envelope_endpoint(): void
    {
        Http::fake(['*' => Http::response('', 200)]);

        $project = FaultProject::factory()->create([
            'forward_enabled' => true,
            'forward_dsn' => 'https://abc123@o0.ingest.sentry.io/456',
        ]);

        (new ForwardFaultEvent($project->id, 'a0000000000000000000000000000000', ['message' => 'boom']))->handle();

        Http::assertSent(function ($request) {
            return $request->url() === 'https://o0.ingest.sentry.io/api/456/envelope/'
                && str_contains($request->header('X-Sentry-Auth')[0] ?? '', 'sentry_key=abc123')
                && str_contains($request->body(), '"message":"boom"');
        });
    }

    public function test_it_does_nothing_when_forwarding_is_disabled(): void
    {
        Http::fake();

        $project = FaultProject::factory()->create([
            'forward_enabled' => false,
            'forward_dsn' => 'https://abc123@o0.ingest.sentry.io/456',
        ]);

        (new ForwardFaultEvent($project->id, 'a0000000000000000000000000000000', ['message' => 'boom']))->handle();

        Http::assertNothingSent();
    }

    public function test_it_does_nothing_without_a_configured_dsn(): void
    {
        Http::fake();

        $project = FaultProject::factory()->create(['forward_enabled' => true, 'forward_dsn' => null]);

        (new ForwardFaultEvent($project->id, 'a0000000000000000000000000000000', ['message' => 'boom']))->handle();

        Http::assertNothingSent();
    }
}
