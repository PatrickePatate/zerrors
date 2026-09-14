<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDailyStat;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class CompactMonitorChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_rolls_up_old_checks_into_a_daily_average_and_deletes_the_raw_rows(): void
    {
        $monitor = Monitor::factory()->create();

        MonitorCheck::factory()->up()->create([
            'monitor_id' => $monitor->id,
            'response_time_ms' => 100,
            'checked_at' => now()->subDays(10)->setTime(8, 0),
        ]);
        MonitorCheck::factory()->up()->create([
            'monitor_id' => $monitor->id,
            'response_time_ms' => 200,
            'checked_at' => now()->subDays(10)->setTime(20, 0),
        ]);
        MonitorCheck::factory()->down()->create([
            'monitor_id' => $monitor->id,
            'checked_at' => now()->subHours(2),
        ]);

        $this->artisan('monitoring:compact-checks')->assertSuccessful();

        $this->assertSame(1, MonitorCheck::count());
        $this->assertSame(1, MonitorDailyStat::count());

        $stat = MonitorDailyStat::first();
        $this->assertSame($monitor->id, $stat->monitor_id);
        $this->assertSame(2, $stat->total_checks);
        $this->assertSame(2, $stat->up_count);
        $this->assertSame(150, $stat->avg_response_time_ms);
        $this->assertSame(now()->subDays(10)->toDateString(), $stat->date->toDateString());
    }

    public function test_it_leaves_recent_checks_uncompacted(): void
    {
        $monitor = Monitor::factory()->create();
        MonitorCheck::factory()->up()->create([
            'monitor_id' => $monitor->id,
            'checked_at' => now()->subDays(2),
        ]);

        $this->artisan('monitoring:compact-checks')->assertSuccessful();

        $this->assertSame(1, MonitorCheck::count());
        $this->assertSame(0, MonitorDailyStat::count());
    }
}
