<?php

namespace App\Enums;

enum NotificationRuleTrigger: string
{
    case NewIssue = 'new_issue';
    case Regression = 'regression';
    case EveryEvent = 'every_event';
    case OccurrenceThreshold = 'occurrence_threshold';
    case Spike = 'spike';

    public function label(): string
    {
        return match ($this) {
            self::NewIssue => 'New issue',
            self::Regression => 'Regression (resolved issue reoccurs)',
            self::EveryEvent => 'Every event',
            self::OccurrenceThreshold => 'Occurrence thresholds',
            self::Spike => 'Spike (sudden surge in events)',
        };
    }
}
