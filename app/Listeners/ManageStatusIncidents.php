<?php

namespace App\Listeners;

use App\Enums\MonitorStatus;
use App\Enums\StatusIncidentState;
use App\Events\MonitorStatusChanged;
use App\Models\StatusIncident;
use App\Support\Status\StatusPageData;
use Illuminate\Contracts\Queue\ShouldQueue;

class ManageStatusIncidents implements ShouldQueue
{
    public function handle(MonitorStatusChanged $event): void
    {
        $monitor = $event->monitor;

        if (! in_array($event->newStatus, [MonitorStatus::Down, MonitorStatus::Up], true)) {
            return;
        }

        foreach ($monitor->statusPages()->get() as $page) {
            $name = $page->pivot->display_name ?: $monitor->name;

            $open = StatusIncident::query()
                ->where('status_page_id', $page->id)
                ->where('monitor_id', $monitor->id)
                ->where('auto', true)
                ->whereNull('resolved_at')
                ->first();

            if ($event->newStatus === MonitorStatus::Down && $open === null) {
                $incident = $page->incidents()->create([
                    'monitor_id' => $monitor->id,
                    'title' => "{$name} is experiencing issues",
                    'started_at' => now(),
                    'auto' => true,
                ]);
                $incident->updates()->create([
                    'status' => StatusIncidentState::Investigating,
                    'body' => 'Our monitoring detected a problem. We are investigating.',
                ]);
            } elseif ($event->newStatus === MonitorStatus::Up && $open !== null) {
                $open->update(['resolved_at' => now()]);
                $open->updates()->create([
                    'status' => StatusIncidentState::Resolved,
                    'body' => 'The service has recovered and is operating normally.',
                ]);
            }

            StatusPageData::forget($page);
        }
    }
}
