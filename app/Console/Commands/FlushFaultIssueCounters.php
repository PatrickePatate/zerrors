<?php

namespace App\Console\Commands;

use App\Models\FaultIssue;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Redis;
use Throwable;

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

        $flushed = 0;

        foreach ($issueIds as $issueId) {
            // GETSET atomically reads the pending count and resets it to 0, so a
            // concurrent INCR from a worker either lands before this read (and is
            // included) or after (and starts a fresh count for the next flush).
            $pending = (int) $redis->getset("fault:issue:{$issueId}:pending_count", 0);
            $lastSeenTimestamp = $redis->get("fault:issue:{$issueId}:pending_last_seen");

            if ($pending > 0) {
                try {
                    $this->applyToIssue((int) $issueId, $pending, $lastSeenTimestamp ? (int) $lastSeenTimestamp : null);
                } catch (Throwable $e) {
                    // Hand the claimed count back; the id was never removed from the
                    // dirty set, so the next run retries instead of losing it.
                    $redis->incrby("fault:issue:{$issueId}:pending_count", $pending);
                    report($e);

                    continue;
                }

                $flushed++;
            }

            $this->syncUsersAffected($redis, (int) $issueId);
            $this->releaseIfIdle($redis, (int) $issueId);
        }

        $this->info("Flushed counters for {$flushed} issue(s).");

        return self::SUCCESS;
    }

    /**
     * Add the pending count and move last_seen_at forward only: a late event
     * carrying an old timestamp must never rewind it.
     */
    private function applyToIssue(int $issueId, int $pending, ?int $lastSeenTimestamp): void
    {
        $values = ['times_seen' => DB::raw('times_seen + '.$pending)];

        if ($lastSeenTimestamp !== null) {
            $lastSeen = Carbon::createFromTimestamp($lastSeenTimestamp)->toDateTimeString();
            $values['last_seen_at'] = DB::raw('CASE WHEN last_seen_at IS NULL OR last_seen_at < '.DB::getPdo()->quote($lastSeen)
                .' THEN '.DB::getPdo()->quote($lastSeen).' ELSE last_seen_at END');
        }

        FaultIssue::where('id', $issueId)->update($values);
    }

    /**
     * Write the HyperLogLog cardinality of the issue's distinct users to
     * users_affected. Never lowers the stored value.
     */
    private function syncUsersAffected(mixed $redis, int $issueId): void
    {
        $count = (int) $redis->pfcount("fault:issue:{$issueId}:users");

        if ($count > 0) {
            FaultIssue::where('id', $issueId)->where('users_affected', '<', $count)->update(['users_affected' => $count]);
        }
    }

    /**
     * Drop the issue's Redis keys and dirty flag once its pending count is
     * zero. Done in one Lua script so it is atomic against a worker's INCR:
     * a worker that incremented before the script leaves a non-zero count and
     * the keys are kept (still dirty); one that increments after recreates
     * the key and re-adds the id to the dirty set itself.
     */
    private function releaseIfIdle(mixed $redis, int $issueId): void
    {
        $redis->eval(
            "local c = tonumber(redis.call('GET', KEYS[1])); if c == nil or c <= 0 then redis.call('DEL', KEYS[1], KEYS[2]); redis.call('SREM', KEYS[3], ARGV[1]) end",
            3,
            "fault:issue:{$issueId}:pending_count",
            "fault:issue:{$issueId}:pending_last_seen",
            'fault:dirty-issues',
            $issueId,
        );
    }
}
