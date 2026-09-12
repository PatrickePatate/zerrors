<?php

namespace App\Console\Commands;

use App\Jobs\RunMonitorCheck;
use App\Models\Monitor;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;

#[Signature('monitoring:dispatch-checks')]
#[Description('Dispatch RunMonitorCheck for every active monitor that is due for a check.')]
class DispatchMonitorChecks extends Command
{
    public function handle(): int
    {
        $dispatched = 0;

        Monitor::query()
            ->where('is_active', true)
            ->where(function ($query) {
                $query->whereNull('last_checked_at')
                    ->orWhereRaw($this->dueSql());
            })
            ->each(function (Monitor $monitor) use (&$dispatched): void {
                RunMonitorCheck::dispatch($monitor);
                $dispatched++;
            });

        $this->info("Dispatched {$dispatched} monitor check(s).");

        return self::SUCCESS;
    }

    /**
     * A driver-appropriate SQL fragment for "last_checked_at is old enough that
     * this monitor's own check_interval_minutes has elapsed since then", so the
     * due/not-due decision is pushed into the query instead of hydrated in PHP.
     */
    private function dueSql(): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "last_checked_at <= datetime('now', '-' || check_interval_minutes || ' minutes')",
            'pgsql' => "last_checked_at <= now() - (check_interval_minutes || ' minutes')::interval",
            default => 'last_checked_at <= DATE_SUB(NOW(), INTERVAL check_interval_minutes MINUTE)',
        };
    }
}
