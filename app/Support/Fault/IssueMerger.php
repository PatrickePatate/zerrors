<?php

namespace App\Support\Fault;

use App\Models\FaultEvent;
use App\Models\FaultFeedback;
use App\Models\FaultIssue;
use App\Models\FaultIssueActivity;
use App\Models\FaultIssueFingerprint;
use App\Models\FaultIssueShareLink;
use App\Models\User;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

class IssueMerger
{
    public const MAX_ISSUES = 100;

    /**
     * Merge the source issues into the target (all in the same project).
     * Events are re-pointed in chunks, counters summed, fingerprints kept as
     * redirects, and the sources deleted. Pending Redis counters and the
     * affected-users HyperLogLog are transferred after the commit.
     *
     * @param  Collection<int, FaultIssue>  $sources
     */
    public function merge(FaultIssue $target, Collection $sources, ?User $user = null): void
    {
        $sources = $sources->reject(fn (FaultIssue $source) => $source->id === $target->id)
            ->where('fault_project_id', $target->fault_project_id)->values();

        if ($sources->isEmpty()) {
            return;
        }

        $sourceIds = $sources->pluck('id')->all();

        DB::transaction(function () use ($target, $sourceIds, $user): void {
            $locked = FaultIssue::whereIn('id', [$target->id, ...$sourceIds])->lockForUpdate()->get()->keyBy('id');
            $target = $locked[$target->id];
            $sources = $locked->except($target->id);

            foreach ($sourceIds as $sourceId) {
                $this->moveEvents($sourceId, $target->id);
            }

            FaultFeedback::whereIn('fault_issue_id', $sourceIds)->update(['fault_issue_id' => $target->id]);
            FaultIssueShareLink::whereIn('fault_issue_id', $sourceIds)->update(['fault_issue_id' => $target->id]);
            FaultIssueActivity::whereIn('fault_issue_id', $sourceIds)->update(['fault_issue_id' => $target->id]);
            FaultIssueFingerprint::whereIn('fault_issue_id', $sourceIds)->update(['fault_issue_id' => $target->id]);

            foreach ($sources as $source) {
                FaultIssueFingerprint::firstOrCreate(
                    ['fault_project_id' => $target->fault_project_id, 'fingerprint' => $source->fingerprint],
                    ['fault_issue_id' => $target->id],
                );
            }

            $all = $locked->values();
            $earliest = $all->whereNotNull('first_seen_at')->sortBy('first_seen_at')->first();

            $target->forceFill([
                'times_seen' => $all->sum('times_seen'),
                'users_affected' => $all->max('users_affected'),
                'first_seen_at' => $earliest?->first_seen_at ?? $target->first_seen_at,
                'first_seen_release' => $earliest?->first_seen_release ?? $target->first_seen_release,
                'last_seen_at' => $all->whereNotNull('last_seen_at')->max('last_seen_at') ?? $target->last_seen_at,
            ])->saveQuietly();

            FaultIssueActivity::record($target, 'merged', [
                'merged' => $sources->map(fn (FaultIssue $source) => ['id' => $source->id, 'title' => $source->title])->values()->all(),
            ], $user);

            FaultIssue::whereIn('id', $sourceIds)->delete();
        });

        $this->transferRedisState($target->id, $sourceIds);
    }

    protected function moveEvents(int $sourceId, int $targetId): void
    {
        do {
            $ids = FaultEvent::where('fault_issue_id', $sourceId)->limit(1000)->pluck('id');

            if ($ids->isNotEmpty()) {
                FaultEvent::whereIn('id', $ids)->update(['fault_issue_id' => $targetId]);
            }
        } while ($ids->isNotEmpty());
    }

    /**
     * @param  list<int>  $sourceIds
     */
    protected function transferRedisState(int $targetId, array $sourceIds): void
    {
        $redis = Redis::connection();
        $pendingTotal = 0;
        $lastSeen = null;
        $userKeys = [];

        foreach ($sourceIds as $sourceId) {
            $pendingTotal += (int) $redis->getset("fault:issue:{$sourceId}:pending_count", 0);
            $seen = $redis->get("fault:issue:{$sourceId}:pending_last_seen");
            $lastSeen = $seen !== null && $seen !== false ? max((int) $lastSeen, (int) $seen) : $lastSeen;
            $userKeys[] = "fault:issue:{$sourceId}:users";
        }

        if ($pendingTotal > 0) {
            $redis->incrby("fault:issue:{$targetId}:pending_count", $pendingTotal);
        }

        if ($lastSeen) {
            $redis->eval(
                "local c = tonumber(redis.call('GET', KEYS[1])); if c == nil or tonumber(ARGV[1]) > c then redis.call('SET', KEYS[1], ARGV[1]) end",
                1,
                "fault:issue:{$targetId}:pending_last_seen",
                $lastSeen,
            );
        }

        $redis->pfmerge("fault:issue:{$targetId}:users", $userKeys);
        $redis->sadd('fault:dirty-issues', $targetId);

        foreach ($sourceIds as $sourceId) {
            $redis->del(
                "fault:issue:{$sourceId}:pending_count",
                "fault:issue:{$sourceId}:pending_last_seen",
                "fault:issue:{$sourceId}:users",
            );
            $redis->srem('fault:dirty-issues', $sourceId);
        }
    }
}
