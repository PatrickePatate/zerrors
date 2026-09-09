<?php

namespace App\Console\Commands;

use App\Models\FaultEvent;
use App\Models\FaultProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('zerrors:prune-events')]
#[Description('Delete events older than each project\'s retention_days. Issues themselves are kept.')]
class PruneFaultEvents extends Command
{
    public function handle(): int
    {
        $totalDeleted = 0;

        FaultProject::query()->where('retention_days', '>', 0)->each(function (FaultProject $project) use (&$totalDeleted): void {
            $cutoff = now()->subDays($project->retention_days);

            $deleted = FaultEvent::where('fault_project_id', $project->id)
                ->where('occurred_at', '<', $cutoff)
                ->delete();

            if ($deleted > 0) {
                $this->line("Pruned {$deleted} event(s) from \"{$project->name}\" older than {$project->retention_days} days.");
            }

            $totalDeleted += $deleted;
        });

        $this->info("Done. {$totalDeleted} event(s) pruned.");

        return self::SUCCESS;
    }
}
