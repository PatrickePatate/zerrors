<?php

namespace App\Models;

use App\Enums\NotificationRuleTrigger;
use Database\Factories\NotificationRuleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class NotificationRule extends Model
{
    /** @use HasFactory<NotificationRuleFactory> */
    use HasFactory;

    protected $fillable = [
        'notification_channel_id', 'trigger', 'thresholds', 'enabled',
    ];

    protected $casts = [
        'trigger' => NotificationRuleTrigger::class,
        'thresholds' => 'array',
        'enabled' => 'boolean',
    ];

    public function channel(): BelongsTo
    {
        return $this->belongsTo(NotificationChannel::class);
    }

    /**
     * Spike settings (stored in the `thresholds` JSON column as a keyed array),
     * merged over the defaults.
     *
     * @return array{window_minutes: int, multiplier: int, min_count: int}
     */
    public function spikeSettings(): array
    {
        $stored = $this->trigger === NotificationRuleTrigger::Spike && is_array($this->thresholds) ? $this->thresholds : [];

        return [
            'window_minutes' => max(1, (int) ($stored['window_minutes'] ?? 5)),
            'multiplier' => max(1, (int) ($stored['multiplier'] ?? 10)),
            'min_count' => max(1, (int) ($stored['min_count'] ?? 20)),
        ];
    }

    /**
     * Whether this rule applies to the issue's current occurrence count.
     * Only meaningful for the occurrence_threshold trigger; every other
     * trigger applies unconditionally once matched by type.
     */
    public function matchesOccurrence(FaultIssue $issue): bool
    {
        if ($this->trigger !== NotificationRuleTrigger::OccurrenceThreshold) {
            return true;
        }

        return in_array($issue->times_seen, array_values($this->thresholds ?? []), true);
    }
}
