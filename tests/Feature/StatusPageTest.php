<?php

namespace Tests\Feature;

use App\Enums\MonitorStatus;
use App\Enums\StatusIncidentState;
use App\Events\MonitorStatusChanged;
use App\Listeners\ManageStatusIncidents;
use App\Livewire\StatusPageManager;
use App\Models\Monitor;
use App\Models\MonitorDailyStat;
use App\Models\Organization;
use App\Models\StatusIncident;
use App\Models\StatusPage;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Livewire\Livewire;
use Tests\TestCase;

class StatusPageTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->withoutVite();
        Cache::flush();
    }

    protected function memberWithRole(Organization $organization, string $role): User
    {
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => $role]);

        return $user;
    }

    public function test_disabled_or_unknown_page_returns_404(): void
    {
        StatusPage::factory()->private()->create(['slug' => 'hidden']);

        $this->get('/status/hidden')->assertNotFound();
        $this->get('/status/hidden.json')->assertNotFound();
        $this->get('/status/nope')->assertNotFound();
    }

    public function test_public_page_shows_only_selected_monitors_with_public_names(): void
    {
        $page = StatusPage::factory()->create(['slug' => 'acme', 'title' => 'Acme Status']);
        $shown = Monitor::factory()->up()->create(['organization_id' => $page->organization_id, 'name' => 'internal-api-01', 'url' => 'https://secret.internal.example/health']);
        $hidden = Monitor::factory()->up()->create(['organization_id' => $page->organization_id, 'name' => 'hidden-monitor', 'url' => 'https://hidden.example/x']);
        $page->monitors()->attach($shown->id, ['display_name' => 'Public API', 'position' => 0]);

        $this->get('/status/acme')
            ->assertOk()
            ->assertSee('Acme Status')
            ->assertSee('Public API')
            ->assertSee('All systems operational')
            ->assertDontSee('internal-api-01')
            ->assertDontSee('hidden-monitor')
            ->assertDontSee('secret.internal.example')
            ->assertDontSee('hidden.example');
    }

    public function test_banner_reflects_partial_and_major_outage(): void
    {
        $page = StatusPage::factory()->create(['slug' => 'acme']);
        $up = Monitor::factory()->up()->create(['organization_id' => $page->organization_id]);
        $down = Monitor::factory()->down()->create(['organization_id' => $page->organization_id]);
        $page->monitors()->attach([$up->id => ['position' => 0], $down->id => ['position' => 1]]);

        $this->getJson('/status/acme.json')->assertJsonPath('overall_status', 'partial_outage');

        $up->update(['current_status' => MonitorStatus::Down]);
        Cache::flush();

        $this->getJson('/status/acme.json')->assertJsonPath('overall_status', 'major_outage');
    }

    public function test_uptime_is_calculated_from_daily_stats(): void
    {
        $page = StatusPage::factory()->create(['slug' => 'acme']);
        $monitor = Monitor::factory()->up()->create(['organization_id' => $page->organization_id]);
        $page->monitors()->attach($monitor->id, ['display_name' => 'API', 'position' => 0]);

        MonitorDailyStat::factory()->create(['monitor_id' => $monitor->id, 'date' => now()->subDays(2)->toDateString(), 'total_checks' => 100, 'up_count' => 100]);
        MonitorDailyStat::factory()->create(['monitor_id' => $monitor->id, 'date' => now()->subDay()->toDateString(), 'total_checks' => 100, 'up_count' => 90]);

        $response = $this->getJson('/status/acme.json')->assertOk();

        $response->assertJsonPath('monitors.0.uptime', 95);
        $this->assertCount(90, $response->json('monitors.0.days'));
        $this->assertSame(90, $response->json('monitors.0.days.88.uptime'));
        $this->assertNull($response->json('monitors.0.days.0.uptime'));
    }

    public function test_json_endpoint_returns_structured_data_without_internal_details(): void
    {
        $page = StatusPage::factory()->create(['slug' => 'acme']);
        $monitor = Monitor::factory()->up()->create(['organization_id' => $page->organization_id, 'url' => 'https://secret.example/health']);
        $page->monitors()->attach($monitor->id, ['display_name' => 'API', 'position' => 0]);

        $this->getJson('/status/acme.json')
            ->assertOk()
            ->assertJsonStructure(['title', 'overall_status', 'monitors' => [['name', 'status', 'uptime', 'days']], 'active_incidents', 'past_incidents'])
            ->assertJsonPath('monitors.0.name', 'API');

        $this->assertStringNotContainsString('secret.example', $this->get('/status/acme.json')->getContent());
    }

    public function test_incidents_are_listed_active_and_recent_history_only(): void
    {
        $page = StatusPage::factory()->create(['slug' => 'acme']);
        StatusIncident::factory()->create(['status_page_id' => $page->id, 'title' => 'Ongoing thing']);
        StatusIncident::factory()->create(['status_page_id' => $page->id, 'title' => 'Fixed recently', 'started_at' => now()->subDays(3), 'resolved_at' => now()->subDays(2)]);
        StatusIncident::factory()->create(['status_page_id' => $page->id, 'title' => 'Ancient', 'started_at' => now()->subDays(30), 'resolved_at' => now()->subDays(29)]);

        $this->get('/status/acme')
            ->assertSee('Ongoing thing')
            ->assertSee('Fixed recently')
            ->assertDontSee('Ancient');
    }

    public function test_listener_is_registered_exactly_once(): void
    {
        $listeners = collect(Event::getRawListeners()[MonitorStatusChanged::class] ?? [])
            ->filter(fn ($listener) => is_string($listener) && str_starts_with($listener, ManageStatusIncidents::class));

        $this->assertCount(1, $listeners);
    }

    public function test_auto_incident_opens_when_monitor_goes_down_and_resolves_on_recovery(): void
    {
        $page = StatusPage::factory()->create();
        $monitor = Monitor::factory()->up()->create(['organization_id' => $page->organization_id]);
        $page->monitors()->attach($monitor->id, ['display_name' => 'API', 'position' => 0]);
        $other = Monitor::factory()->create(['organization_id' => $page->organization_id]);

        $listener = new ManageStatusIncidents;
        $listener->handle(new MonitorStatusChanged($other, MonitorStatus::Up, MonitorStatus::Down));
        $this->assertSame(0, StatusIncident::count());

        $listener->handle(new MonitorStatusChanged($monitor, MonitorStatus::Up, MonitorStatus::Down));
        $listener->handle(new MonitorStatusChanged($monitor, MonitorStatus::Up, MonitorStatus::Down));

        $incident = StatusIncident::sole();
        $this->assertTrue($incident->auto);
        $this->assertNull($incident->resolved_at);
        $this->assertSame(StatusIncidentState::Investigating, $incident->updates()->sole()->status);
        $this->assertSame('API is experiencing issues', $incident->title);

        $listener->handle(new MonitorStatusChanged($monitor, MonitorStatus::Down, MonitorStatus::Up));

        $incident->refresh();
        $this->assertNotNull($incident->resolved_at);
        $this->assertSame(StatusIncidentState::Resolved, $incident->updates()->get()->last()->status);
    }

    public function test_dispatching_the_event_runs_the_listener(): void
    {
        $page = StatusPage::factory()->create();
        $monitor = Monitor::factory()->down()->create(['organization_id' => $page->organization_id]);
        $page->monitors()->attach($monitor->id, ['position' => 0]);

        MonitorStatusChanged::dispatch($monitor, MonitorStatus::Up, MonitorStatus::Down);

        $this->assertSame(1, StatusIncident::where('auto', true)->count());
    }

    public function test_members_cannot_access_the_admin_page(): void
    {
        $organization = Organization::factory()->create();
        $member = $this->memberWithRole($organization, 'member');
        $admin = $this->memberWithRole($organization, 'admin');

        $this->actingAs($member)->get(route('organizations.status-page.edit', $organization))->assertForbidden();
        $this->actingAs($admin)->get(route('organizations.status-page.edit', $organization))->assertOk();

        $this->actingAs($member);
        Livewire::test(StatusPageManager::class, ['organization' => $organization])->assertForbidden();
    }

    public function test_admin_can_configure_the_page_and_run_a_manual_incident(): void
    {
        $organization = Organization::factory()->create();
        $admin = $this->memberWithRole($organization, 'owner');
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'name' => 'Real name']);
        $this->actingAs($admin);

        $component = Livewire::test(StatusPageManager::class, ['organization' => $organization])
            ->set('slug', 'My Status')
            ->set('title', 'My Status Page')
            ->set('isPublic', true)
            ->set('monitorRows.0.selected', true)
            ->set('monitorRows.0.name', 'Website')
            ->call('save')
            ->assertHasNoErrors();

        $page = $organization->refresh()->statusPage;
        $this->assertSame('my-status', $page->slug);
        $this->assertTrue($page->is_public);
        $this->assertSame('Website', $page->monitors()->sole()->pivot->display_name);
        $this->assertDatabaseHas('audit_logs', ['organization_id' => $organization->id, 'action' => 'status_page.updated']);

        $component
            ->set('incidentTitle', 'Database maintenance')
            ->set('incidentBody', 'Investigating slowness')
            ->call('createIncident')
            ->assertHasNoErrors();

        $incident = StatusIncident::sole();
        $this->assertFalse($incident->auto);
        $this->assertNull($incident->resolved_at);
        $this->get('/status/my-status')->assertSee('Database maintenance');

        $component
            ->set("updateBodies.{$incident->id}", 'Fix deployed')
            ->set("updateStates.{$incident->id}", 'monitoring')
            ->call('postUpdate', $incident->id);
        $this->assertCount(2, $incident->updates);

        $component->call('resolveIncident', $incident->id);
        $this->assertNotNull($incident->refresh()->resolved_at);
        $this->assertSame(StatusIncidentState::Resolved, $incident->updates()->get()->last()->status);
    }
}
