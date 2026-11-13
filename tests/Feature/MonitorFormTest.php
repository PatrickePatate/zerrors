<?php

namespace Tests\Feature;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\Organization;
use App\Models\User;
use App\Support\Net\OutboundUrlGuard;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class MonitorFormTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Fake DNS answers for hostnames; anything not listed resolves to a public address.
     *
     * @var array<string, list<string>>
     */
    protected array $dnsRecords = [];

    protected function setUp(): void
    {
        parent::setUp();

        config()->set('monitoring.allow_private_targets', false);

        $this->app->instance(OutboundUrlGuard::class, new OutboundUrlGuard(
            fn (string $host): array => $this->dnsRecords[$host] ?? ['93.184.216.34'],
        ));
    }

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

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function blockedTargets(): array
    {
        return [
            'loopback URL' => [MonitorType::Http->value, 'http://127.0.0.1:8080/', 'url'],
            'cloud metadata URL' => [MonitorType::Http->value, 'http://169.254.169.254/latest/meta-data/', 'url'],
            'RFC1918 health URL' => [MonitorType::LaravelHealth->value, 'https://10.1.2.3/health-json', 'url'],
            'IPv6 loopback URL' => [MonitorType::Http->value, 'http://[::1]/', 'url'],
            'private ping host' => [MonitorType::Ping->value, '192.168.0.10', 'url'],
            'non-http scheme' => [MonitorType::Http->value, 'file:///etc/passwd', 'url'],
        ];
    }

    #[DataProvider('blockedTargets')]
    public function test_a_private_or_reserved_target_fails_validation_on_store(string $type, string $url, string $field): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'Internal',
            'type' => $type,
            'url' => $url,
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
        ])->assertSessionHasErrors($field);

        $this->assertDatabaseMissing('monitors', ['name' => 'Internal']);
    }

    public function test_a_private_target_fails_validation_on_update(): void
    {
        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);
        $monitor = Monitor::factory()->create([
            'organization_id' => $organization->id,
            'type' => MonitorType::Http,
            'url' => 'https://example.com/',
        ]);

        $this->actingAs($user)->patch(route('organizations.monitors.update', [$organization, $monitor]), [
            'name' => $monitor->name,
            'type' => MonitorType::Http->value,
            'url' => 'http://169.254.169.254/latest/meta-data/',
            'check_interval_minutes' => $monitor->check_interval_minutes,
            'timeout_seconds' => $monitor->timeout_seconds,
        ])->assertSessionHasErrors('url');

        $this->assertSame('https://example.com/', $monitor->fresh()->url);
    }

    public function test_a_hostname_resolving_to_a_private_address_fails_validation(): void
    {
        $this->dnsRecords['intranet.example.com'] = ['10.0.0.20'];

        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'Intranet',
            'type' => MonitorType::Http->value,
            'url' => 'https://intranet.example.com/',
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
        ])->assertSessionHasErrors('url');
    }

    public function test_a_private_target_is_accepted_when_private_targets_are_allowed(): void
    {
        config()->set('monitoring.allow_private_targets', true);

        $organization = Organization::factory()->create();
        $user = $this->actingOwner($organization);

        $this->actingAs($user)->post(route('organizations.monitors.store', $organization), [
            'name' => 'LAN',
            'type' => MonitorType::Http->value,
            'url' => 'http://192.168.1.50:8080/',
            'check_interval_minutes' => 5,
            'timeout_seconds' => 10,
            'expected_status_code' => 200,
        ])->assertSessionHasNoErrors()->assertRedirect();

        $this->assertDatabaseHas('monitors', ['name' => 'LAN', 'url' => 'http://192.168.1.50:8080/']);
    }
}
