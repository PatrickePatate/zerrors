<?php

namespace App\Notifications;

use App\Enums\MonitorStatus;
use App\Models\Monitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class MonitorStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public Monitor $monitor,
        public MonitorStatus $previousStatus,
        public MonitorStatus $newStatus,
    ) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $isDown = $this->newStatus === MonitorStatus::Down;

        $message = (new MailMessage)
            ->subject(($isDown ? '🔴 Down: ' : '🟢 Recovered: ').$this->monitor->name)
            ->greeting($isDown ? 'A monitor just went down.' : 'A monitor has recovered.');

        $message->line("Monitor \"{$this->monitor->name}\" ({$this->monitor->url}) is now **{$this->newStatus->label()}**.");
        $message->line("Previous status: {$this->previousStatus->label()}.");

        return $message->action('View monitor', url("/o/{$this->monitor->organization->slug}/monitoring/{$this->monitor->id}"));
    }
}
