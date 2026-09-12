<?php

namespace Tests\Feature;

use App\Models\Monitor;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitoringDashboardTest extends TestCase
{
    use RefreshDatabase;

    public function test_members_can_view_the_monitors_index(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        Monitor::factory()->create(['organization_id' => $organization->id, 'name' => 'API Health']);

        $response = $this->actingAs($user)->get(route('organizations.monitors.index', $organization));

        $response->assertOk()->assertSeeText('API Health');
    }

    public function test_members_can_view_a_monitor_show_page(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'name' => 'API Health']);

        $response = $this->actingAs($user)->get(route('organizations.monitors.show', [$organization, $monitor]));

        $response->assertOk()->assertSeeText('API Health');
    }

    public function test_non_members_cannot_view_the_monitors_index(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();

        $response = $this->actingAs($user)->get(route('organizations.monitors.index', $organization));

        $response->assertForbidden();
    }

    public function test_non_members_cannot_view_a_monitor_show_page(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id]);

        $response = $this->actingAs($user)->get(route('organizations.monitors.show', [$organization, $monitor]));

        $response->assertForbidden();
    }
}
