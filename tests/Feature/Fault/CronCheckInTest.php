<?php

namespace Tests\Feature\Fault;

use App\Models\CronCheckIn;
use App\Models\CronMonitor;
use App\Models\FaultProject;
use App\Models\User;
use App\Notifications\CronMonitorStatusChanged;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;

class CronCheckInTest extends IngestExtrasTestCase
{
    private function checkIn(FaultProject $project, array $data): void
    {
        $this->sendEnvelope($project, [[['type' => 'check_in'], json_encode($data)]])->assertOk();
    }

    protected function tearDown(): void
    {
        Carbon::setTestNow();
        parent::tearDown();
    }

    public function test_check_in_lifecycle_upserts_monitor_and_updates_check_in(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:30');
        $project = FaultProject::factory()->create();
        $config = ['schedule' => ['type' => 'crontab', 'value' => '*/5 * * * *'], 'checkin_margin' => 2, 'max_runtime' => 10, 'timezone' => 'Europe/Paris'];

        $this->checkIn($project, ['check_in_id' => 'abc', 'monitor_slug' => 'nightly', 'status' => 'in_progress', 'monitor_config' => $config]);
        $monitor = CronMonitor::firstOrFail();
        $this->assertSame('unknown', $monitor->status);
        $this->assertSame('2026-01-01 10:05:00', $monitor->next_expected_at->toDateTimeString());
        $this->assertSame(2, $monitor->checkin_margin);

        $this->checkIn($project, ['check_in_id' => 'abc', 'monitor_slug' => 'nightly', 'status' => 'ok', 'duration' => 12.5, 'environment' => 'prod']);

        $this->assertSame(1, CronCheckIn::count());
        $this->assertSame('ok', CronCheckIn::first()->status);
        $this->assertSame(12.5, CronCheckIn::first()->duration);
        $this->assertSame('ok', $monitor->fresh()->status);
    }

    public function test_interval_schedule_computes_next_expected(): void
    {
        Carbon::setTestNow('2026-01-01 10:00:00');
        $project = FaultProject::factory()->create();
        $this->checkIn($project, ['monitor_slug' => 'hourly', 'status' => 'ok', 'monitor_config' => ['schedule' => ['type' => 'interval', 'value' => 2, 'unit' => 'hour']]]);

        $this->assertSame('2026-01-01 12:00:00', CronMonitor::firstOrFail()->next_expected_at->toDateTimeString());
    }

    public function test_missed_detection_timeouts_and_notifications(): void
    {
        Notification::fake();
        Carbon::setTestNow('2026-01-01 10:00:00');
        $project = FaultProject::factory()->create();
        $member = User::factory()->create();
        $project->organization->users()->attach($member->id, ['role' => 'member']);

        $this->checkIn($project, ['monitor_slug' => 'job', 'status' => 'ok', 'monitor_config' => ['schedule' => ['type' => 'crontab', 'value' => '*/10 * * * *'], 'checkin_margin' => 3, 'max_runtime' => 5]]);
        Notification::assertNothingSent();

        Carbon::setTestNow('2026-01-01 10:12:00');
        $this->artisan('monitoring:detect-missed-check-ins')->assertSuccessful();
        $this->assertSame('ok', CronMonitor::first()->status);

        Carbon::setTestNow('2026-01-01 10:14:00');
        $this->artisan('monitoring:detect-missed-check-ins')->assertSuccessful();
        $monitor = CronMonitor::first();
        $this->assertSame('missed', $monitor->status);
        $this->assertSame('2026-01-01 10:20:00', $monitor->next_expected_at->toDateTimeString());
        Notification::assertSentTo($member, CronMonitorStatusChanged::class, fn ($n) => $n->newStatus === 'missed');

        Carbon::setTestNow('2026-01-01 10:40:00');
        $this->artisan('monitoring:detect-missed-check-ins');
        Notification::assertSentToTimes($member, CronMonitorStatusChanged::class, 1);

        $this->checkIn($project, ['check_in_id' => 'r', 'monitor_slug' => 'job', 'status' => 'ok']);
        Notification::assertSentTo($member, CronMonitorStatusChanged::class, fn ($n) => $n->newStatus === 'ok');

        $this->checkIn($project, ['check_in_id' => 'p', 'monitor_slug' => 'job', 'status' => 'in_progress']);
        Carbon::setTestNow('2026-01-01 10:47:00');
        $this->artisan('monitoring:detect-missed-check-ins');
        $this->assertSame('timeout', CronCheckIn::where('check_in_id', 'p')->first()->status);
        $this->assertSame('error', $monitor->fresh()->status);
        Notification::assertSentTo($member, CronMonitorStatusChanged::class, fn ($n) => $n->newStatus === 'error');
    }

    public function test_old_check_ins_are_pruned_and_index_page_renders(): void
    {
        $project = FaultProject::factory()->create();
        $monitor = CronMonitor::factory()->create(['fault_project_id' => $project->id, 'schedule_type' => null, 'name' => 'Nightly <b>']);
        $old = CronCheckIn::factory()->create(['cron_monitor_id' => $monitor->id]);
        $old->forceFill(['created_at' => now()->subDays(31)])->save();
        CronCheckIn::factory()->create(['cron_monitor_id' => $monitor->id]);

        $this->artisan('monitoring:detect-missed-check-ins');
        $this->assertSame(1, CronCheckIn::count());

        $user = User::factory()->create();
        $project->organization->users()->attach($user->id, ['role' => 'member']);
        $this->actingAs($user)->get(route('organizations.crons.index', [$project->organization, $project]))
            ->assertOk()->assertSee('Nightly &lt;b&gt;', false);
    }
}
