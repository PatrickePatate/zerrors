<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_endpoint_stores_an_event_and_groups_it_into_an_issue(): void
    {
        $project = FaultProject::factory()->create();

        $eventId = 'a'.str_repeat('0', 31);
        $envelopeHeader = json_encode(['event_id' => $eventId]);
        $itemHeader = json_encode(['type' => 'event']);
        $itemPayload = json_encode([
            'event_id' => $eventId,
            'level' => 'error',
            'timestamp' => time(),
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => 'Something broke',
                    'stacktrace' => ['frames' => [
                        ['filename' => 'app/Foo.php', 'function' => 'bar'],
                    ]],
                ]],
            ],
        ]);

        $body = "{$envelopeHeader}\n{$itemHeader}\n{$itemPayload}\n";

        $response = $this->call(
            'POST',
            "/api/{$project->id}/envelope/",
            server: ['HTTP_X-Sentry-Auth' => "Sentry sentry_version=7, sentry_key={$project->public_key}"],
            content: $body,
        );

        $response->assertOk();
        $response->assertJsonStructure(['id']);

        $this->assertDatabaseCount('fault_events', 1);
        $this->assertDatabaseCount('fault_issues', 1);

        $event = FaultEvent::first();
        $this->assertSame('Something broke', $event->message);
        $this->assertSame($project->id, $event->fault_project_id);

        $issue = FaultIssue::first();
        $this->assertSame('RuntimeException', $issue->title);
        $this->assertSame(1, $issue->times_seen);
    }

    public function test_envelope_endpoint_rejects_an_invalid_dsn_key(): void
    {
        $project = FaultProject::factory()->create();

        $response = $this->call(
            'POST',
            "/api/{$project->id}/envelope/?sentry_key=wrong-key",
            content: "{}\n",
        );

        $response->assertUnauthorized();
        $this->assertDatabaseCount('fault_events', 0);
    }

    public function test_repeated_events_with_the_same_fingerprint_increment_the_same_issue(): void
    {
        $project = FaultProject::factory()->create();

        for ($i = 0; $i < 3; $i++) {
            $payload = json_encode([
                'event_id' => str_repeat((string) $i, 32),
                'level' => 'error',
                'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
            ]);

            $this->call(
                'POST',
                "/api/{$project->id}/envelope/?sentry_key={$project->public_key}",
                content: "{}\n".json_encode(['type' => 'event'])."\n{$payload}\n",
            );
        }

        $this->assertDatabaseCount('fault_issues', 1);
        $this->assertDatabaseCount('fault_events', 3);
        $this->assertSame(3, FaultIssue::first()->times_seen);
    }
}
