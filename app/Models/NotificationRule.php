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
     * Whether this rule applies to the issue's current occurrence count.
     * Only meaningful for the occurrence_threshold trigger; every other
     * trigger applies unconditionally once matched by type.
     */
    public function matchesOccurrence(FaultIssue $issue): bool
    {
        if ($this->trigger !== NotificationRuleTrigger::OccurrenceThreshold) {
            return true;
        }

        return in_array($issue->times_seen, $this->thresholds ?? [], true);
    }
}
