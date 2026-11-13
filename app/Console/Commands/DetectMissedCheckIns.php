<?php

namespace App\Console\Commands;

use App\Support\Fault\CronMonitorTracker;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:detect-missed-check-ins')]
#[Description('Mark overdue cron monitors as missed, time out stuck in-progress check-ins and prune old check-ins.')]
class DetectMissedCheckIns extends Command
{
    public function handle(CronMonitorTracker $tracker): int
    {
        $result = $tracker->detect();
        $pruned = $tracker->pruneCheckIns();

        $this->info("Missed: {$result['missed']}, timed out: {$result['timed_out']}, pruned: {$pruned}.");

        return self::SUCCESS;
    }
}
