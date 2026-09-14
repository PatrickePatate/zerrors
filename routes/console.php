<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('fault:flush-issue-counters')->everyMinute();
Schedule::command('zerrors:prune-events')->daily();
Schedule::command('monitoring:dispatch-checks')->everyMinute();
Schedule::command('monitoring:compact-checks')->daily();
Schedule::command('monitoring:prune-checks')->dailyAt('01:00');
