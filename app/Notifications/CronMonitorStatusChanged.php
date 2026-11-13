<?php

namespace App\Notifications;

use App\Models\CronMonitor;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class CronMonitorStatusChanged extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public CronMonitor $monitor,
        public string $previousStatus,
        public string $newStatus,
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
        $project = $this->monitor->project;
        $recovered = $this->newStatus === 'ok';

        return (new MailMessage)
            ->subject(($recovered ? 'Cron recovered: ' : "Cron {$this->newStatus}: ").$this->monitor->name)
            ->greeting($recovered ? 'A cron job has recovered.' : 'A cron job needs attention.')
            ->line("Cron monitor \"{$this->monitor->name}\" in project \"{$project->name}\" is now {$this->newStatus} (was {$this->previousStatus}).")
            ->action('View cron jobs', route('organizations.crons.index', [$project->organization, $project]));
    }
}
