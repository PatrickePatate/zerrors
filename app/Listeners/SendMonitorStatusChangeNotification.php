<?php

namespace App\Listeners;

use App\Enums\MonitorStatus;
use App\Events\MonitorStatusChanged as MonitorStatusChangedEvent;
use App\Notifications\MonitorStatusChanged as MonitorStatusChangedNotification;
use Illuminate\Contracts\Queue\ShouldQueue;

class SendMonitorStatusChangeNotification implements ShouldQueue
{
    public function handle(MonitorStatusChangedEvent $event): void
    {
        $transitionsToNotify = [
            MonitorStatus::Up->value.'-'.MonitorStatus::Down->value,
            MonitorStatus::Down->value.'-'.MonitorStatus::Up->value,
        ];

        $transition = $event->previousStatus->value.'-'.$event->newStatus->value;

        if (! in_array($transition, $transitionsToNotify, true)) {
            return;
        }

        $organization = $event->monitor->organization;

        $recipients = $organization->users()
            ->wherePivotIn('role', ['owner', 'admin'])
            ->get();

        foreach ($recipients as $recipient) {
            $recipient->notify(new MonitorStatusChangedNotification(
                $event->monitor,
                $event->previousStatus,
                $event->newStatus,
            ));
        }
    }
}
