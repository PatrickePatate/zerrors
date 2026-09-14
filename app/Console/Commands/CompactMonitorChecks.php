<?php

namespace App\Console\Commands;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\MonitorCheck;
use App\Models\MonitorDailyStat;
use Carbon\Carbon;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('monitoring:compact-checks')]
#[Description('Roll up monitor_checks older than config(monitoring.compact_after_days) into one daily average per monitor.')]
class CompactMonitorChecks extends Command
{
    public function handle(): int
    {
        $compactAfterDays = (int) config('monitoring.compact_after_days', 7);
        $cutoff = now()->subDays($compactAfterDays)->startOfDay();

        $compactedDays = 0;

        Monitor::query()->select('id')->chunkById(100, function ($monitors) use ($cutoff, &$compactedDays) {
            foreach ($monitors as $monitor) {
                $compactedDays += $this->compactMonitor($monitor->id, $cutoff);
            }
        });

        $this->info("Compacted {$compactedDays} monitor-day(s) into daily stats.");

        return self::SUCCESS;
    }

    private function compactMonitor(int $monitorId, Carbon $cutoff): int
    {
        $days = MonitorCheck::query()
            ->where('monitor_id', $monitorId)
            ->where('checked_at', '<', $cutoff)
            ->selectRaw('date(checked_at) as day')
            ->distinct()
            ->pluck('day');

        foreach ($days as $day) {
            $stats = MonitorCheck::query()
                ->where('monitor_id', $monitorId)
                ->whereDate('checked_at', $day)
                ->selectRaw(
                    'count(*) as total,'.
                    'sum(case when status = ? then 1 else 0 end) as up_count,'.
                    'avg(response_time_ms) as avg_response_time_ms,'.
                    'avg(dns_time_ms) as avg_dns_time_ms,'.
                    'avg(connect_time_ms) as avg_connect_time_ms,'.
                    'avg(ssl_time_ms) as avg_ssl_time_ms,'.
                    'avg(ttfb_ms) as avg_ttfb_ms,'.
                    'avg(download_time_ms) as avg_download_time_ms',
                    [MonitorStatus::Up->value]
                )
                ->first();

            if (! $stats || (int) $stats->total === 0) {
                continue;
            }

            DB::transaction(function () use ($monitorId, $day, $stats) {
                MonitorDailyStat::updateOrCreate(
                    ['monitor_id' => $monitorId, 'date' => $day],
                    [
                        'total_checks' => (int) $stats->total,
                        'up_count' => (int) $stats->up_count,
                        'avg_response_time_ms' => $stats->avg_response_time_ms !== null ? (int) round($stats->avg_response_time_ms) : null,
                        'avg_dns_time_ms' => $stats->avg_dns_time_ms !== null ? (int) round($stats->avg_dns_time_ms) : null,
                        'avg_connect_time_ms' => $stats->avg_connect_time_ms !== null ? (int) round($stats->avg_connect_time_ms) : null,
                        'avg_ssl_time_ms' => $stats->avg_ssl_time_ms !== null ? (int) round($stats->avg_ssl_time_ms) : null,
                        'avg_ttfb_ms' => $stats->avg_ttfb_ms !== null ? (int) round($stats->avg_ttfb_ms) : null,
                        'avg_download_time_ms' => $stats->avg_download_time_ms !== null ? (int) round($stats->avg_download_time_ms) : null,
                    ]
                );

                MonitorCheck::query()
                    ->where('monitor_id', $monitorId)
                    ->whereDate('checked_at', $day)
                    ->delete();
            });
        }

        return $days->count();
    }
}
