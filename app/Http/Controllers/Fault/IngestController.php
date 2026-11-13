<?php

namespace App\Http\Controllers\Fault;

use App\Http\Controllers\Controller;
use App\Jobs\Fault\ProcessCronCheckIn;
use App\Jobs\Fault\ProcessFaultAttachment;
use App\Jobs\Fault\ProcessFaultEvent;
use App\Jobs\Fault\ProcessFaultFeedback;
use App\Models\FaultProject;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Storage;
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

        $maxItems = max(1, (int) config('fault.max_envelope_items'));
        $body = $this->decodedBody($request);
        $envelopeEventId = $this->envelopeEventId($body);
        $events = [];
        $others = [];
        $attachmentCounts = [];

        foreach ($this->parseEnvelope($body) as $item) {
            $type = $item['header']['type'] ?? null;

            if ($type === 'log') {
                $this->collectLogItems($events, $item['payload'], $maxItems - count($others));
            } elseif ($type === 'event' || $type === 'error') {
                $payload = json_decode($item['payload'], true);

                if (is_array($payload)) {
                    $eventId = $this->normalizeUuid($payload['event_id'] ?? (string) Str::uuid());
                    $events[] = [$eventId, $payload];
                }
            } elseif ($type === 'user_report' || $type === 'feedback') {
                $feedback = $this->parseFeedback($type, $item['payload']);

                if ($feedback !== null) {
                    $others[] = ['feedback', $feedback];
                }
            } elseif ($type === 'check_in') {
                $checkIn = json_decode($item['payload'], true);

                if (is_array($checkIn) && is_string($checkIn['monitor_slug'] ?? null) && $checkIn['monitor_slug'] !== '') {
                    $others[] = ['check_in', $checkIn];
                }
            } elseif ($type === 'attachment') {
                $attachment = $this->acceptAttachment($project['id'], $item, $envelopeEventId, $attachmentCounts);

                if ($attachment !== null) {
                    $others[] = ['attachment', $attachment];
                }
            }

            if (count($events) + count($others) >= $maxItems) {
                $events = array_slice($events, 0, max(0, $maxItems - count($others)));

                break;
            }
        }

        $this->consumeEventQuota($project['id'], count($events) + count($others));

        foreach ($events as [$eventId, $payload]) {
            ProcessFaultEvent::dispatch($project['id'], $eventId, $payload);
        }

        foreach ($others as [$kind, $data]) {
            match ($kind) {
                'feedback' => ProcessFaultFeedback::dispatch($project['id'], $data),
                'check_in' => ProcessCronCheckIn::dispatch($project['id'], $data),
                'attachment' => $this->dispatchAttachment($project['id'], $data),
            };
        }

        $lastId = $events === [] ? null : $events[array_key_last($events)][0];

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

        $this->consumeEventQuota($project['id'], 1);

        $eventId = $this->normalizeUuid($payload['event_id'] ?? (string) Str::uuid());
        ProcessFaultEvent::dispatch($project['id'], $eventId, $payload);

        return response()->json(['id' => $eventId]);
    }

    /**
     * Enforces the per-project events-per-minute budget. The request limiter
     * counts HTTP requests; one envelope can carry many events, so those are
     * accounted for separately here.
     */
    protected function consumeEventQuota(int $projectId, int $count): void
    {
        if ($count < 1) {
            return;
        }

        $key = "fault-ingest-events:{$projectId}";
        $limit = (int) config('fault.ingest_events_rate_limit');

        if (RateLimiter::tooManyAttempts($key, $limit)) {
            $this->tooManyEvents(RateLimiter::availableIn($key));
        }

        RateLimiter::increment($key, 60, $count);
    }

    protected function tooManyEvents(int $retryAfter): never
    {
        throw new HttpResponseException(response()->json(['detail' => 'Rate limit exceeded'], Response::HTTP_TOO_MANY_REQUESTS, [
            'Retry-After' => (string) $retryAfter,
            'X-Sentry-Rate-Limits' => "{$retryAfter}:error;log_item:key",
        ]));
    }

    /**
     * Sentry's structured logs protocol (https://develop.sentry.dev/sdk/telemetry/logs/):
     * a "log" envelope item payload is a JSON object with an `items` array, each entry
     * carrying `timestamp`, `trace_id`, `level`, `body`, and optional typed `attributes`.
     * Each log goes through the same event pipeline as errors so it groups into
     * an issue and is stored/displayed as a log (see FaultEvent::log_context).
     * Collection stops once `$maxItems` events are gathered.
     *
     * @param  array<int, array{0: string, 1: array<string, mixed>}>  $events
     */
    protected function collectLogItems(array &$events, string $rawPayload, int $maxItems): void
    {
        $payload = json_decode($rawPayload, true);
        $items = $payload['items'] ?? null;

        if (! is_array($items)) {
            return;
        }

        foreach ($items as $log) {
            if (count($events) >= $maxItems) {
                return;
            }

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

            $events[] = [$eventId, [
                'event_id' => $eventId,
                'timestamp' => $log['timestamp'] ?? null,
                'level' => $this->normalizeLogLevel($log['level'] ?? null),
                'message' => $log['body'],
                'log_context' => $attributes !== [] ? $attributes : null,
            ]];
        }
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
     *
     * The cache entry is keyed by project id only and forgotten by FaultProject
     * model events, so key rotation and deletion take effect immediately.
     *
     * @return array{id: int, public_key: string}
     */
    protected function authenticate(Request $request, int $projectId): array
    {
        $key = $request->query('sentry_key');

        if (! $key && $request->hasHeader('X-Sentry-Auth')) {
            if (preg_match('/sentry_key=([a-zA-Z0-9]+)/i', (string) $request->header('X-Sentry-Auth'), $m)) {
                $key = $m[1];
            }
        }

        abort_unless(is_string($key) && $key !== '', Response::HTTP_UNAUTHORIZED, 'Missing sentry_key');

        $project = Cache::remember(
            FaultProject::ingestCacheKey($projectId),
            300,
            fn () => FaultProject::whereKey($projectId)->first(['id', 'public_key'])?->only(['id', 'public_key'])
        );

        abort_unless(
            is_array($project) && hash_equals((string) $project['public_key'], $key),
            Response::HTTP_UNAUTHORIZED,
            'Invalid DSN'
        );

        return $project;
    }

    /**
     * SDKs may gzip or deflate the body (Content-Encoding header). Decode transparently,
     * rejecting oversize raw or decompressed bodies with 413.
     */
    protected function decodedBody(Request $request): string
    {
        $body = $request->getContent();

        abort_if(strlen($body) > (int) config('fault.max_body_bytes'), Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'Payload too large');

        $encoding = strtolower((string) $request->header('Content-Encoding', ''));

        $decoded = match ($encoding) {
            'gzip' => $this->inflate($body, ZLIB_ENCODING_GZIP),
            'deflate' => $this->inflate($body, ZLIB_ENCODING_DEFLATE) ?? $this->inflate($body, ZLIB_ENCODING_RAW),
            default => null,
        };

        return $decoded ?? $body;
    }

    /**
     * Streaming inflate that aborts with 413 as soon as the output exceeds the
     * configured decompressed limit. Returns null when the data is not valid.
     */
    protected function inflate(string $data, int $encoding): ?string
    {
        $max = (int) config('fault.max_decompressed_bytes');
        $context = @inflate_init($encoding);

        if ($context === false) {
            return null;
        }

        $output = '';

        foreach (str_split($data, 8192) as $chunk) {
            $part = @inflate_add($context, $chunk);

            if ($part === false) {
                return null;
            }

            $output .= $part;

            abort_if(strlen($output) > $max, Response::HTTP_REQUEST_ENTITY_TOO_LARGE, 'Payload too large');
        }

        return $output;
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

    /**
     * @param  array{content: string, path: string}&array<string, mixed>  $data
     */
    protected function dispatchAttachment(int $projectId, array $data): void
    {
        Storage::put($data['path'], $data['content']);
        unset($data['content']);

        ProcessFaultAttachment::dispatch($projectId, $data);
    }

    protected function envelopeEventId(string $raw): ?string
    {
        $header = json_decode((string) strtok($raw, "\n"), true);
        $eventId = is_array($header) ? ($header['event_id'] ?? null) : null;

        return is_string($eventId) && $eventId !== '' ? $this->normalizeUuid($eventId) : null;
    }

    /**
     * Normalize a `user_report` item (event_id, name, email, comments) or a
     * `feedback` item (contexts.feedback.{message, contact_email, name,
     * associated_event_id}) into one shape.
     *
     * @return array{event_id: ?string, name: ?string, email: ?string, comments: string}|null
     */
    protected function parseFeedback(string $type, string $rawPayload): ?array
    {
        $payload = json_decode($rawPayload, true);

        if (! is_array($payload)) {
            return null;
        }

        if ($type === 'feedback') {
            $feedback = $payload['contexts']['feedback'] ?? [];
            $source = [
                'event_id' => $feedback['associated_event_id'] ?? null,
                'name' => $feedback['name'] ?? null,
                'email' => $feedback['contact_email'] ?? null,
                'comments' => $feedback['message'] ?? null,
            ];
        } else {
            $source = [
                'event_id' => $payload['event_id'] ?? null,
                'name' => $payload['name'] ?? null,
                'email' => $payload['email'] ?? null,
                'comments' => $payload['comments'] ?? null,
            ];
        }

        $text = static fn (mixed $value, int $max): ?string => is_string($value) && trim($value) !== ''
            ? mb_substr(trim($value), 0, $max)
            : null;

        $comments = $text($source['comments'], 10000);

        if ($comments === null) {
            return null;
        }

        return [
            'event_id' => is_string($source['event_id']) && $source['event_id'] !== '' ? $this->normalizeUuid(substr($source['event_id'], 0, 36)) : null,
            'name' => $text($source['name'], 255),
            'email' => $text($source['email'], 255),
            'comments' => $comments,
        ];
    }

    /**
     * Validates an attachment item and returns its content plus the metadata
     * for ProcessFaultAttachment (written to disk only once the event quota
     * passed), or null when it is dropped (no event to
     * attach to, over the size limit, or over the per-event count).
     *
     * @param  array{header: array<string, mixed>, payload: string}  $item
     * @param  array<string, int>  $counts
     * @return array{content: string, event_id: string, filename: string, content_type: ?string, attachment_type: ?string, size: int, path: string}|null
     */
    protected function acceptAttachment(int $projectId, array $item, ?string $eventId, array &$counts): ?array
    {
        $size = strlen($item['payload']);

        if ($eventId === null || $size === 0 || $size > (int) config('fault.max_attachment_bytes')) {
            return null;
        }

        if (($counts[$eventId] ?? 0) >= (int) config('fault.max_attachments_per_event')) {
            return null;
        }

        $counts[$eventId] = ($counts[$eventId] ?? 0) + 1;

        $filename = preg_replace('/[\x00-\x1f\x7f\/\\\\]+/', '_', (string) ($item['header']['filename'] ?? 'attachment'));
        $filename = mb_substr(trim((string) $filename) ?: 'attachment', 0, 200);
        $contentType = $item['header']['content_type'] ?? null;
        $attachmentType = $item['header']['attachment_type'] ?? null;

        return [
            'content' => $item['payload'],
            'event_id' => $eventId,
            'filename' => $filename,
            'content_type' => is_string($contentType) ? mb_substr($contentType, 0, 255) : null,
            'attachment_type' => is_string($attachmentType) ? mb_substr($attachmentType, 0, 255) : null,
            'size' => $size,
            'path' => 'fault-attachments/'.$projectId.'/'.$eventId.'/'.Str::uuid(),
        ];
    }

    protected function normalizeUuid(string $eventId): string
    {
        if (preg_match('/^[0-9a-f]{32}$/i', $eventId) === 1) {
            return implode('-', [
                substr($eventId, 0, 8),
                substr($eventId, 8, 4),
                substr($eventId, 12, 4),
                substr($eventId, 16, 4),
                substr($eventId, 20, 12),
            ]);
        }

        if (preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i', $eventId) !== 1) {
            return (string) Str::uuid();
        }

        return $eventId;
    }
}
