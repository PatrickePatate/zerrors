<?php

namespace App\Console\Commands;

use App\Models\MonitorCheck;
use App\Models\MonitorDailyStat;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:prune-checks')]
#[Description('Delete monitor_checks/monitor_daily_stats older than config(monitoring.retention_days).')]
class PruneMonitorChecks extends Command
{
    public function handle(): int
    {
        $retentionDays = (int) config('monitoring.retention_days', 365);

        $cutoff = now()->subDays($retentionDays);

        // Raw checks past retention shouldn't normally exist (compact-checks
        // rolls them up first), but this guards against the compaction
        // command not having run yet for very old data.
        $deletedChecks = MonitorCheck::where('checked_at', '<', $cutoff)->delete();
        $deletedStats = MonitorDailyStat::where('date', '<', $cutoff)->delete();

        $this->info("Pruned {$deletedChecks} monitor check(s) and {$deletedStats} daily stat(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
