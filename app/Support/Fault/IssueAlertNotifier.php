<?php

namespace App\Support\Fault;

use App\Models\FaultIssue;
use App\Models\FaultProject;
use App\Notifications\Fault\IssueCreatedNotification;
use App\Notifications\Fault\IssueRegressedNotification;
use Closure;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Throwable;

class IssueAlertNotifier
{
    public function issueCreated(FaultIssue $issue): void
    {
        $this->notify($issue, fn () => new IssueCreatedNotification($issue), 'New issue');
    }

    public function issueRegressed(FaultIssue $issue): void
    {
        $this->notify($issue, fn () => new IssueRegressedNotification($issue), 'Issue regressed');
    }

    protected function notify(FaultIssue $issue, Closure $notification, string $label): void
    {
        $organization = $issue->project->organization;
        $project = $issue->project;

        if ($organization->alerts_enabled) {
            Notification::send($organization->users, $notification());
        }

        if ($project->hasEmailAlertConfigured()) {
            Notification::route('mail', $project->notify_email)->notify($notification());
        }

        if ($project->hasSlackConfigured()) {
            $this->postSlack($project->slack_webhook_url, $issue, $label);
        }

        if ($project->hasTelegramConfigured()) {
            $this->postTelegram($project, $issue, $label);
        }
    }

    protected function postSlack(string $url, FaultIssue $issue, string $label): void
    {
        $link = route('organizations.issues.show', [$issue->project->organization, $issue->project, $issue]);

        try {
            // Slack- and Discord-compatible payload shape (both read a top-level "text" field).
            Http::timeout(5)->post($url, [
                'text' => "*{$label}* in {$issue->project->name}: {$issue->title}\n{$link}",
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function postTelegram(FaultProject $project, FaultIssue $issue, string $label): void
    {
        $link = route('organizations.issues.show', [$project->organization, $project, $issue]);

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$project->telegram_bot_token}/sendMessage", [
                'chat_id' => $project->telegram_chat_id,
                'text' => "{$label} in {$project->name}: {$issue->title}\n{$link}",
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
