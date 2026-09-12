<?php

namespace Tests\Unit;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Services\Monitoring\CertificateInspector;
use App\Services\Monitoring\MonitorCheckRunner;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use RuntimeException;
use Tests\TestCase;

class MonitorCheckRunnerTest extends TestCase
{
    use RefreshDatabase;

    public function test_http_monitor_records_an_up_check_on_matching_status(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
        $this->assertSame(200, $check->status_code);
        $this->assertNull($check->error_message);
        $this->assertIsInt($check->response_time_ms);
    }

    public function test_http_monitor_retries_once_and_recovers_from_a_transient_failure(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('server error', 503)
            ->push('ok', 200),
        ]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
        $this->assertSame(200, $check->status_code);
        Http::assertSentCount(2);
    }

    public function test_http_monitor_still_reports_down_after_retry_exhausted(): void
    {
        Http::fake(['*' => Http::response('server error', 503)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Down, $check->status);
        $this->assertSame(503, $check->status_code);
        Http::assertSentCount(2);
    }

    public function test_http_monitor_sends_configured_headers(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'headers' => ['Authorization' => 'Bearer secret-token', 'X-Api-Key' => 'abc123'],
        ]);

        app(MonitorCheckRunner::class)->run($monitor);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token')
            && $request->hasHeader('X-Api-Key', 'abc123'));
    }

    public function test_http_monitor_sends_a_get_request_by_default(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
        ]);

        app(MonitorCheckRunner::class)->run($monitor);

        Http::assertSent(fn ($request) => $request->method() === 'GET');
    }

    public function test_http_monitor_sends_a_post_request_when_configured(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'http_method' => MonitorHttpMethod::Post,
        ]);

        app(MonitorCheckRunner::class)->run($monitor);

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_laravel_health_monitor_sends_a_post_request_when_configured(): void
    {
        Http::fake(['*' => Http::response(['finishedAt' => time(), 'checkResults' => []], 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'http_method' => MonitorHttpMethod::Post,
        ]);

        app(MonitorCheckRunner::class)->run($monitor);

        Http::assertSent(fn ($request) => $request->method() === 'POST');
    }

    public function test_laravel_health_monitor_sends_configured_headers(): void
    {
        Http::fake(['*' => Http::response(['finishedAt' => time(), 'checkResults' => []], 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'headers' => ['Authorization' => 'Bearer secret-token'],
        ]);

        app(MonitorCheckRunner::class)->run($monitor);

        Http::assertSent(fn ($request) => $request->hasHeader('Authorization', 'Bearer secret-token'));
    }

    public function test_http_monitor_records_a_down_check_on_mismatched_status(): void
    {
        Http::fake(['*' => Http::response('error', 500)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Down, $check->status);
        $this->assertSame(500, $check->status_code);
        $this->assertNotNull($check->error_message);
    }

    public function test_laravel_health_monitor_parses_ok_status_and_checks(): void
    {
        // Real shape returned by spatie/laravel-health's JSON endpoint
        // (HealthCheckJsonResultsController) — no overall "status" field, just
        // a list of individual check results.
        Http::fake(['*' => Http::response([
            'finishedAt' => time(),
            'checkResults' => [
                ['name' => 'database', 'label' => 'Database', 'status' => 'ok', 'notificationMessage' => '', 'shortSummary' => 'Ok'],
            ],
        ], 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
        $this->assertIsArray($check->health_checks);
        $this->assertSame('database', $check->health_checks[0]['name']);
    }

    public function test_laravel_health_monitor_stays_up_with_a_warning_check(): void
    {
        Http::fake(['*' => Http::response([
            'finishedAt' => time(),
            'checkResults' => [
                ['name' => 'disk', 'label' => 'Disk space', 'status' => 'warning', 'notificationMessage' => 'Getting full', 'shortSummary' => '85%'],
            ],
        ], 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
    }

    public function test_laravel_health_monitor_records_down_when_a_check_failed(): void
    {
        Http::fake(['*' => Http::response([
            'finishedAt' => time(),
            'checkResults' => [
                ['name' => 'database', 'label' => 'Database', 'status' => 'failed', 'notificationMessage' => 'Connection refused', 'shortSummary' => 'Error'],
            ],
        ], 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Down, $check->status);
        $this->assertStringContainsString('Connection refused', $check->error_message);
        $this->assertIsArray($check->health_checks);
    }

    public function test_laravel_health_monitor_records_down_when_payload_is_not_health_json(): void
    {
        Http::fake(['*' => Http::response('<html>not json</html>', 200)]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Down, $check->status);
        $this->assertNotNull($check->error_message);
    }

    public function test_laravel_health_monitor_retries_once_and_recovers_from_a_transient_403(): void
    {
        Http::fake(['*' => Http::sequence()
            ->push('forbidden', 403)
            ->push(['finishedAt' => time(), 'checkResults' => [
                ['name' => 'database', 'label' => 'Database', 'status' => 'ok', 'notificationMessage' => '', 'shortSummary' => 'Ok'],
            ]], 200),
        ]);

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::LaravelHealth,
            'url' => 'https://example.com/health-json',
            'expected_status_code' => 200,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
        Http::assertSentCount(2);
    }

    public function test_certificate_check_is_skipped_when_not_enabled(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->mock(CertificateInspector::class)->shouldNotReceive('expiresAt');

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'check_certificate' => false,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
        $this->assertNull($check->certificate_expires_at);
    }

    public function test_certificate_check_records_expiry_and_stays_up_when_valid(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $expiresAt = now()->addDays(60);
        $this->mock(CertificateInspector::class)
            ->shouldReceive('expiresAt')
            ->once()
            ->andReturn(Carbon::instance($expiresAt));

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'check_certificate' => true,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
        $this->assertNotNull($check->certificate_expires_at);
        $this->assertTrue($check->certificate_expires_at->isSameDay($expiresAt));
    }

    public function test_certificate_check_marks_monitor_down_when_certificate_expired(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $expiresAt = now()->subDays(2);
        $this->mock(CertificateInspector::class)
            ->shouldReceive('expiresAt')
            ->once()
            ->andReturn(Carbon::instance($expiresAt));

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'check_certificate' => true,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Down, $check->status);
        $this->assertNotNull($check->certificate_error);
        $this->assertStringContainsString('expired', $check->error_message);
    }

    public function test_certificate_check_marks_monitor_down_when_it_cannot_be_read(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);

        $this->mock(CertificateInspector::class)
            ->shouldReceive('expiresAt')
            ->once()
            ->andThrow(new RuntimeException('Unable to establish TLS connection to read certificate: connection refused'));

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'https://example.com',
            'check_certificate' => true,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Down, $check->status);
        $this->assertNull($check->certificate_expires_at);
        $this->assertStringContainsString('TLS connection', $check->certificate_error);
    }

    public function test_certificate_check_is_skipped_for_non_https_urls(): void
    {
        Http::fake(['*' => Http::response('ok', 200)]);
        $this->mock(CertificateInspector::class)->shouldNotReceive('expiresAt');

        $monitor = Monitor::factory()->create([
            'type' => MonitorType::Http,
            'url' => 'http://example.com',
            'check_certificate' => true,
        ]);

        $check = app(MonitorCheckRunner::class)->run($monitor);

        $this->assertSame(MonitorStatus::Up, $check->status);
    }
}
