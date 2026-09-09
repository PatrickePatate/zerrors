<?php

namespace App\Notifications\Fault;

use App\Models\FaultIssue;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Messages\MailMessage;
use Illuminate\Notifications\Notification;

class IssueRegressedNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(public FaultIssue $issue) {}

    /**
     * @return array<int, string>
     */
    public function via(object $notifiable): array
    {
        return ['mail'];
    }

    public function toMail(object $notifiable): MailMessage
    {
        $issue = $this->issue;
        $project = $issue->project;
        $organization = $project->organization;

        return (new MailMessage)
            ->subject("Regression in {$project->name}: {$issue->title}")
            ->line("An issue that was marked **resolved** just happened again in **{$project->name}** ({$organization->name}).")
            ->line("**{$issue->title}**")
            ->when($issue->culprit, fn ($mail) => $mail->line($issue->culprit))
            ->action('View issue', route('organizations.issues.show', [$organization, $project, $issue]))
            ->line('You are receiving this because alert emails are enabled for this organization.');
    }
}
