<?php

namespace App\Services\Monitoring;

use App\Enums\MonitorHttpMethod;
use App\Enums\MonitorStatus;
use App\Enums\MonitorType;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use GuzzleHttp\TransferStats;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;
use Symfony\Component\Process\Process;
use Throwable;

class MonitorCheckRunner
{
    public function __construct(protected CertificateInspector $certificateInspector) {}

    public function run(Monitor $monitor): MonitorCheck
    {
        $result = match ($monitor->type) {
            MonitorType::Http => $this->runHttp($monitor),
            MonitorType::Ping => $this->runPing($monitor),
            MonitorType::LaravelHealth => $this->runLaravelHealth($monitor),
        };

        return $monitor->checks()->create([
            ...$result,
            'checked_at' => now(),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    protected function runHttp(Monitor $monitor): array
    {
        $start = microtime(true);
        $timing = $this->emptyTiming();

        try {
            $response = $this->sendRequest($monitor, $timing);
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            $expected = $monitor->expected_status_code ?? 200;
            $up = $response->status() === $expected;

            return $this->applyCertificateCheck($monitor, [
                'status' => $up ? MonitorStatus::Up : MonitorStatus::Down,
                'response_time_ms' => $elapsedMs,
                ...$timing,
                'status_code' => $response->status(),
                'error_message' => $up ? null : "Expected status {$expected}, got {$response->status()}.",
                'health_checks' => null,
            ]);
        } catch (Throwable $e) {
            return [
                'status' => MonitorStatus::Down,
                'response_time_ms' => null,
                ...$this->emptyTiming(),
                'status_code' => null,
                'error_message' => $e->getMessage(),
                'health_checks' => null,
                'certificate_expires_at' => null,
                'certificate_error' => null,
            ];
        }
    }

    /**
     * @param  array<string, int|null>  $timing  Filled in by reference from the
     *                                           underlying curl handler's stats.
     */
    protected function sendRequest(Monitor $monitor, array &$timing = []): Response
    {
        $timing = $this->emptyTiming();

        $request = Http::timeout($monitor->timeout_seconds)
            ->withUserAgent('Zerrors (+uptime)')
            ->withHeaders($monitor->headers ?? [])
            // Guzzle's "on_stats" option exposes the underlying curl handler's
            // transfer stats after each attempt completes, which is where the
            // DNS/connect/SSL/TTFB breakdown comes from — Laravel's HTTP client
            // has no higher-level API for these phases.
            ->withOptions([
                'on_stats' => function (TransferStats $stats) use (&$timing) {
                    $timing = $this->extractTiming($stats);
                },
            ])
            // A single retry after a short delay absorbs a transient blip on the
            // target (a mid-deploy config reload, a brief WAF hiccup, a restarting
            // app server) rather than flipping the monitor down — and reporting
            // "recovered" a minute later — for something that was never really an
            // outage. `throw: false` keeps this returning the (possibly still
            // failing) response normally instead of throwing once retries are
            // exhausted, since every caller here just inspects the response.
            ->retry(2, 500, throw: false);

        return match ($monitor->http_method) {
            MonitorHttpMethod::Post => $request->post($monitor->url),
            default => $request->get($monitor->url),
        };
    }

    /**
     * @return array<string, int|null>
     */
    protected function emptyTiming(): array
    {
        return [
            'dns_time_ms' => null,
            'connect_time_ms' => null,
            'ssl_time_ms' => null,
            'ttfb_ms' => null,
            'download_time_ms' => null,
        ];
    }

    /**
     * Turns curl's cumulative timing phases (each measured from the start of
     * the transfer) into the discrete, non-overlapping durations shown on the
     * monitor detail chart.
     *
     * @return array<string, int|null>
     */
    protected function extractTiming(TransferStats $stats): array
    {
        $handlerStats = $stats->getHandlerStats();

        if (empty($handlerStats)) {
            return $this->emptyTiming();
        }

        $toMs = fn (float $seconds): int => (int) round($seconds * 1000);

        $namelookup = (float) ($handlerStats['namelookup_time'] ?? 0);
        $connect = (float) ($handlerStats['connect_time'] ?? 0);
        $appconnect = (float) ($handlerStats['appconnect_time'] ?? 0);
        $startTransfer = (float) ($handlerStats['starttransfer_time'] ?? 0);
        $total = (float) ($handlerStats['total_time'] ?? 0);

        // appconnect_time stays 0 for a plain HTTP request (no TLS handshake).
        $preTransfer = $appconnect > 0 ? $appconnect : $connect;

        return [
            'dns_time_ms' => $toMs($namelookup),
            'connect_time_ms' => $toMs(max(0, $connect - $namelookup)),
            'ssl_time_ms' => $appconnect > 0 ? $toMs(max(0, $appconnect - $connect)) : null,
            'ttfb_ms' => $toMs(max(0, $startTransfer - $preTransfer)),
            'download_time_ms' => $toMs(max(0, $total - $startTransfer)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runPing(Monitor $monitor): array
    {
        $timeout = max(1, $monitor->timeout_seconds);

        $process = new Process(['ping', '-c', '1', '-W', (string) $timeout, $monitor->url]);
        $process->setTimeout($timeout + 5);

        $start = microtime(true);

        try {
            $process->run();
        } catch (Throwable $e) {
            return [
                'status' => MonitorStatus::Down,
                'response_time_ms' => null,
                'status_code' => null,
                'error_message' => $e->getMessage(),
                'health_checks' => null,
                'certificate_expires_at' => null,
                'certificate_error' => null,
            ];
        }

        $elapsedMs = (int) round((microtime(true) - $start) * 1000);

        if (! $process->isSuccessful()) {
            return [
                'status' => MonitorStatus::Down,
                'response_time_ms' => null,
                'status_code' => null,
                'error_message' => trim($process->getErrorOutput()) ?: 'Ping failed (host unreachable or request timed out).',
                'health_checks' => null,
                'certificate_expires_at' => null,
                'certificate_error' => null,
            ];
        }

        $output = $process->getOutput();
        $responseTime = $elapsedMs;

        if (preg_match('/time[=<]([\d.]+)\s*ms/i', $output, $matches)) {
            $responseTime = (int) round((float) $matches[1]);
        }

        return [
            'status' => MonitorStatus::Up,
            'response_time_ms' => $responseTime,
            'status_code' => null,
            'error_message' => null,
            'health_checks' => null,
            'certificate_expires_at' => null,
            'certificate_error' => null,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function runLaravelHealth(Monitor $monitor): array
    {
        $start = microtime(true);
        $timing = $this->emptyTiming();

        try {
            $response = $this->sendRequest($monitor, $timing);
            $elapsedMs = (int) round((microtime(true) - $start) * 1000);

            $expected = $monitor->expected_status_code ?? 200;

            // spatie/laravel-health's JSON endpoint (HealthCheckJsonResultsController)
            // returns {"finishedAt": ..., "checkResults": [{name, label, status,
            // notificationMessage, shortSummary, meta}, ...]} — there's no overall
            // "status" field, so it has to be derived from the individual results.
            // "failed"/"crashed" fail the monitor; "warning" is surfaced but doesn't
            // flip it down, matching how Oh Dear/laravel-health itself only escalates
            // on failed checks.
            $payload = $response->json();
            $checkResults = is_array($payload) ? ($payload['checkResults'] ?? null) : null;

            $failure = null;
            if (is_array($checkResults)) {
                foreach ($checkResults as $result) {
                    if (in_array($result['status'] ?? null, ['failed', 'crashed'], true)) {
                        $failure = ($result['label'] ?? $result['name'] ?? 'Check').': '.($result['notificationMessage'] ?: ($result['shortSummary'] ?? $result['status']));
                        break;
                    }
                }
            }

            $up = $response->status() === $expected && is_array($checkResults) && $failure === null;

            $errorMessage = null;
            if (! $up) {
                $errorMessage = $failure
                    ?? (is_array($checkResults)
                        ? "Expected status {$expected}, got {$response->status()}."
                        : 'Response was not a valid Laravel Health JSON payload.');
            }

            return $this->applyCertificateCheck($monitor, [
                'status' => $up ? MonitorStatus::Up : MonitorStatus::Down,
                'response_time_ms' => $elapsedMs,
                ...$timing,
                'status_code' => $response->status(),
                'error_message' => $errorMessage,
                'health_checks' => $checkResults,
            ]);
        } catch (Throwable $e) {
            return [
                'status' => MonitorStatus::Down,
                'response_time_ms' => null,
                ...$this->emptyTiming(),
                'status_code' => null,
                'error_message' => $e->getMessage(),
                'health_checks' => null,
                'certificate_expires_at' => null,
                'certificate_error' => null,
            ];
        }
    }

    /**
     * When certificate monitoring is enabled for an HTTPS target, fetch the
     * peer certificate's expiry and fold it into the check result: an
     * unreachable/invalid/expired certificate fails the check outright
     * (mirroring the "site is broken" outcome a real visitor would see),
     * while an expiry that's merely approaching stays informational —
     * surfaced on the detail page rather than flipping the monitor down.
     *
     * @param  array<string, mixed>  $result
     * @return array<string, mixed>
     */
    protected function applyCertificateCheck(Monitor $monitor, array $result): array
    {
        $result['certificate_expires_at'] = null;
        $result['certificate_error'] = null;

        if (! $monitor->check_certificate || ! str_starts_with(strtolower($monitor->url), 'https://')) {
            return $result;
        }

        try {
            $expiresAt = $this->certificateInspector->expiresAt($monitor->url, $monitor->timeout_seconds);
            $result['certificate_expires_at'] = $expiresAt;

            if ($expiresAt->isPast()) {
                $result['status'] = MonitorStatus::Down;
                $result['certificate_error'] = 'Certificate expired '.$expiresAt->diffForHumans();
                $result['error_message'] ??= $result['certificate_error'];
            }
        } catch (Throwable $e) {
            $result['status'] = MonitorStatus::Down;
            $result['certificate_error'] = $e->getMessage();
            $result['error_message'] ??= $e->getMessage();
        }

        return $result;
    }
}
