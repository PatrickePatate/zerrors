<?php

namespace Tests\Feature;

use App\Events\MonitorStatusChanged;
use App\Listeners\SendMonitorStatusChangeNotification;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class MonitorStatusChangedListenerRegistrationTest extends TestCase
{
    /**
     * Listeners in app/Listeners are auto-discovered; registering one again
     * manually makes it run twice and sends every status-change email twice.
     */
    public function test_notification_listener_is_registered_exactly_once(): void
    {
        $listeners = collect(Event::getRawListeners()[MonitorStatusChanged::class] ?? [])
            ->filter(fn ($listener) => is_string($listener) && str_starts_with($listener, SendMonitorStatusChangeNotification::class));

        $this->assertCount(1, $listeners);
    }
}
