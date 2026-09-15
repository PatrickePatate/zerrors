<?php

namespace Tests\Feature;

use App\Livewire\MonitorDetail;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class MonitorDetailChartTest extends TestCase
{
    use RefreshDatabase;

    public function test_chart_data_is_downsampled_when_there_are_many_checks(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id]);

        MonitorCheck::factory()
            ->count(500)
            ->sequence(fn ($sequence) => ['checked_at' => now()->subMinutes(500 - $sequence->index)])
            ->create(['monitor_id' => $monitor->id]);

        $component = Livewire::actingAs($user)->test(MonitorDetail::class, [
            'organization' => $organization,
            'monitor' => $monitor,
        ]);

        $chartChecks = $component->viewData('chartChecks');

        $this->assertLessThanOrEqual(300, count($chartChecks));
        $this->assertGreaterThan(2, count($chartChecks));
    }

    public function test_chart_data_is_not_downsampled_when_there_are_few_checks(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id]);

        MonitorCheck::factory()
            ->count(10)
            ->sequence(fn ($sequence) => ['checked_at' => now()->subMinutes(10 - $sequence->index)])
            ->create(['monitor_id' => $monitor->id]);

        $component = Livewire::actingAs($user)->test(MonitorDetail::class, [
            'organization' => $organization,
            'monitor' => $monitor,
        ]);

        $this->assertCount(10, $component->viewData('chartChecks'));
    }
}
