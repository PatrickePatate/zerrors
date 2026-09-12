<?php

namespace Tests\Feature;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\Organization;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MonitorFormTest extends TestCase
{
    use RefreshDatabase;

    protected function actingOwner(Organization $organization): User
    {
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'owner']);

        return $user;
    }

    public function test_headers_are_parsed_from_name_value_lines_for_an_http_monitor(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'API',
            'type' => MonitorType::Http->value,
            'url' => 'https://example.com',
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
            'headers_raw' => "Authorization: Bearer secret-token\nX-Api-Key: abc123\n\nmalformed-line",
        ])->assertRedirect();

        $monitor = Monitor::where('name', 'API')->firstOrFail();

        $this->assertSame([
            'Authorization' => 'Bearer secret-token',
            'X-Api-Key' => 'abc123',
        ], $monitor->headers);
    }

    public function test_expected_status_code_and_headers_are_discarded_for_a_ping_monitor(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'Server',
            'type' => MonitorType::Ping->value,
            'url' => 'example.com',
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
            'headers_raw' => 'Authorization: Bearer secret-token',
            'http_method' => MonitorHttpMethod::Post->value,
        ])->assertRedirect();

        $monitor = Monitor::where('name', 'Server')->firstOrFail();

        $this->assertNull($monitor->expected_status_code);
        $this->assertNull($monitor->headers);
        $this->assertSame(MonitorHttpMethod::Get, $monitor->http_method);
    }

    public function test_http_method_can_be_set_to_post(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'API',
            'type' => MonitorType::Http->value,
            'url' => 'https://example.com',
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
            'http_method' => MonitorHttpMethod::Post->value,
        ])->assertRedirect();

        $monitor = Monitor::where('name', 'API')->firstOrFail();

        $this->assertSame(MonitorHttpMethod::Post, $monitor->http_method);
    }

    public function test_http_method_defaults_to_get_when_not_submitted(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'API',
            'type' => MonitorType::Http->value,
            'url' => 'https://example.com',
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
        ])->assertRedirect();

        $monitor = Monitor::where('name', 'API')->firstOrFail();

        $this->assertSame(MonitorHttpMethod::Get, $monitor->http_method);
    }

    public function test_headers_are_encrypted_at_rest(): void
    {
        $organization = Organization::factory()->create();
        $monitor = Monitor::factory()->create([
            'organization_id' => $organization->id,
            'headers' => ['Authorization' => 'Bearer secret-token'],
        ]);

        $rawColumnValue = $monitor->getRawOriginal('headers');

        $this->assertIsString($rawColumnValue);
        $this->assertStringNotContainsString('secret-token', $rawColumnValue);
    }

    public function test_unchecking_active_persists_as_inactive(): void
    {
        // A plain HTML checkbox that's unchecked is simply absent from the
        // submitted form; the form now pairs it with a hidden "0" fallback
        // (resources/views/components/form/checkbox.blade.php) so the server
        // still receives an explicit is_active=0 rather than nothing at all.
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'is_active' => true]);

        $this->actingAs($user)->patch(route('organizations.monitors.update', [$organization, $monitor]), [
            'name' => $monitor->name,
            'type' => $monitor->type->value,
            'url' => $monitor->url,
            'check_interval_minutes' => $monitor->check_interval_minutes,
            'timeout_seconds' => $monitor->timeout_seconds,
            'is_active' => '0',
        ])->assertRedirect();

        $this->assertFalse($monitor->fresh()->is_active);
    }

    public function test_owner_can_delete_a_monitor_by_typing_the_confirmation_phrase(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'name' => 'API']);

        $this->actingAs($user)
            ->delete(route('organizations.monitors.destroy', [$organization, $monitor]), [
                'confirm_name' => 'Bye bye API',
            ])
            ->assertRedirect(route('organizations.monitors.index', $organization));

        $this->assertModelMissing($monitor);
    }

    public function test_deleting_a_monitor_requires_the_exact_confirmation_phrase(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'name' => 'API']);

        $this->actingAs($user)
            ->delete(route('organizations.monitors.destroy', [$organization, $monitor]), [
                'confirm_name' => 'wrong phrase',
            ])
            ->assertSessionHasErrors('confirm_name');

        $this->assertModelExists($monitor);
    }

    public function test_members_cannot_delete_a_monitor(): void
    {
        $organization = Organization::factory()->create();
        $user = User::factory()->create();
        $organization->users()->attach($user->id, ['role' => 'member']);
        $monitor = Monitor::factory()->create(['organization_id' => $organization->id, 'name' => 'API']);

        $this->actingAs($user)
            ->delete(route('organizations.monitors.destroy', [$organization, $monitor]), [
                'confirm_name' => 'Bye bye API',
            ])
            ->assertForbidden();

        $this->assertModelExists($monitor);
    }
}
