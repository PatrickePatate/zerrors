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

    /*
    |--------------------------------------------------------------------------
    | Allow private targets
    |--------------------------------------------------------------------------
    |
    | By default monitors may only target public addresses, so a member cannot
    | use the server to reach loopback, LAN, link-local (cloud metadata) or
    | other internal services. Self-hosters monitoring internal services on
    | their own network can enable this. It disables the address checks only;
    | URLs must still be http or https.
    |
    */
    'allow_private_targets' => env('MONITORING_ALLOW_PRIVATE_TARGETS', false),
];
