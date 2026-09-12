<?php

namespace App\Events;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

class MonitorStatusChanged
{
    use Dispatchable, SerializesModels;

    public function __construct(
        public Monitor $monitor,
        public MonitorStatus $previousStatus,
        public MonitorStatus $newStatus,
    ) {}
}
