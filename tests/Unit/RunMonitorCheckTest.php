<?php

namespace Tests\Unit;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use PHPUnit\Framework\TestCase;

class RunMonitorCheckTest extends TestCase
{
    public function test_unique_id_is_the_monitor_id(): void
    {
        $monitor = new Monitor;
        $monitor->id = 42;

        $job = new RunMonitorCheck($monitor);

        $this->assertSame('42', $job->uniqueId());
    }

    /**
     * The unique lock must outlive the check's own worst case (a timed-out
     * HTTP monitor retries twice and, if certificate checking is on, opens a
     * second TLS connection afterwards — each leg up to timeout_seconds).
     * Otherwise the lock can expire while a slow check is still running, a
     * second scheduler tick dispatches an overlapping job for the same
     * monitor, and both independently detect the same status change —
     * exactly the duplicate-notification race this job exists to prevent.
     */
    public function test_unique_for_covers_the_slowest_possible_check(): void
    {
        $monitor = new Monitor;
        $monitor->id = 1;
        $monitor->timeout_seconds = 120;

        $job = new RunMonitorCheck($monitor);

        $worstCaseCheckDuration = (3 * 120) + (2 * 0.5) + 120;

        $this->assertGreaterThan($worstCaseCheckDuration, $job->uniqueFor());
    }

    public function test_unique_for_scales_with_the_monitor_timeout(): void
    {
        $slow = new Monitor;
        $slow->id = 1;
        $slow->timeout_seconds = 120;

        $fast = new Monitor;
        $fast->id = 2;
        $fast->timeout_seconds = 10;

        $this->assertGreaterThan(
            (new RunMonitorCheck($fast))->uniqueFor(),
            (new RunMonitorCheck($slow))->uniqueFor(),
        );
    }

    /**
     * Under Horizon's `auto` balancing, a job still running past the
     * supervisor's own timeout (config/horizon.php sets 60s) is treated as
     * "hanging" and force-killed on scale down, which abandons it mid-run
     * and lets it be redelivered and reprocessed — sending a duplicate
     * notification for the same status change. The job must declare its own
     * timeout so Horizon never applies that shorter default to it.
     */
    public function test_timeout_exceeds_the_horizon_supervisor_default(): void
    {
        $monitor = new Monitor;
        $monitor->id = 1;
        $monitor->timeout_seconds = 120;

        $job = new RunMonitorCheck($monitor);

        $horizonSupervisorDefaultTimeout = 60;

        $this->assertGreaterThan($horizonSupervisorDefaultTimeout, $job->timeout);
    }

    public function test_timeout_matches_unique_for(): void
    {
        $monitor = new Monitor;
        $monitor->id = 1;
        $monitor->timeout_seconds = 45;

        $job = new RunMonitorCheck($monitor);

        $this->assertSame($job->uniqueFor(), $job->timeout);
    }
}
