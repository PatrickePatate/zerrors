<?php

namespace App\Console\Commands;

use App\Models\FaultIssue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;

#[Signature('fault:flush-issue-counters')]
#[Description('Apply the times_seen/last_seen_at increments buffered in Redis by ProcessFaultEvent, one bulk update per dirty issue.')]
class FlushFaultIssueCounters extends Command
{
    public function handle(): int
    {
        $redis = Redis::connection();

        $issueIds = $redis->smembers('fault:dirty-issues');

        if (empty($issueIds)) {
            return self::SUCCESS;
        }

        // Claim these ids now; any event that arrives for one of them while this
        // command runs re-adds it to the set and accumulates a fresh pending
        // count, which the next run will pick up. No increment is ever lost.
        $redis->srem('fault:dirty-issues', ...$issueIds);

        $flushed = 0;

        foreach ($issueIds as $issueId) {
            // GETSET atomically reads the pending count and resets it to 0, so a
            // concurrent INCR from a worker either lands before this read (and is
            // included) or after (and starts a fresh count for the next flush).
            $pending = (int) $redis->getset("fault:issue:{$issueId}:pending_count", 0);

            if ($pending <= 0) {
                continue;
            }

            $lastSeenTimestamp = $redis->get("fault:issue:{$issueId}:pending_last_seen");

            FaultIssue::where('id', $issueId)->update(array_filter([
                'times_seen' => DB::raw("times_seen + {$pending}"),
                'last_seen_at' => $lastSeenTimestamp ? Carbon::createFromTimestamp((int) $lastSeenTimestamp) : null,
            ]));

            $flushed++;
        }

        $this->info("Flushed counters for {$flushed} issue(s).");

        return self::SUCCESS;
    }
}
