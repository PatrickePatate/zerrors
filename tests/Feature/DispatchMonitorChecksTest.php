<?php

namespace Tests\Feature;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use App\Models\Organization;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Bus;
use Tests\TestCase;

class DispatchMonitorChecksTest extends TestCase
{
    use RefreshDatabase;

    public function test_due_active_monitors_are_dispatched(): void
    {
        Bus::fake();

        $organization = Organization::factory()->create();

        $due = Monitor::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'check_interval_minutes' => 5,
            'last_checked_at' => now()->subMinutes(10),
        ]);

        $this->artisan('monitoring:dispatch-checks')->assertSuccessful();

        Bus::assertDispatched(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitor->is($due));
    }

    public function test_never_checked_monitors_are_dispatched(): void
    {
        Bus::fake();

        $organization = Organization::factory()->create();

        $neverChecked = Monitor::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'last_checked_at' => null,
        ]);

        $this->artisan('monitoring:dispatch-checks')->assertSuccessful();

        Bus::assertDispatched(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitor->is($neverChecked));
    }

    public function test_not_yet_due_monitors_are_not_dispatched(): void
    {
        Bus::fake();

        $organization = Organization::factory()->create();

        $notDue = Monitor::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => true,
            'check_interval_minutes' => 30,
            'last_checked_at' => now()->subMinutes(5),
        ]);

        $this->artisan('monitoring:dispatch-checks')->assertSuccessful();

        Bus::assertNotDispatched(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitor->is($notDue));
    }

    public function test_inactive_monitors_are_not_dispatched(): void
    {
        Bus::fake();

        $organization = Organization::factory()->create();

        $inactive = Monitor::factory()->create([
            'organization_id' => $organization->id,
            'is_active' => false,
            'last_checked_at' => null,
        ]);

        $this->artisan('monitoring:dispatch-checks')->assertSuccessful();

        Bus::assertNotDispatched(RunMonitorCheck::class, fn (RunMonitorCheck $job) => $job->monitor->is($inactive));
    }
}
