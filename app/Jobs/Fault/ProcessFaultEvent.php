<?php

namespace App\Jobs\Fault;

use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Support\Fault\EventFingerprinter;
use App\Support\Fault\EventPayloadCensor;
use App\Support\Fault\IssueAlertNotifier;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;

class ProcessFaultEvent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    /**
     * @param  array<string, mixed>  $payload
     */
    public function __construct(
        public int $projectId,
        public string $eventId,
        public array $payload,
    ) {
        $this->onQueue('fault-ingest');
    }

    public function handle(): void
    {
        // Idempotent: SDKs retry on transient network failures, so the same
        // event_id can arrive twice. The unique index short-circuits reprocessing.
        if (FaultEvent::where('event_id', $this->eventId)->exists()) {
            return;
        }

        $censoredHeaders = FaultProject::find($this->projectId)?->censoredHeaders() ?? FaultProject::DEFAULT_CENSORED_HEADERS;
        $payload = EventPayloadCensor::redact($this->payload, $censoredHeaders);

        ForwardFaultEvent::dispatch($this->projectId, $this->eventId, $payload);

        $exception = $payload['exception']['values'][0] ?? null;
        $fingerprint = EventFingerprinter::for($payload);
        $occurredAt = isset($payload['timestamp']) ? $this->parseTimestamp($payload['timestamp']) : now();
        $level = $payload['level'] ?? 'error';

        try {
            $issue = FaultIssue::firstOrCreate(
                ['fault_project_id' => $this->projectId, 'fingerprint' => $fingerprint],
                [
                    'type' => $exception['type'] ?? null,
                    'title' => EventFingerprinter::title($payload),
                    'culprit' => $payload['culprit'] ?? $payload['transaction'] ?? null,
                    'level' => $level,
                    'status' => 'unresolved',
                    'times_seen' => 0,
                    'first_seen_at' => $occurredAt,
                    'last_seen_at' => $occurredAt,
                    'first_seen_release' => $payload['release'] ?? null,
                ]
            );
        } catch (QueryException) {
            // Two workers raced to create the same fingerprint; the unique index
            // rejected the loser, which just needs to read what the winner inserted.
            $issue = FaultIssue::where('fault_project_id', $this->projectId)
                ->where('fingerprint', $fingerprint)
                ->firstOrFail();
        }

        $wasNew = $issue->wasRecentlyCreated;
        $isRegression = ! $wasNew && $issue->status === 'resolved';

        if (! $wasNew) {
            // Level/status/regression changes are comparatively rare, so they're
            // still written immediately. The times_seen/last_seen_at bump below
            // is batched instead: under a burst of events for the same issue,
            // an immediate increment+save on every single one would serialize
            // every worker on that one row's lock. Deferring it to periodic
            // bulk updates (see FlushFaultIssueCounters) keeps this row cheap
            // to write regardless of how hot the issue is.
            $issue->forceFill([
                'level' => $level,
                // A resolved issue reoccurring is a regression and must resurface;
                // an ignored issue is a deliberate choice, so new events don't undo it.
                'status' => $isRegression ? 'unresolved' : $issue->status,
                'regressed_at' => $isRegression ? now() : $issue->regressed_at,
            ])->save();
        }

        // $issue->times_seen still reflects the last value flushed to the database
        // (the baseline); adding the pending count since that flush gives the true,
        // up-to-the-event count, which IssueAlertNotifier needs for exact occurrence
        // thresholds (e.g. "notify on the 100th event") without waiting on the flush.
        $issue->times_seen += $this->bumpIssueCounters($issue, $occurredAt);

        FaultEvent::create([
            'fault_project_id' => $this->projectId,
            'fault_issue_id' => $issue->id,
            'event_id' => $this->eventId,
            'level' => $level,
            'message' => is_array($payload['message'] ?? null)
                ? ($payload['message']['formatted'] ?? null)
                : ($payload['message'] ?? ($exception['value'] ?? null)),
            'culprit' => $payload['culprit'] ?? null,
            'environment' => $payload['environment'] ?? null,
            'release' => $payload['release'] ?? null,
            'transaction' => $payload['transaction'] ?? null,
            'server_name' => $payload['server_name'] ?? null,
            'exception' => $payload['exception'] ?? null,
            'sdk' => $payload['sdk'] ?? null,
            'tags' => $payload['tags'] ?? null,
            'extra' => $payload['extra'] ?? null,
            'contexts' => $payload['contexts'] ?? null,
            'request' => $payload['request'] ?? null,
            'breadcrumbs' => $payload['breadcrumbs']['values'] ?? $payload['breadcrumbs'] ?? null,
            'log_context' => $payload['log_context'] ?? null,
            'payload' => $payload,
            'occurred_at' => $occurredAt,
        ]);

        app(IssueAlertNotifier::class)->notify($issue, $wasNew, $isRegression);
    }

    protected function parseTimestamp(mixed $timestamp): Carbon
    {
        if (is_numeric($timestamp)) {
            return Carbon::createFromTimestamp((float) $timestamp);
        }

        return Carbon::parse($timestamp);
    }

    /**
     * Record this occurrence in Redis instead of writing straight to
     * fault_issues, and mark the issue "dirty" so FlushFaultIssueCounters
     * picks it up. Returns the pending count accumulated since the last
     * flush, so the caller can compute the true, un-flushed times_seen.
     */
    protected function bumpIssueCounters(FaultIssue $issue, Carbon $occurredAt): int
    {
        $redis = Redis::connection();

        $pending = (int) $redis->incr("fault:issue:{$issue->id}:pending_count");
        $redis->set("fault:issue:{$issue->id}:pending_last_seen", $occurredAt->getTimestamp());
        $redis->sadd('fault:dirty-issues', $issue->id);

        return $pending;
    }
}
