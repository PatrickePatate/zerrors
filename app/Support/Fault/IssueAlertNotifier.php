<?php

namespace App\Support\Fault;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Models\FaultIssue;
use App\Models\NotificationChannel;
use App\Notifications\Fault\IssueAlertNotification;
use App\Notifications\Fault\IssueCreatedNotification;
use App\Notifications\Fault\IssueRegressedNotification;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Notification;
use Throwable;

class IssueAlertNotifier
{
    public function issueCreated(FaultIssue $issue): void
    {
        $this->dispatch($issue, NotificationRuleTrigger::NewIssue, 'New issue', new IssueCreatedNotification($issue));
    }

    public function issueRegressed(FaultIssue $issue): void
    {
        $this->dispatch($issue, NotificationRuleTrigger::Regression, 'Issue regressed', new IssueRegressedNotification($issue));
    }

    /**
     * Called for every processed event, including the one that created or
     * regressed the issue, so "every event" and "occurrence threshold"
     * channel rules see every occurrence. A channel matching both rules for
     * the same occurrence is only notified once.
     */
    public function issueOccurrence(FaultIssue $issue, bool $isFirstOccurrence = false): void
    {
        $matched = [];

        foreach ([
            [NotificationRuleTrigger::EveryEvent, 'New occurrence'],
            [NotificationRuleTrigger::OccurrenceThreshold, "Occurrence #{$issue->times_seen}"],
        ] as [$trigger, $label]) {
            // The threshold-1 milestone is already covered by issueCreated()'s "New issue" alert,
            // so skip it here to avoid double-notifying for the same first occurrence.
            if ($isFirstOccurrence && $trigger === NotificationRuleTrigger::OccurrenceThreshold) {
                continue;
            }

            foreach ($this->matchingChannels($issue, $trigger) as $channel) {
                $matched[$channel->id] ??= [$channel, $label];
            }
        }

        foreach ($matched as [$channel, $label]) {
            $this->send($channel, $issue, $label);
        }
    }

    protected function dispatch(FaultIssue $issue, NotificationRuleTrigger $trigger, string $label, ?object $orgNotification = null): void
    {
        $organization = $issue->project->organization;

        if ($organization->alerts_enabled && $orgNotification) {
            Notification::send($organization->users, $orgNotification);
        }

        foreach ($this->matchingChannels($issue, $trigger) as $channel) {
            $this->send($channel, $issue, $label);
        }
    }

    /**
     * @return Collection<int, NotificationChannel>
     */
    protected function matchingChannels(FaultIssue $issue, NotificationRuleTrigger $trigger): Collection
    {
        return $issue->project->notificationChannels()
            ->where('enabled', true)
            ->with(['rules' => fn ($q) => $q->where('enabled', true)->where('trigger', $trigger->value)])
            ->get()
            ->filter(fn (NotificationChannel $channel) => $channel->rules->contains(
                fn ($rule) => $rule->matchesOccurrence($issue)
            ));
    }

    protected function send(NotificationChannel $channel, FaultIssue $issue, string $label): void
    {
        match ($channel->type) {
            NotificationChannelType::Slack => $this->postSlack($channel->config['webhook_url'], $issue, $label),
            NotificationChannelType::Telegram => $this->postTelegram($channel->config['bot_token'], $channel->config['chat_id'], $issue, $label),
            NotificationChannelType::Email => Notification::route('mail', $channel->config['email'])->notify(new IssueAlertNotification($issue, $label)),
        };
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

    protected function postTelegram(string $botToken, string $chatId, FaultIssue $issue, string $label): void
    {
        $link = route('organizations.issues.show', [$issue->project->organization, $issue->project, $issue]);

        try {
            Http::timeout(5)->post("https://api.telegram.org/bot{$botToken}/sendMessage", [
                'chat_id' => $chatId,
                'text' => "{$label} in {$issue->project->name}: {$issue->title}\n{$link}",
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
