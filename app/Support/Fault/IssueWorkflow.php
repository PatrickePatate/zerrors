<?php

namespace App\Support\Fault;

use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\Organization;
use App\Models\Release;
use App\Models\User;
use App\Support\Organization\AuditLogger;

class IssueWorkflow
{
    public const IGNORE_OPTIONS = [
        'forever' => 'Forever',
        '1h' => 'For 1 hour',
        '24h' => 'For 24 hours',
        '7d' => 'For 7 days',
        'x10' => 'Until 10x more events',
        'x100' => 'Until 100 more events',
    ];

    public const RESOLVE_MODES = ['now', 'next', 'current'];

    protected bool $quiet = false;

    protected ?string $source = null;

    public function __construct(protected AuditLogger $audit) {}

    /**
     * A copy of this workflow for changes that originate outside the app
     * (e.g. a GitHub webhook): status changes are saved without firing model
     * events, so the GitHub observer never calls back to GitHub, and the
     * audit entry records where the change came from.
     */
    public function quietly(string $source): static
    {
        $clone = clone $this;
        $clone->quiet = true;
        $clone->source = $source;

        return $clone;
    }

    /**
     * @param  string  $mode  one of RESOLVE_MODES
     */
    public function resolve(Organization $organization, FaultIssue $issue, ?User $user, string $mode = 'now'): void
    {
        abort_unless(in_array($mode, self::RESOLVE_MODES, true), 422);

        $release = null;
        if ($mode === 'current') {
            $release = Release::where('fault_project_id', $issue->fault_project_id)
                ->orderByDesc('deployed_at')->orderByDesc('id')->value('version');
        }

        $details = ['resolved_in_release' => $release, 'in_next_release' => $mode === 'next'];

        $this->apply($organization, $issue, $user, 'resolved', [
            'resolved_in_release' => $release,
            'resolved_in_next_release' => $mode === 'next',
            'resolved_last_seen_release' => $issue->events()->orderByDesc('occurred_at')->value('release'),
            'resolved_at' => now(),
            'resolved_by_user_id' => $user?->id,
        ], $details);
    }

    public function unresolve(Organization $organization, FaultIssue $issue, ?User $user): void
    {
        $this->apply($organization, $issue, $user, 'unresolved');
    }

    public function ignore(Organization $organization, FaultIssue $issue, ?User $user, string $until = 'forever'): void
    {
        abort_unless(array_key_exists($until, self::IGNORE_OPTIONS), 422);

        $snoozedUntil = match ($until) {
            '1h' => now()->addHour(),
            '24h' => now()->addDay(),
            '7d' => now()->addDays(7),
            default => null,
        };
        $count = match ($until) {
            'x10' => max($issue->liveTimesSeen(), 1) * 10,
            'x100' => $issue->liveTimesSeen() + 100,
            default => null,
        };

        $this->apply($organization, $issue, $user, 'ignored', [
            'snoozed_until' => $snoozedUntil,
            'snooze_until_count' => $count,
        ], ['until' => $until, 'snoozed_until' => $snoozedUntil?->toIso8601String(), 'until_count' => $count]);
    }

    public function assign(Organization $organization, FaultIssue $issue, ?User $user, ?int $assigneeId): void
    {
        $previous = $issue->assigned_to_user_id;
        $issue->update(['assigned_to_user_id' => $assigneeId]);

        FaultIssueActivity::record($issue, 'assignment', ['from' => $previous, 'to' => $assigneeId], $user);
        $this->audit->log($organization, $user, 'issue.assigned', $issue->title, [
            'assigned_to_user_id' => $assigneeId,
            ...($this->source !== null ? ['source' => $this->source] : []),
        ]);
    }

    /**
     * @param  array<string, mixed>  $extra
     * @param  array<string, mixed>  $details
     */
    protected function apply(Organization $organization, FaultIssue $issue, ?User $user, string $status, array $extra = [], array $details = []): void
    {
        $previous = $issue->status;

        $attributes = [
            'status' => $status,
            'snoozed_until' => null,
            'snooze_until_count' => null,
            'resolved_in_release' => null,
            'resolved_in_next_release' => false,
            'resolved_last_seen_release' => null,
            'resolved_at' => null,
            'resolved_by_user_id' => null,
            ...$extra,
        ];

        if ($this->quiet) {
            $issue->forceFill($attributes)->saveQuietly();
        } else {
            $issue->update($attributes);
        }

        FaultIssueActivity::record($issue, 'status', ['from' => $previous, 'to' => $status, ...$details], $user);
        $this->audit->log($organization, $user, 'issue.status_updated', $issue->title, [
            'from' => $previous,
            'to' => $status,
            ...($this->source !== null ? ['source' => $this->source] : []),
        ]);
    }
}
