<?php

namespace App\Console\Commands;

use App\Models\FaultAttachment;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Redis;
use Illuminate\Support\Facades\Storage;

#[Signature('zerrors:prune-events')]
#[Description('Delete events older than each project\'s retention_days, then delete issues left with no events and not seen since the cutoff.')]
class PruneFaultEvents extends Command
{
    private const CHUNK_SIZE = 5000;

    public function handle(): int
    {
        $totalDeleted = 0;
        $totalIssues = 0;

        FaultProject::query()->where('retention_days', '>', 0)->each(function (FaultProject $project) use (&$totalDeleted, &$totalIssues): void {
            $cutoff = now()->subDays($project->retention_days);

            $deleted = $this->pruneEvents($project, $cutoff);
            $issues = $this->pruneEmptyIssues($project, $cutoff);
            $this->pruneSourceMaps($project, $cutoff);

            if ($deleted > 0 || $issues > 0) {
                $this->line("Pruned {$deleted} event(s) and {$issues} empty issue(s) from \"{$project->name}\" older than {$project->retention_days} days.");
            }

            $totalDeleted += $deleted;
            $totalIssues += $issues;
        });

        $this->info("Done. {$totalDeleted} event(s) and {$totalIssues} issue(s) pruned.");

        return self::SUCCESS;
    }

    private function pruneEvents(FaultProject $project, Carbon $cutoff): int
    {
        $total = 0;

        do {
            $ids = FaultEvent::query()
                ->where('fault_project_id', $project->id)
                ->where('occurred_at', '<', $cutoff)
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');
            $this->deleteAttachments(FaultAttachment::whereIn('fault_event_id', $ids));
            $deleted = $ids->isEmpty() ? 0 : FaultEvent::whereIn('id', $ids)->delete();
            $total += $deleted;
        } while ($deleted > 0);

        // Attachments whose event never arrived expire with the same retention.
        $this->deleteAttachments(FaultAttachment::where('fault_project_id', $project->id)
            ->whereNull('fault_event_id')->where('created_at', '<', $cutoff));

        return $total;
    }

    /**
     * Delete the files of the matching attachments, then their rows.
     *
     * @param  Builder<FaultAttachment>  $query
     */
    private function deleteAttachments(Builder $query): void
    {
        do {
            $attachments = (clone $query)->limit(1000)->get(['id', 'path']);

            if ($attachments->isEmpty()) {
                return;
            }

            Storage::delete($attachments->pluck('path')->all());
            FaultAttachment::whereIn('id', $attachments->pluck('id'))->delete();
        } while (true);
    }

    /**
     * Delete the source maps (and their files) of releases uploaded before the
     * cutoff that no event references any more.
     */
    private function pruneSourceMaps(FaultProject $project, Carbon $cutoff): void
    {
        $project->sourceMaps()
            ->where('created_at', '<', $cutoff)
            ->distinct()
            ->pluck('release')
            ->each(function (string $release) use ($project, $cutoff): void {
                if (FaultEvent::where('fault_project_id', $project->id)->where('release', $release)->exists()) {
                    return;
                }

                $project->sourceMaps()->where('release', $release)->where('created_at', '<', $cutoff)->get()->each->delete();
            });
    }

    private function pruneEmptyIssues(FaultProject $project, Carbon $cutoff): int
    {
        $total = 0;

        do {
            $ids = FaultIssue::query()
                ->where('fault_project_id', $project->id)
                ->where('last_seen_at', '<', $cutoff)
                ->whereDoesntHave('events')
                ->limit(self::CHUNK_SIZE)
                ->pluck('id');
            $deleted = $ids->isEmpty() ? 0 : FaultIssue::whereIn('id', $ids)->delete();
            $this->forgetRedisState($ids->all());
            $total += $deleted;
        } while ($deleted > 0);

        return $total;
    }

    /**
     * Query-builder deletes fire no model events, so drop the deleted issues'
     * Redis counters and affected-users HyperLogLogs here.
     *
     * @param  list<int>  $issueIds
     */
    private function forgetRedisState(array $issueIds): void
    {
        if ($issueIds === []) {
            return;
        }

        $redis = Redis::connection();
        $keys = [];

        foreach ($issueIds as $id) {
            array_push($keys, "fault:issue:{$id}:users", "fault:issue:{$id}:pending_count", "fault:issue:{$id}:pending_last_seen");
        }

        foreach (array_chunk($keys, 900) as $chunk) {
            $redis->del(...$chunk);
        }

        $redis->srem('fault:dirty-issues', ...$issueIds);
    }
}
