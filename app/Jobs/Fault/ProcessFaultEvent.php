<?php

namespace App\Jobs\Fault;

use App\Models\FaultAttachment;
use App\Models\FaultEvent;
use App\Models\FaultFeedback;
use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\FaultIssueFingerprint;
use App\Models\FaultProject;
use App\Models\Release;
use App\Support\Fault\EventFingerprinter;
use App\Support\Fault\EventPayloadCensor;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Redis;

class ProcessFaultEvent implements ShouldQueue
{
    use InteractsWithQueue, Queueable, SerializesModels;

    public int $tries = 3;

    public int $backoff = 5;

    private const USERS_KEY_TTL = 60 * 60 * 24 * 30;

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

        $project = FaultProject::find($this->projectId);

        // The project was deleted while the event sat in the queue.
        if (! $project) {
            return;
        }

        $payload = EventPayloadCensor::redact($this->payload, $project->censoredHeaders());

        $values = $payload['exception']['values'] ?? null;
        $exception = is_array($values) && $values !== [] && is_array(end($values)) ? end($values) : null;
        $handled = isset($exception['mechanism']['handled']) ? (bool) $exception['mechanism']['handled'] : null;
        $fingerprint = EventFingerprinter::for($payload);
        $occurredAt = isset($payload['timestamp']) ? $this->parseTimestamp($payload['timestamp']) : now();
        $level = $payload['level'] ?? 'error';

        $issue = $this->findExistingIssue($fingerprint);

        try {
            $issue ??= FaultIssue::firstOrCreate(
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
                    'latest_event_handled' => $handled,
                ]
            );
        } catch (QueryException) {
            // Two workers raced to create the same fingerprint; the unique index
            // rejected the loser, which just needs to read what the winner inserted.
            $issue = FaultIssue::where('fault_project_id', $this->projectId)
                ->where('fingerprint', $fingerprint)
                ->firstOrFail();
        }

        // The event row goes in first: its unique event_id index is the
        // authority on duplicates (a retry, or two workers on the same event),
        // so counters, forwarding and alerts only ever run once per event.
        try {
            $event = FaultEvent::create([
                'fault_project_id' => $this->projectId,
                'fault_issue_id' => $issue->id,
                'event_id' => $this->eventId,
                'level' => $level,
                'message' => $this->truncateMessage(is_array($payload['message'] ?? null)
                    ? ($payload['message']['formatted'] ?? null)
                    : ($payload['message'] ?? ($exception['value'] ?? null))),
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
                'payload' => $this->remainingPayload($payload),
                'occurred_at' => $occurredAt,
            ]);
        } catch (QueryException $e) {
            if ($this->isDuplicateEvent($e)) {
                return;
            }

            throw $e;
        }

        if ($project->isForwardingConfigured()) {
            ForwardFaultEvent::dispatch($this->projectId, $this->eventId, $payload);
        }

        $eventRelease = $payload['release'] ?? null;
        $isRegression = false;
        $regressionActivity = null;

        // $issue->times_seen still reflects the last value flushed to the database
        // (the baseline); adding the pending count since that flush gives the true,
        // up-to-the-event count, which IssueAlertNotifier needs for exact occurrence
        // thresholds (e.g. "notify on the 100th event") without waiting on the flush.
        $pending = $this->bumpIssueCounters($issue, $occurredAt);
        $liveCount = (int) $issue->times_seen + $pending;

        // A retry after a failed attempt finds the issue already created, but
        // its first counted event is still the new-issue one.
        $wasNew = $issue->wasRecentlyCreated || $liveCount === 1;

        $attributes = ['level' => $level, 'latest_event_handled' => $handled];

        if (! $wasNew) {
            if ($issue->status === 'resolved' && $this->shouldRegress($issue, $eventRelease)) {
                $isRegression = true;
                $regressionActivity = ['type' => 'regression', 'data' => ['release' => $eventRelease]];
                $attributes += $this->clearedResolution() + ['status' => 'unresolved', 'regressed_at' => now()];
            } elseif ($issue->status === 'ignored' && $this->snoozeEnded($issue, $liveCount)) {
                $isRegression = true;
                $regressionActivity = ['type' => 'resurfaced', 'data' => ['reason' => $issue->snooze_until_count !== null && $liveCount >= $issue->snooze_until_count ? 'count_reached' : 'snooze_expired']];
                $attributes += ['status' => 'unresolved', 'snoozed_until' => null, 'snooze_until_count' => null];
            }

            // Level/status/regression changes are comparatively rare, so they're
            // still written immediately. The times_seen/last_seen_at bump is
            // batched instead (see FlushFaultIssueCounters). Saved quietly so a
            // regression never triggers a synchronous GitHub call on the ingest
            // worker; SyncGithubIssueRegression handles that.
            $issue->forceFill($attributes)->saveQuietly();
        }

        $issue->times_seen = $liveCount;

        if ($regressionActivity !== null) {
            FaultIssueActivity::record($issue, $regressionActivity['type'], $regressionActivity['data']);

            if ($regressionActivity['type'] === 'regression' && $issue->github_issue_number) {
                SyncGithubIssueRegression::dispatch($issue->id);
            }
        }

        $this->recordAffectedUser($issue, $event);
        $this->linkPendingRecords($event);

        if ($wasNew || $isRegression || $this->hasAlertChannels($project)) {
            SendIssueAlert::dispatch($issue->id, $wasNew, $isRegression, (int) $issue->times_seen);
        }
    }

    protected function isDuplicateEvent(QueryException $e): bool
    {
        // Integrity-constraint violations also cover foreign keys (e.g. a project
        // deleted mid-flight), so confirm the row really exists before swallowing.
        return in_array((string) $e->getCode(), ['23000', '23505'], true)
            && FaultEvent::where('event_id', $this->eventId)->exists();
    }

    /**
     * Find the issue for a fingerprint, following merge redirects. The alias
     * table is only consulted when no issue matches directly, so the common
     * path (an existing issue) costs no extra query; only brand-new
     * fingerprints pay one indexed unique-key lookup.
     */
    protected function findExistingIssue(string $fingerprint): ?FaultIssue
    {
        $issue = FaultIssue::where('fault_project_id', $this->projectId)->where('fingerprint', $fingerprint)->first();

        if ($issue) {
            return $issue;
        }

        $targetId = FaultIssueFingerprint::where('fault_project_id', $this->projectId)
            ->where('fingerprint', $fingerprint)
            ->value('fault_issue_id');

        return $targetId ? FaultIssue::find($targetId) : null;
    }

    /**
     * @return array<string, mixed>
     */
    protected function clearedResolution(): array
    {
        return [
            'resolved_in_release' => null,
            'resolved_in_next_release' => false,
            'resolved_last_seen_release' => null,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
        ];
    }

    protected function snoozeEnded(FaultIssue $issue, int $liveCount): bool
    {
        return ($issue->snoozed_until !== null && $issue->snoozed_until->lte(now()))
            || ($issue->snooze_until_count !== null && $liveCount >= $issue->snooze_until_count);
    }

    /**
     * Whether an event reopens a resolved issue. Plain resolutions, events
     * without release info, or unknown releases always regress. Release-aware
     * resolutions ignore events from the release the issue was last seen in,
     * regress on the fix release itself or anything deployed at/after it, and
     * for "next release" only on releases deployed after the resolution.
     */
    protected function shouldRegress(FaultIssue $issue, ?string $eventRelease): bool
    {
        if ($issue->resolved_in_release === null && ! $issue->resolved_in_next_release) {
            return true;
        }

        if ($eventRelease === null) {
            return true;
        }

        if ($eventRelease === $issue->resolved_last_seen_release) {
            return false;
        }

        if ($issue->resolved_in_release !== null && $eventRelease === $issue->resolved_in_release) {
            return true;
        }

        $eventDeployedAt = Release::where('fault_project_id', $this->projectId)->where('version', $eventRelease)->value('deployed_at');

        if ($eventDeployedAt === null) {
            return true;
        }

        $eventDeployedAt = Carbon::parse($eventDeployedAt);

        if ($issue->resolved_in_release !== null) {
            $fixDeployedAt = Release::where('fault_project_id', $this->projectId)->where('version', $issue->resolved_in_release)->value('deployed_at');

            return $fixDeployedAt === null || $eventDeployedAt->gte(Carbon::parse($fixDeployedAt));
        }

        return $issue->resolved_at === null || $eventDeployedAt->gt($issue->resolved_at);
    }

    /**
     * Feed the issue's HyperLogLog of distinct users; the periodic flush
     * writes its cardinality to fault_issues.users_affected.
     */
    protected function recordAffectedUser(FaultIssue $issue, FaultEvent $event): void
    {
        if ($event->user_identifier === null) {
            return;
        }

        // Sliding TTL: keys of issues deleted by DB-level cascades (project or
        // organization deletion) would otherwise linger forever.
        Redis::connection()->pipeline(function ($pipe) use ($issue, $event): void {
            $pipe->pfadd("fault:issue:{$issue->id}:users", [$event->user_identifier]);
            $pipe->expire("fault:issue:{$issue->id}:users", self::USERS_KEY_TTL);
            $pipe->sadd('fault:dirty-issues', $issue->id);
        });
    }

    /**
     * Feedback and attachments may reach the server before their event;
     * attach them now that it exists.
     */
    protected function linkPendingRecords(FaultEvent $event): void
    {
        FaultFeedback::where('fault_project_id', $event->fault_project_id)
            ->where('event_id', $event->event_id)
            ->whereNull('fault_event_id')
            ->update(['fault_event_id' => $event->id, 'fault_issue_id' => $event->fault_issue_id]);

        FaultAttachment::where('fault_project_id', $event->fault_project_id)
            ->where('event_id', $event->event_id)
            ->whereNull('fault_event_id')
            ->update(['fault_event_id' => $event->id]);
    }

    /**
     * Whether the project has any enabled alert channel. New issues and
     * regressions always dispatch (they also email organization members);
     * for every other event this decides if the alert job is worth queueing.
     * Cached briefly per project so the hot ingest path does not query it for
     * every event; the cost is that a freshly added channel can take up to
     * the TTL to start receiving occurrence alerts.
     */
    protected function hasAlertChannels(FaultProject $project): bool
    {
        return Cache::remember(
            "fault:project:{$project->id}:has-alert-channels",
            30,
            fn () => $project->notificationChannels()->where('enabled', true)->exists(),
        );
    }

    /**
     * The payload keys that have no dedicated column on fault_events. The rest
     * (exception, sdk, tags, extra, contexts, request, breadcrumbs, log_context)
     * is already persisted in its own column; FaultEvent::payload rebuilds the
     * full payload from both.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>
     */
    protected function remainingPayload(array $payload): array
    {
        return array_diff_key($payload, array_flip(FaultEvent::PAYLOAD_COLUMN_KEYS));
    }

    /**
     * The message column is TEXT (64KB); clip oversized messages such as
     * fatal errors embedding a full stack trace.
     */
    protected function truncateMessage(mixed $message): ?string
    {
        if (! is_string($message)) {
            return null;
        }

        return mb_strcut($message, 0, 65000);
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
        // Only ever move forward: a late event carrying an old timestamp must not rewind it.
        $redis->eval(
            "local c = tonumber(redis.call('GET', KEYS[1])); if c == nil or tonumber(ARGV[1]) > c then redis.call('SET', KEYS[1], ARGV[1]) end",
            1,
            "fault:issue:{$issue->id}:pending_last_seen",
            $occurredAt->getTimestamp(),
        );
        $redis->sadd('fault:dirty-issues', $issue->id);

        return $pending;
    }
}
