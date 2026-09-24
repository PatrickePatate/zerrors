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

        return $this->downsample($points, 300);
    }

    /**
     * Caps $points to at most $maxPoints using the Largest-Triangle-Three-
     * Buckets algorithm, so the chart stays legible (and ApexCharts stays
     * fast) even when the selected range spans many days of raw checks.
     * Always keeps the first and last point; buckets the rest and keeps
     * whichever point in each bucket forms the largest triangle with the
     * previously-kept point and the next bucket's average — preserving the
     * shape of the response-time curve instead of just sampling every Nth
     * point.
     *
     * @param  array<int, array<string, mixed>>  $points
     * @return array<int, array<string, mixed>>
     */
    private function downsample(array $points, int $maxPoints): array
    {
        $count = count($points);

        if ($count <= $maxPoints || $maxPoints < 3) {
            return $points;
        }

        $sampled = [$points[0]];

        // Buckets exclude the first and last point, which are always kept.
        $bucketSize = ($count - 2) / ($maxPoints - 2);
        $previousIndex = 0;

        for ($i = 0; $i < $maxPoints - 2; $i++) {
            $bucketStart = (int) floor(($i + 1) * $bucketSize) + 1;
            $bucketEnd = (int) floor(($i + 2) * $bucketSize) + 1;
            $bucketEnd = min($bucketEnd, $count - 1);

            $nextBucketStart = $bucketEnd;
            $nextBucketEnd = min((int) floor(($i + 3) * $bucketSize) + 1, $count);
            $nextAvgTime = 0;
            $nextAvgValue = 0;
            $nextCount = max(1, $nextBucketEnd - $nextBucketStart);

            for ($j = $nextBucketStart; $j < $nextBucketEnd; $j++) {
                $nextAvgTime += strtotime($points[$j]['checked_at']);
                $nextAvgValue += $points[$j]['response_time_ms'] ?? 0;
            }
            $nextAvgTime /= $nextCount;
            $nextAvgValue /= $nextCount;

            $prevTime = strtotime($points[$previousIndex]['checked_at']);
            $prevValue = $points[$previousIndex]['response_time_ms'] ?? 0;

            $maxArea = -1;
            $maxAreaIndex = $bucketStart;

            for ($j = $bucketStart; $j < $bucketEnd; $j++) {
                $time = strtotime($points[$j]['checked_at']);
                $value = $points[$j]['response_time_ms'] ?? 0;

                $area = abs(
                    ($prevTime - $nextAvgTime) * ($value - $prevValue)
                    - ($prevTime - $time) * ($nextAvgValue - $prevValue)
                ) * 0.5;

                if ($area > $maxArea) {
                    $maxArea = $area;
                    $maxAreaIndex = $j;
                }
            }

            $sampled[] = $points[$maxAreaIndex];
            $previousIndex = $maxAreaIndex;
        }

        $sampled[] = $points[$count - 1];

        return $sampled;
    }
}
