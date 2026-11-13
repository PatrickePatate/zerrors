<?php

namespace App\Support\Status;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use App\Models\StatusIncident;
use App\Models\StatusPage;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class StatusPageData
{
    public const DAYS = 90;

    public const HISTORY_DAYS = 14;

    public const CACHE_SECONDS = 60;

    public static function cacheKey(StatusPage $page): string
    {
        return 'status-page:'.$page->id;
    }

    public static function forget(StatusPage $page): void
    {
        Cache::forget(self::cacheKey($page));
    }

    /**
     * Public-safe snapshot of a status page, cached for a short while.
     * Only scalars and arrays so it serialises into any cache store and
     * doubles as the JSON payload.
     *
     * @return array<string, mixed>
     */
    public function forPage(StatusPage $page): array
    {
        return Cache::remember(self::cacheKey($page), self::CACHE_SECONDS, fn (): array => $this->build($page));
    }

    /**
     * @return array<string, mixed>
     */
    public function build(StatusPage $page): array
    {
        $monitors = $page->monitors()->get()->map(fn (Monitor $monitor): array => $this->monitorData($monitor))->all();

        $statuses = array_column($monitors, 'status');
        $down = count(array_filter($statuses, fn (string $status): bool => $status === MonitorStatus::Down->value));

        $overall = match (true) {
            $down === 0 => 'operational',
            $down === count($statuses) => 'major_outage',
            default => 'partial_outage',
        };

        $incidents = $page->incidents()->with('updates')->orderByDesc('started_at')->get();

        $historyCutoff = now()->subDays(self::HISTORY_DAYS);

        return [
            'title' => $page->title,
            'description' => $page->description,
            'overall_status' => $overall,
            'generated_at' => now()->toIso8601String(),
            'monitors' => $monitors,
            'active_incidents' => $incidents->filter(fn (StatusIncident $incident): bool => ! $incident->isResolved())
                ->map(fn (StatusIncident $incident): array => $this->incidentData($incident))->values()->all(),
            'past_incidents' => $incidents->filter(fn (StatusIncident $incident): bool => $incident->isResolved() && $incident->started_at->gte($historyCutoff))
                ->map(fn (StatusIncident $incident): array => $this->incidentData($incident))->values()->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function monitorData(Monitor $monitor): array
    {
        $since = now()->subDays(self::DAYS - 1)->startOfDay();

        /** @var array<string, array{total: int, up: int}> $perDay */
        $perDay = [];

        $stats = $monitor->dailyStats()->where('date', '>=', $since->toDateString())->get(['date', 'total_checks', 'up_count']);

        foreach ($stats as $stat) {
            $key = $stat->date->toDateString();
            $perDay[$key]['total'] = ($perDay[$key]['total'] ?? 0) + $stat->total_checks;
            $perDay[$key]['up'] = ($perDay[$key]['up'] ?? 0) + $stat->up_count;
        }

        $rawChecks = $monitor->checks()
            ->where('checked_at', '>=', $since)
            ->groupBy(DB::raw('date(checked_at)'))
            ->selectRaw('date(checked_at) as day, count(*) as total, sum(case when status = ? then 1 else 0 end) as up_count', [MonitorStatus::Up->value])
            ->get();

        foreach ($rawChecks as $row) {
            $perDay[$row->day]['total'] = ($perDay[$row->day]['total'] ?? 0) + (int) $row->total;
            $perDay[$row->day]['up'] = ($perDay[$row->day]['up'] ?? 0) + (int) $row->up_count;
        }

        $days = [];
        $total = 0;
        $up = 0;

        for ($i = 0; $i < self::DAYS; $i++) {
            $date = $since->copy()->addDays($i)->toDateString();
            $dayTotal = $perDay[$date]['total'] ?? 0;
            $dayUp = $perDay[$date]['up'] ?? 0;
            $total += $dayTotal;
            $up += $dayUp;

            $days[] = [
                'date' => $date,
                'uptime' => $dayTotal > 0 ? round($dayUp / $dayTotal * 100, 2) : null,
            ];
        }

        return [
            'name' => $monitor->pivot->display_name ?: $monitor->name,
            'status' => $monitor->current_status->value,
            'uptime' => $total > 0 ? round($up / $total * 100, 2) : null,
            'days' => $days,
        ];
    }

    /**
     * @return array<string, mixed>
     */
    protected function incidentData(StatusIncident $incident): array
    {
        return [
            'title' => $incident->title,
            'started_at' => $incident->started_at->toIso8601String(),
            'resolved_at' => $incident->resolved_at?->toIso8601String(),
            'updates' => $incident->updates->map(fn ($update): array => [
                'status' => $update->status->value,
                'body' => $update->body,
                'created_at' => $update->created_at->toIso8601String(),
            ])->all(),
        ];
    }
}
