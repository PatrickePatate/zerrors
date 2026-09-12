<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Monitor check retention
    |--------------------------------------------------------------------------
    |
    | Number of days of monitor_checks history to keep. Older rows are pruned
    | daily by the monitoring:prune-checks command.
    |
    */
    'retention_days' => env('MONITORING_RETENTION_DAYS', 30),
];
