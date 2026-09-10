<?php

namespace App\Jobs\Fault;

use App\Models\FaultProject;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Http;
use Sentry\Dsn;
use Throwable;

/**
 * Re-posts an ingested event to a second, user-supplied Sentry DSN, so a
 * project can be monitored by zerrors and a real Sentry instance at once.
 * Runs on its own queue so a slow or unreachable forwarding target never
 * backs up ingestion processing.
 */
class ForwardFaultEvent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 10;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $projectId,
        public string $eventId,
        public array $payload,
    ) {
        $this->onQueue('fault-forward');
    }

    public function handle(): void
    {
        $project = FaultProject::find($this->projectId);

        if (! $project || ! $project->isForwardingConfigured()) {
            return;
        }

        try {
            $dsn = Dsn::createFromString($project->forward_dsn);
        } catch (Throwable $e) {
            report($e);

            return;
        }

        $envelope = $this->buildEnvelope($dsn->getPublicKey());

        $authHeader = implode(', ', [
            'Sentry sentry_version=7',
            'sentry_client=zerrors-forwarder/1.0',
            "sentry_key={$dsn->getPublicKey()}",
        ]);

        try {
            Http::timeout(5)
                ->withHeaders(['X-Sentry-Auth' => $authHeader])
                ->withBody($envelope, 'application/x-sentry-envelope')
                ->post($dsn->getEnvelopeApiEndpointUrl());
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function buildEnvelope(string $publicKey): string
    {
        $envelopeHeader = json_encode([
            'event_id' => str_replace('-', '', $this->eventId),
            'sent_at' => now()->toIso8601ZuluString(),
            'sdk' => ['name' => 'zerrors-forwarder', 'version' => '1.0'],
            'trace' => ['public_key' => $publicKey],
        ]);

        $itemPayload = json_encode($this->payload);
        $itemHeader = json_encode(['type' => 'event', 'length' => strlen((string) $itemPayload)]);

        // The auth header isn't part of the envelope body itself, but Sentry's
        // relay also accepts it as a query param; carrying it in the URL keeps
        // this a plain POST without custom header plumbing through Http::.
        return "{$envelopeHeader}\n{$itemHeader}\n{$itemPayload}\n";
    }
}
