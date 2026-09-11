<?php

namespace Tests\Feature\Fault;

use App\Jobs\Fault\ForwardFaultEvent;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class IngestTest extends TestCase
{
    use RefreshDatabase;

    public function test_envelope_endpoint_stores_an_event_and_groups_it_into_an_issue(): void
    {
        Bus::fake([ForwardFaultEvent::class]);

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

        Bus::assertDispatched(
            ForwardFaultEvent::class,
            fn ($job) => $job->projectId === $project->id && str_replace('-', '', $job->eventId) === $eventId
        );
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

    public function test_store_endpoint_captures_log_context_for_a_log_style_payload(): void
    {
        $project = FaultProject::factory()->create();

        $payload = [
            'message' => 'No query results for model [App\Models\Store].',
            'log_context' => ['piece' => 'F6-0004144', 'depot' => 'ETB COURSOL'],
        ];

        $response = $this->call(
            'POST',
            "/api/{$project->id}/store/?sentry_key={$project->public_key}",
            content: json_encode($payload),
        );

        $response->assertOk();

        $event = FaultEvent::first();
        $this->assertSame(['piece' => 'F6-0004144', 'depot' => 'ETB COURSOL'], $event->log_context);
    }

    public function test_envelope_endpoint_ingests_a_sentry_structured_log_item(): void
    {
        $project = FaultProject::factory()->create();

        $envelopeHeader = json_encode(['sdk' => ['name' => 'sentry.javascript.browser', 'version' => '9.15.0']]);
        $itemHeader = json_encode([
            'type' => 'log',
            'item_count' => 1,
            'content_type' => 'application/vnd.sentry.items.log+json',
        ]);
        $itemPayload = json_encode([
            'items' => [[
                'timestamp' => 1746456149.0191,
                'trace_id' => '5b8efff798038103d269b633813fc60c',
                'level' => 'info',
                'body' => 'User John has logged in!',
                'severity_number' => 9,
                'attributes' => [
                    'sentry.message.template' => ['value' => 'User %s has logged in!', 'type' => 'string'],
                    'sentry.message.parameter.0' => ['value' => 'John', 'type' => 'string'],
                ],
            ]],
        ]);

        $body = "{$envelopeHeader}\n{$itemHeader}\n{$itemPayload}\n";

        $response = $this->call(
            'POST',
            "/api/{$project->id}/envelope/?sentry_key={$project->public_key}",
            content: $body,
        );

        $response->assertOk();
        $response->assertJsonStructure(['id']);

        $this->assertDatabaseCount('fault_events', 1);
        $this->assertDatabaseCount('fault_issues', 1);

        $event = FaultEvent::first();
        $this->assertSame('info', $event->level);
        $this->assertSame('User John has logged in!', $event->message);
        $this->assertSame([
            'sentry.message.template' => 'User %s has logged in!',
            'sentry.message.parameter.0' => 'John',
        ], $event->log_context);

        $issue = FaultIssue::first();
        $this->assertSame('User John has logged in!', $issue->title);
    }

    public function test_envelope_endpoint_normalizes_the_warn_log_level_to_warning(): void
    {
        $project = FaultProject::factory()->create();

        $itemHeader = json_encode(['type' => 'log', 'item_count' => 1]);
        $itemPayload = json_encode([
            'items' => [[
                'timestamp' => 1746456149.0,
                'level' => 'warn',
                'body' => 'Disk usage above threshold',
            ]],
        ]);

        $response = $this->call(
            'POST',
            "/api/{$project->id}/envelope/?sentry_key={$project->public_key}",
            content: "{}\n{$itemHeader}\n{$itemPayload}\n",
        );

        $response->assertOk();
        $this->assertSame('warning', FaultEvent::first()->level);
    }

    public function test_envelope_endpoint_ingests_multiple_log_items_from_the_same_batch(): void
    {
        $project = FaultProject::factory()->create();

        $itemHeader = json_encode(['type' => 'log', 'item_count' => 2]);
        $itemPayload = json_encode([
            'items' => [
                ['timestamp' => 1746456149.0, 'level' => 'debug', 'body' => 'Cache warmed'],
                ['timestamp' => 1746456150.0, 'level' => 'error', 'body' => 'Cache warmed'],
            ],
        ]);

        $response = $this->call(
            'POST',
            "/api/{$project->id}/envelope/?sentry_key={$project->public_key}",
            content: "{}\n{$itemHeader}\n{$itemPayload}\n",
        );

        $response->assertOk();
        $this->assertDatabaseCount('fault_events', 2);
    }
}
