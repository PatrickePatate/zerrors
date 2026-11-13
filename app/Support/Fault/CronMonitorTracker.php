<?php

namespace App\Support\Fault;

use App\Models\CronCheckIn;
use App\Models\CronMonitor;
use App\Models\FaultProject;
use App\Notifications\CronMonitorStatusChanged;
use Cron\CronExpression;
use DateTimeZone;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Notification;
use Throwable;

class CronMonitorTracker
{
    private const STATUSES = ['in_progress', 'ok', 'error'];

    private const UNITS = ['minute', 'hour', 'day', 'week', 'month', 'year'];

    /**
     * Apply a Sentry `check_in` payload: upsert the monitor from its
     * monitor_config, record the check-in and run status transitions.
     *
     * @param  array<string, mixed>  $data
     */
    public function record(FaultProject $project, array $data): void
    {
        $status = $data['status'] ?? null;
        $slug = mb_substr((string) ($data['monitor_slug'] ?? ''), 0, 191);

        if ($slug === '' || ! in_array($status, self::STATUSES, true)) {
            return;
        }

        $monitor = $this->resolveMonitor($project, $slug, is_array($data['monitor_config'] ?? null) ? $data['monitor_config'] : null);

        if ($monitor === null) {
            return;
        }

        $checkInId = isset($data['check_in_id']) && is_string($data['check_in_id']) ? mb_substr($data['check_in_id'], 0, 64) : null;
        $checkIn = $checkInId !== null ? $monitor->checkIns()->where('check_in_id', $checkInId)->first() : null;
        $attributes = [
            'status' => $status,
            'duration' => is_numeric($data['duration'] ?? null) ? (float) $data['duration'] : null,
            'environment' => is_string($data['environment'] ?? null) ? mb_substr($data['environment'], 0, 255) : null,
        ];

        if ($checkIn !== null) {
            $checkIn->update(array_filter($attributes, fn ($value) => $value !== null));
        } else {
            $monitor->checkIns()->create($attributes + ['check_in_id' => $checkInId]);
        }

        $previous = $monitor->status;
        $now = now();

        $monitor->last_check_in_at = $now;
        $monitor->next_expected_at = $this->nextExpectedAt($monitor, $now);

        if ($status !== 'in_progress') {
            $monitor->status = $status;
        }

        $monitor->save();

        $this->notifyOnTransition($monitor, $previous);
    }

    /**
     * Mark monitors whose next check-in is overdue as missed, and in-progress
     * check-ins that outlived max_runtime as timed out.
     *
     * @return array{missed: int, timed_out: int}
     */
    public function detect(): array
    {
        return ['missed' => $this->markMissed(), 'timed_out' => $this->markTimedOut()];
    }

    public function pruneCheckIns(): int
    {
        return CronCheckIn::where('created_at', '<', now()->subDays((int) config('fault.cron_check_in_retention_days')))->delete();
    }

    public function nextExpectedAt(CronMonitor $monitor, Carbon $from): ?Carbon
    {
        try {
            if ($monitor->schedule_type === 'crontab' && $monitor->schedule_value) {
                $timezone = new DateTimeZone($monitor->timezone ?: 'UTC');
                $next = (new CronExpression($monitor->schedule_value))->getNextRunDate($from->copy()->setTimezone($timezone), 0, false, $timezone->getName());

                return Carbon::instance($next)->utc();
            }

            if ($monitor->schedule_type === 'interval' && in_array($monitor->schedule_unit, self::UNITS, true) && (int) $monitor->schedule_value > 0) {
                return $from->copy()->add($monitor->schedule_unit, (int) $monitor->schedule_value);
            }
        } catch (Throwable) {
            return null;
        }

        return null;
    }

    private function markMissed(): int
    {
        $count = 0;

        CronMonitor::query()
            ->whereNotNull('next_expected_at')
            ->where('next_expected_at', '<', now())
            ->each(function (CronMonitor $monitor) use (&$count): void {
                if ($monitor->next_expected_at->copy()->addMinutes($monitor->checkin_margin)->isFuture()) {
                    return;
                }

                $previous = $monitor->status;
                $monitor->checkIns()->create(['status' => 'missed']);
                $monitor->status = 'missed';
                $monitor->next_expected_at = $this->nextExpectedAt($monitor, now());
                $monitor->save();

                $this->notifyOnTransition($monitor, $previous);
                $count++;
            });

        return $count;
    }

    private function markTimedOut(): int
    {
        $count = 0;

        CronCheckIn::query()
            ->where('status', 'in_progress')
            ->with('monitor')
            ->each(function (CronCheckIn $checkIn) use (&$count): void {
                $monitor = $checkIn->monitor;

                if ($checkIn->created_at->copy()->addMinutes($monitor->max_runtime)->isFuture()) {
                    return;
                }

                $checkIn->update(['status' => 'timeout']);
                $previous = $monitor->status;
                $monitor->update(['status' => 'error']);

                $this->notifyOnTransition($monitor, $previous);
                $count++;
            });

        return $count;
    }

    /**
     * @param  array<string, mixed>|null  $config
     */
    private function resolveMonitor(FaultProject $project, string $slug, ?array $config): ?CronMonitor
    {
        $monitor = CronMonitor::where('fault_project_id', $project->id)->where('slug', $slug)->first();

        if ($monitor === null) {
            if (CronMonitor::where('fault_project_id', $project->id)->count() >= (int) config('fault.max_cron_monitors_per_project')) {
                return null;
            }

            $monitor = new CronMonitor(['fault_project_id' => $project->id, 'slug' => $slug, 'name' => $slug, 'status' => 'unknown']);
        }

        if ($config !== null) {
            $monitor->fill($this->configAttributes($config));
        }

        $monitor->save();

        return $monitor;
    }

    /**
     * @param  array<string, mixed>  $config
     * @return array<string, mixed>
     */
    private function configAttributes(array $config): array
    {
        $attributes = [];
        $schedule = is_array($config['schedule'] ?? null) ? $config['schedule'] : [];

        if (($schedule['type'] ?? null) === 'crontab' && is_string($schedule['value'] ?? null) && CronExpression::isValidExpression($schedule['value'])) {
            $attributes += ['schedule_type' => 'crontab', 'schedule_value' => $schedule['value'], 'schedule_unit' => null];
        } elseif (($schedule['type'] ?? null) === 'interval' && is_numeric($schedule['value'] ?? null) && (int) $schedule['value'] > 0 && in_array($schedule['unit'] ?? null, self::UNITS, true)) {
            $attributes += ['schedule_type' => 'interval', 'schedule_value' => (string) (int) $schedule['value'], 'schedule_unit' => $schedule['unit']];
        }

        foreach (['checkin_margin', 'max_runtime'] as $key) {
            if (is_numeric($config[$key] ?? null) && (int) $config[$key] > 0) {
                $attributes[$key] = min((int) $config[$key], 525600);
            }
        }

        $timezone = $config['timezone'] ?? null;

        if (is_string($timezone) && in_array($timezone, DateTimeZone::listIdentifiers(), true)) {
            $attributes['timezone'] = $timezone;
        }

        return $attributes;
    }

    private function notifyOnTransition(CronMonitor $monitor, string $previous): void
    {
        $current = $monitor->status;

        if ($current === $previous || ! in_array($current, ['error', 'missed', 'ok'], true)) {
            return;
        }

        if ($current === 'ok' && ! in_array($previous, ['error', 'missed'], true)) {
            return;
        }

        $organization = $monitor->project()->first()?->organization;

        if ($organization === null) {
            return;
        }

        Notification::send($organization->users, new CronMonitorStatusChanged($monitor, $previous, $current));
    }
}
