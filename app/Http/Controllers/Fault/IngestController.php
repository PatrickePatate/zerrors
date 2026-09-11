<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Jobs\Fault\ProcessFaultEvent;
use App\Models\FaultProject;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\Response;

class IngestController extends Controller
{
    /**
     * Modern endpoint used by current sentry-php / sentry-laravel SDKs.
     * POST /api/{projectId}/envelope/
     *
     * Kept intentionally cheap: no DB writes on the request path. Each event
     * item is handed to a queue worker (see ProcessFaultEvent) so ingestion
     * throughput isn't bound by grouping/insert latency.
     */
    public function envelope(Request $request, int $projectId)
    {
        $project = $this->authenticate($request, $projectId);

        $lastId = null;

        foreach ($this->parseEnvelope($this->decodedBody($request)) as $item) {
            $type = $item['header']['type'] ?? null;

            if ($type === 'log') {
                $lastId = $this->dispatchLogItems($project->id, $item['payload']) ?? $lastId;

                continue;
            }

            if ($type !== 'event' && $type !== 'error') {
                continue;
            }

            $payload = json_decode($item['payload'], true);

            if (! is_array($payload)) {
                continue;
            }

            $eventId = $this->normalizeUuid($payload['event_id'] ?? (string) Str::uuid());
            ProcessFaultEvent::dispatch($project->id, $eventId, $payload);
            $lastId = $eventId;
        }

        return response()->json(['id' => $lastId ?? str_replace('-', '', (string) Str::uuid())]);
    }

    /**
     * Legacy endpoint, kept for older SDK versions or config that still targets /store/.
     * POST /api/{projectId}/store/
     */
    public function store(Request $request, int $projectId)
    {
        $project = $this->authenticate($request, $projectId);

        $payload = json_decode($this->decodedBody($request), true);

        abort_unless(is_array($payload), Response::HTTP_BAD_REQUEST, 'Invalid payload');

        $eventId = $this->normalizeUuid($payload['event_id'] ?? (string) Str::uuid());
        ProcessFaultEvent::dispatch($project->id, $eventId, $payload);

        return response()->json(['id' => $eventId]);
    }

    /**
     * Sentry's structured logs protocol (https://develop.sentry.dev/sdk/telemetry/logs/):
     * a "log" envelope item payload is a JSON object with an `items` array, each entry
     * carrying `timestamp`, `trace_id`, `level`, `body`, and optional typed `attributes`.
     * Each log is dispatched through the same event pipeline as errors so it groups into
     * an issue and is stored/displayed as a log (see FaultEvent::log_context).
     *
     * @return string|null the last dispatched event id, or null if the payload had no logs
     */
    protected function dispatchLogItems(int $projectId, string $rawPayload): ?string
    {
        $payload = json_decode($rawPayload, true);
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            return null;
        }

        $lastId = null;

        foreach ($items as $log) {
            if (! is_array($log) || ! isset($log['body'])) {
                continue;
            }

            $eventId = str_replace('-', '', (string) Str::uuid());

            $attributes = [];
            foreach (($log['attributes'] ?? []) as $key => $attribute) {
                $attributes[$key] = is_array($attribute) && array_key_exists('value', $attribute)
                    ? $attribute['value']
                    : $attribute;
            }

            ProcessFaultEvent::dispatch($projectId, $eventId, [
                'event_id' => $eventId,
                'timestamp' => $log['timestamp'] ?? null,
                'level' => $this->normalizeLogLevel($log['level'] ?? null),
                'message' => $log['body'],
                'log_context' => $attributes !== [] ? $attributes : null,
            ]);

            $lastId = $eventId;
        }

        return $lastId;
    }

    /**
     * Sentry's log protocol uses `warn`, while event-derived issues in this app use
     * `warning` (matching the level Sentry SDKs report on error events). Normalize so
     * both event and log issues share the same badge color mapping.
     */
    protected function normalizeLogLevel(?string $level): string
    {
        return $level === 'warn' ? 'warning' : ($level ?? 'info');
    }

    /**
     * Validates the public key against the project, read from the X-Sentry-Auth
     * header (sent by the SDK per the DSN) or the sentry_key query param.
     */
    protected function authenticate(Request $request, int $projectId): FaultProject
    {
        $key = $request->query('sentry_key');

        if (! $key && $request->hasHeader('X-Sentry-Auth')) {
            if (preg_match('/sentry_key=([a-zA-Z0-9]+)/i', (string) $request->header('X-Sentry-Auth'), $m)) {
                $key = $m[1];
            }
        }

        abort_unless($key, Response::HTTP_UNAUTHORIZED, 'Missing sentry_key');

        $project = Cache::remember(
            "fault:project:{$projectId}:{$key}",
            300,
            fn () => FaultProject::where('id', $projectId)->where('public_key', $key)->first()
        );

        abort_unless($project, Response::HTTP_UNAUTHORIZED, 'Invalid DSN');

        return $project;
    }

    /**
     * SDKs may gzip or deflate the body (Content-Encoding header). Decode transparently.
     */
    protected function decodedBody(Request $request): string
    {
        $body = $request->getContent();
        $encoding = strtolower((string) $request->header('Content-Encoding', ''));

        if ($encoding === 'gzip') {
            $decoded = @gzdecode($body);

            return $decoded !== false ? $decoded : $body;
        }

        if ($encoding === 'deflate') {
            $decoded = @gzuncompress($body);

            return $decoded !== false ? $decoded : $body;
        }

        return $body;
    }

    /**
     * Parse a Sentry envelope (newline-delimited JSON: header, then item header/payload
     * pairs). Item headers may carry an explicit byte `length` for binary payloads
     * (attachments); when absent the payload runs to the next newline.
     *
     * @return array<int, array{header: array<string, mixed>, payload: string}>
     */
    protected function parseEnvelope(string $raw): array
    {
        $length = strlen($raw);
        $offset = strpos($raw, "\n");

        if ($offset === false) {
            return [];
        }

        $offset++;
        $items = [];

        while ($offset < $length) {
            $headerEnd = strpos($raw, "\n", $offset);
            if ($headerEnd === false) {
                break;
            }

            $itemHeader = json_decode(substr($raw, $offset, $headerEnd - $offset), true) ?? [];
            $offset = $headerEnd + 1;

            if (isset($itemHeader['length'])) {
                $payload = substr($raw, $offset, (int) $itemHeader['length']);
                $offset += (int) $itemHeader['length'];
                if (($raw[$offset] ?? null) === "\n") {
                    $offset++;
                }
            } else {
                $payloadEnd = strpos($raw, "\n", $offset);
                $payload = $payloadEnd === false ? substr($raw, $offset) : substr($raw, $offset, $payloadEnd - $offset);
                $offset = $payloadEnd === false ? $length : $payloadEnd + 1;
            }

            $items[] = ['header' => $itemHeader, 'payload' => $payload];
        }

        return $items;
    }

    protected function normalizeUuid(string $eventId): string
    {
        if (strlen($eventId) === 32 && ! str_contains($eventId, '-')) {
            return implode('-', [
                substr($eventId, 0, 8),
                substr($eventId, 8, 4),
                substr($eventId, 12, 4),
                substr($eventId, 16, 4),
                substr($eventId, 20, 12),
            ]);
        }

        return $eventId;
    }
}
