<?php

namespace Tests\Feature\Fault;

use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

abstract class IngestExtrasTestCase extends TestCase
{
    use RefreshDatabase;

    /**
     * @param  list<array{0: array<string, mixed>, 1: string}>  $items
     */
    protected function sendEnvelope(FaultProject $project, array $items, ?string $eventId = null): TestResponse
    {
        $body = json_encode(['event_id' => $eventId ?? str_repeat('a', 32)])."\n";

        foreach ($items as [$header, $payload]) {
            $body .= json_encode($header)."\n".$payload."\n";
        }

        return $this->call('POST', "/api/{$project->id}/envelope/",
            server: ['HTTP_X-Sentry-Auth' => "Sentry sentry_version=7, sentry_key={$project->public_key}"],
            content: $body);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    protected function errorPayload(string $eventId, array $overrides = []): array
    {
        return array_replace([
            'event_id' => $eventId,
            'level' => 'error',
            'timestamp' => time(),
            'exception' => ['values' => [['type' => 'RuntimeException', 'value' => 'boom']]],
        ], $overrides);
    }
}
