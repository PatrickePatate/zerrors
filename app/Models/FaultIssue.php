<?php

namespace App\Models;

use App\Observers\FaultIssueGithubObserver;
use App\Observers\FaultIssueTriageObserver;
use Database\Factories\FaultIssueFactory;
use Illuminate\Database\Eloquent\Attributes\ObservedBy;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Redis;

#[ObservedBy([FaultIssueGithubObserver::class, FaultIssueTriageObserver::class])]
class FaultIssue extends Model
{
    /** @use HasFactory<FaultIssueFactory> */
    use HasFactory;

    protected $fillable = [
        'fault_project_id', 'fingerprint', 'type', 'title', 'culprit',
        'level', 'status', 'times_seen', 'first_seen_at', 'last_seen_at',
        'ai_analysis', 'ai_analyzed_at', 'ai_deep_analysis', 'ai_deep_analyzed_at',
        'assigned_to_user_id', 'regressed_at',
        'first_seen_release', 'github_issue_url', 'github_issue_number',
        'latest_event_handled', 'users_affected',
        'snoozed_until', 'snooze_until_count', 'resolved_in_release', 'resolved_in_next_release',
        'resolved_last_seen_release', 'resolved_at', 'resolved_by_user_id',
        'triage_category', 'triage_priority', 'triage_model', 'triaged_at', 'triage_error',
    ];

    protected $casts = [
        'first_seen_at' => 'datetime',
        'last_seen_at' => 'datetime',
        'ai_analyzed_at' => 'datetime',
        'ai_deep_analyzed_at' => 'datetime',
        'regressed_at' => 'datetime',
        'latest_event_handled' => 'boolean',
        'snoozed_until' => 'datetime',
        'resolved_at' => 'datetime',
        'resolved_in_next_release' => 'boolean',
        'triaged_at' => 'datetime',
    ];

    public function project(): BelongsTo
    {
        return $this->belongsTo(FaultProject::class, 'fault_project_id');
    }

    public function events(): HasMany
    {
        return $this->hasMany(FaultEvent::class);
    }

    /**
     * The most recent event, for surfacing per-event details (Livewire/queue/bot
     * detection) on the issue without loading the full event history. Safe to
     * eager-load with a column-limited constraint to avoid an N+1 on issue lists.
     */
    public function latestEvent(): HasOne
    {
        return $this->hasOne(FaultEvent::class)->latestOfMany('occurred_at');
    }

    public function assignee(): BelongsTo
    {
        return $this->belongsTo(User::class, 'assigned_to_user_id');
    }

    public function feedback(): HasMany
    {
        return $this->hasMany(FaultFeedback::class);
    }

    public function activities(): HasMany
    {
        return $this->hasMany(FaultIssueActivity::class);
    }

    /**
     * times_seen plus the count still buffered in Redis awaiting the periodic flush.
     */
    public function liveTimesSeen(): int
    {
        return (int) $this->times_seen + (int) Redis::connection()->get("fault:issue:{$this->id}:pending_count");
    }

    public function shareLinks(): HasMany
    {
        return $this->hasMany(FaultIssueShareLink::class);
    }

    /**
     * Whether this issue was derived from a log entry rather than a captured
     * exception (see IngestController::dispatchLogItems() and ProcessFaultEvent,
     * which leave `type` null for log-only issues).
     */
    public function isLog(): bool
    {
        return $this->type === null;
    }

    /**
     * The release this issue was first seen in, if it matches a release recorded
     * for the project — used to surface the GitHub commit that likely introduced it.
     */
    public function linkedRelease(): ?Release
    {
        if (! $this->first_seen_release) {
            return null;
        }

        return Release::where('fault_project_id', $this->fault_project_id)
            ->where('version', $this->first_seen_release)
            ->first();
    }
}
