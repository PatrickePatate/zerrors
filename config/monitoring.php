<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitor check retention
    |--------------------------------------------------------------------------
    |
    | Raw monitor_checks older than compact_after_days are rolled up into a
    | single monitor_daily_stats row (one average per monitor per day) by
    | the monitoring:compact-checks command, then deleted. retention_days
    | controls how long those daily stats are kept before the
    | monitoring:prune-checks command removes them for good.
    |
    */
    'compact_after_days' => env('MONITORING_COMPACT_AFTER_DAYS', 7),

    'retention_days' => env('MONITORING_RETENTION_DAYS', 365),
];
