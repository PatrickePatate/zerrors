<?php

namespace App\Support\Fault;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Models\FaultIssue;
use App\Models\NotificationChannel;
use App\Notifications\Fault\IssueAlertNotification;
use App\Notifications\Fault\IssueCreatedNotification;
use App\Notifications\Fault\IssueRegressedNotification;
use App\Support\Slack\SlackAppClient;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Throwable;

class IssueAlertNotifier
{
    public function __construct(protected SlackAppClient $slack) {}

    /**
     * Notify for a single processed event. A channel can match several
     * trigger rules for the same event (e.g. "new issue" and "every event");
     * it is only ever sent once, using the most specific matching label.
     */
    public function notify(FaultIssue $issue, bool $wasNew, bool $isRegression): void
    {
        $organization = $issue->project->organization;

        if ($organization->alerts_enabled) {
            if ($wasNew) {
                Notification::send($organization->users, new IssueCreatedNotification($issue));
            } elseif ($isRegression) {
                Notification::send($organization->users, new IssueRegressedNotification($issue));
            }
        }

        $triggers = [];

        if ($wasNew) {
            $triggers[] = [NotificationRuleTrigger::NewIssue, 'New issue'];
        } elseif ($isRegression) {
            $triggers[] = [NotificationRuleTrigger::Regression, 'Issue regressed'];
        }

        $triggers[] = [NotificationRuleTrigger::EveryEvent, 'New occurrence'];
        $triggers[] = [NotificationRuleTrigger::OccurrenceThreshold, "Occurrence #{$issue->times_seen}"];

        $matched = [];

        foreach ($triggers as [$trigger, $label]) {
            foreach ($this->matchingChannels($issue, $trigger) as $channel) {
                $matched[$channel->id] ??= [$channel, $label];
            }
        }

        foreach ($matched as [$channel, $label]) {
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
            NotificationChannelType::Slack => $this->postSlack($channel, $issue, $label),
            NotificationChannelType::Telegram => $this->postTelegram($channel->config['bot_token'], $channel->config['chat_id'], $issue, $label),
            NotificationChannelType::Email => Notification::route('mail', $channel->config['email'])->notify(new IssueAlertNotification($issue, $label)),
        };
    }

    protected function postSlack(NotificationChannel $channel, FaultIssue $issue, string $label): void
    {
        $botToken = $issue->project->organization->slack_bot_token;

        if (empty($botToken)) {
            Log::debug('Skipping Slack alert: organization has no Slack app connected.', ['organization_id' => $issue->project->organization_id]);

            return;
        }

        $link = route('organizations.issues.show', [$issue->project->organization, $issue->project, $issue]);

        try {
            $this->slack->postMessage($botToken, $channel->config['channel_id'], "*{$label}* in {$issue->project->name}: {$issue->title}\n{$link}");
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
