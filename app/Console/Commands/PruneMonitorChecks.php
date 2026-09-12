<?php

namespace App\Console\Commands;

use App\Models\MonitorCheck;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('monitoring:prune-checks')]
#[Description('Delete monitor_checks older than config(monitoring.retention_days).')]
class PruneMonitorChecks extends Command
{
    public function handle(): int
    {
        $retentionDays = (int) config('monitoring.retention_days', 30);

        $cutoff = now()->subDays($retentionDays);

        $deleted = MonitorCheck::where('checked_at', '<', $cutoff)->delete();

        $this->info("Pruned {$deleted} monitor check(s) older than {$retentionDays} days.");

        return self::SUCCESS;
    }
}
