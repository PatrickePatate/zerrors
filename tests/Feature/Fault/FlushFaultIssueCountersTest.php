<?php

namespace Tests\Feature\Fault;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Redis;
use Tests\TestCase;

class FlushFaultIssueCountersTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_applies_pending_redis_counts_to_the_database(): void
    {
        $project = FaultProject::factory()->create();
        $issue = FaultIssue::factory()->for($project, 'project')->create([
            'times_seen' => 5,
            'last_seen_at' => now()->subHour(),
        ]);

        $redis = Redis::connection();
        $redis->incrby("fault:issue:{$issue->id}:pending_count", 3);
        $lastSeen = now();
        $redis->set("fault:issue:{$issue->id}:pending_last_seen", $lastSeen->getTimestamp());
        $redis->sadd('fault:dirty-issues', $issue->id);

        $this->artisan('fault:flush-issue-counters')->assertSuccessful();

        $issue->refresh();
        $this->assertSame(8, $issue->times_seen);
        $this->assertSame($lastSeen->getTimestamp(), $issue->last_seen_at->getTimestamp());

        // The pending counter is reset so the same increments aren't reapplied.
        $this->assertSame(0, (int) $redis->get("fault:issue:{$issue->id}:pending_count"));
        $this->assertSame([], $redis->smembers('fault:dirty-issues'));
    }

    public function test_it_does_nothing_when_no_issues_are_dirty(): void
    {
        $this->artisan('fault:flush-issue-counters')->assertSuccessful();

        $this->assertTrue(true);
    }

    public function test_a_dirty_issue_with_no_pending_count_is_left_untouched(): void
    {
        $project = FaultProject::factory()->create();
        $issue = FaultIssue::factory()->for($project, 'project')->create(['times_seen' => 5]);

        Redis::connection()->sadd('fault:dirty-issues', $issue->id);

        $this->artisan('fault:flush-issue-counters')->assertSuccessful();

        $this->assertSame(5, $issue->fresh()->times_seen);
    }
}
