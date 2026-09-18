<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

// Required for Horizon's `auto` balancing: without recorded runtime metrics,
// its autoscaler can't estimate time-to-clear a queue, falls back to jumping
// straight from minProcesses to maxProcesses on any non-empty poll, and
// churns workers every `balanceCooldown` tick — force-killing "hanging"
// ones on scale-down and letting their jobs be redelivered and reprocessed.
Schedule::command('horizon:snapshot')->everyFiveMinutes();

Schedule::command('fault:flush-issue-counters')->everyMinute();
Schedule::command('zerrors:prune-events')->daily();
Schedule::command('monitoring:dispatch-checks')->everyMinute()->withoutOverlapping();
Schedule::command('monitoring:compact-checks')->daily();
Schedule::command('monitoring:prune-checks')->dailyAt('01:00');
