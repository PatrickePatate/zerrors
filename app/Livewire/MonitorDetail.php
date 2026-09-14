<?php

namespace App\Livewire;

use App\Models\Monitor;
use App\Models\Organization;
use Illuminate\Support\Carbon;
use Livewire\Component;

class MonitorDetail extends Component
{
    public Organization $organization;

    public Monitor $monitor;

    public string $chartStartDate;

    public string $chartEndDate;

    public function mount(Organization $organization, Monitor $monitor): void
    {
        $this->organization = $organization;
        $this->monitor = $monitor;
        $this->chartStartDate = now()->subDays(7)->toDateString();
        $this->chartEndDate = now()->toDateString();
    }

    public function setChartRange(int $days): void
    {
        $this->chartStartDate = now()->subDays($days)->toDateString();
        $this->chartEndDate = now()->toDateString();
    }

    public function render()
    {
        $checks = $this->monitor->checks()
            ->orderByDesc('checked_at')
            ->limit(100)
            ->get()
            ->reverse()
            ->values();

        $recentChecks = $checks->slice(-50)->values();

        $certificateExpiresAt = $this->monitor->check_certificate
            ? $this->monitor->latestCertificateExpiry()
            : null;

        // The most recent check that actually returned a parsed health_checks
        // payload — a transient failure on the very latest check (e.g. a
        // timeout) shouldn't blank out the last known set of check results.
        $latestHealthCheck = $checks->last(fn ($check) => $check->health_checks !== null);

        return view('livewire.monitor-detail', [
            'recentChecks' => $recentChecks,
            'chartChecks' => $this->chartData(),
            'uptime24h' => $this->monitor->uptimePercentage(24),
            'uptime7d' => $this->monitor->uptimePercentage(24 * 7),
            'uptime30d' => $this->monitor->uptimePercentage(24 * 30),
            'certificateExpiresAt' => $certificateExpiresAt,
            'certificateDaysRemaining' => $certificateExpiresAt ? (int) now()->diffInDays($certificateExpiresAt, false) : null,
            'healthChecks' => $latestHealthCheck?->health_checks,
            'healthChecksCheckedAt' => $latestHealthCheck?->checked_at,
        ]);
    }

    /**
     * Chart series for the selected [chartStartDate, chartEndDate] range.
     * Any portion of the range still within raw retention is read from
     * monitor_checks (per-check resolution); any older portion has already
     * been rolled up by CompactMonitorChecks into monitor_daily_stats, so it
     * is read from there instead — one averaged point per day.
     *
     * @return array<int, array<string, mixed>>
     */
    private function chartData(): array
    {
        $start = Carbon::parse($this->chartStartDate)->startOfDay();
        $end = Carbon::parse($this->chartEndDate)->endOfDay();
        $compactCutoff = now()->subDays((int) config('monitoring.compact_after_days', 7))->startOfDay();

        $points = [];

        if ($start->lt($compactCutoff)) {
            $dailyStats = $this->monitor->dailyStats()
                ->whereBetween('date', [$start->toDateString(), min($end, $compactCutoff)->toDateString()])
                ->orderBy('date')
                ->get();

            foreach ($dailyStats as $stat) {
                $points[] = [
                    'checked_at' => $stat->date->toIso8601String(),
                    'response_time_ms' => $stat->avg_response_time_ms,
                    'dns_time_ms' => $stat->avg_dns_time_ms,
                    'connect_time_ms' => $stat->avg_connect_time_ms,
                    'ssl_time_ms' => $stat->avg_ssl_time_ms,
                    'ttfb_ms' => $stat->avg_ttfb_ms,
                    'download_time_ms' => $stat->avg_download_time_ms,
                    'status' => $stat->uptimePercentage() > 0 ? 'up' : 'down',
                ];
            }
        }

        if ($end->gte($compactCutoff)) {
            $rawChecks = $this->monitor->checks()
                ->whereBetween('checked_at', [max($start, $compactCutoff), $end])
                ->orderBy('checked_at')
                ->get();

            foreach ($rawChecks as $check) {
                $points[] = [
                    'checked_at' => $check->checked_at->toIso8601String(),
                    'response_time_ms' => $check->response_time_ms,
                    'dns_time_ms' => $check->dns_time_ms,
                    'connect_time_ms' => $check->connect_time_ms,
                    'ssl_time_ms' => $check->ssl_time_ms,
                    'ttfb_ms' => $check->ttfb_ms,
                    'download_time_ms' => $check->download_time_ms,
                    'status' => $check->status->value,
                ];
            }
        }

        return $points;
    }
}
