<?php

namespace App\Support\Fault;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Models\FaultEvent;
use App\Models\FaultIssue;
use App\Models\NotificationChannel;
use App\Models\NotificationRule;
use App\Notifications\Fault\IssueAlertNotification;
use App\Notifications\Fault\IssueCreatedNotification;
use App\Notifications\Fault\IssueRegressedNotification;
use App\Support\Net\OutboundUrlGuard;
use App\Support\Slack\SlackAppClient;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Throwable;

class IssueAlertNotifier
{
    public function __construct(protected SlackAppClient $slack) {}

    /**
     * Notify for a single processed event. A channel can match several
     * trigger rules for the same event (e.g. "new issue" and "every event");
     * it is only ever sent once, using the most specific matching label.
     *
     * A project email channel matching this event takes priority over the
     * organization-wide alert for the same address, so someone who is both
     * an org member and configured on a project channel isn't emailed twice.
     */
    public function notify(FaultIssue $issue, bool $wasNew, bool $isRegression): void
    {
        $organization = $issue->project->organization;

        $triggers = [];

        if ($wasNew) {
            $triggers[] = [NotificationRuleTrigger::NewIssue, 'New issue'];
        } elseif ($isRegression) {
            $triggers[] = [NotificationRuleTrigger::Regression, 'Issue regressed'];
        }

        $triggers[] = [NotificationRuleTrigger::EveryEvent, 'New occurrence'];
        $triggers[] = [NotificationRuleTrigger::OccurrenceThreshold, "Occurrence #{$issue->times_seen}"];

        $channels = $this->enabledChannels($issue);
        $cooldown = (int) config('fault.alert_cooldown_seconds', 60);

        $matched = [];

        foreach ($triggers as [$trigger, $label]) {
            foreach ($this->matchingChannels($channels, $issue, $trigger) as $channel) {
                if (isset($matched[$channel->id])) {
                    continue;
                }

                // Only the high-volume "every event" trigger is throttled; when it
                // is throttled the channel stays unclaimed so a later, exact
                // trigger (an occurrence threshold) can still notify.
                if ($trigger === NotificationRuleTrigger::EveryEvent && $cooldown > 0
                    && ! Cache::add("fault:alert-cooldown:{$channel->id}:{$issue->id}", 1, $cooldown)) {
                    continue;
                }

                $matched[$channel->id] = [$channel, $label];
            }
        }

        foreach ($this->spikeChannels($channels, $issue) as $channel) {
            $matched[$channel->id] ??= [$channel, 'Spike in events'];
        }

        if ($organization->alerts_enabled) {
            $alreadyNotifiedEmails = collect($matched)
                ->map(fn ($pair) => $pair[0])
                ->filter(fn (NotificationChannel $channel) => $channel->type === NotificationChannelType::Email)
                ->map(fn (NotificationChannel $channel) => Str::lower($channel->config['email']));

            $recipients = $organization->users->reject(
                fn ($user) => $alreadyNotifiedEmails->contains(Str::lower($user->email))
            );

            if ($wasNew) {
                Notification::send($recipients, new IssueCreatedNotification($issue));
            } elseif ($isRegression) {
                Notification::send($recipients, new IssueRegressedNotification($issue));
            }
        }

        foreach ($matched as [$channel, $label]) {
            $this->send($channel, $issue, $label);
        }
    }

    /**
     * The project's enabled channels with their enabled rules, loaded once
     * per notification rather than once per trigger type.
     *
     * @return Collection<int, NotificationChannel>
     */
    protected function enabledChannels(FaultIssue $issue): Collection
    {
        return $issue->project->notificationChannels()
            ->where('enabled', true)
            ->with(['rules' => fn ($q) => $q->where('enabled', true)])
            ->get();
    }

    /**
     * @param  Collection<int, NotificationChannel>  $channels
     * @return Collection<int, NotificationChannel>
     */
    protected function matchingChannels(Collection $channels, FaultIssue $issue, NotificationRuleTrigger $trigger): Collection
    {
        return $channels->filter(fn (NotificationChannel $channel) => $channel->rules->contains(
            fn ($rule) => $rule->trigger === $trigger && $rule->matchesOccurrence($issue)
        ));
    }

    protected function send(NotificationChannel $channel, FaultIssue $issue, string $label): void
    {
        match ($channel->type) {
            NotificationChannelType::Slack => $this->postSlack($channel, $issue, $label),
            NotificationChannelType::Telegram => $this->postTelegram($channel->config['bot_token'], $channel->config['chat_id'], $issue, $label),
            NotificationChannelType::Email => Notification::route('mail', $channel->config['email'])->notify(new IssueAlertNotification($issue, $label)),
            NotificationChannelType::Discord => $this->postDiscord($channel->config['webhook_url'] ?? '', $issue, $label),
            NotificationChannelType::Webhook => $this->postWebhook($channel->config['url'] ?? '', $channel->config['secret'] ?? null, $issue, $label),
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

    /**
     * Channels whose spike rule fires for this issue. The (cheap) event
     * counts only run when a spike rule exists, behind a short per-issue
     * lock so a burst doesn't run the query for every event, and each
     * channel is alerted at most once per issue per hour.
     *
     * @param  Collection<int, NotificationChannel>  $channels
     * @return Collection<int, NotificationChannel>
     */
    protected function spikeChannels(Collection $channels, FaultIssue $issue): Collection
    {
        $withSpikeRule = $channels->filter(fn (NotificationChannel $channel) => $channel->rules->contains(
            fn (NotificationRule $rule) => $rule->trigger === NotificationRuleTrigger::Spike
        ));

        if ($withSpikeRule->isEmpty() || ! Cache::add("fault:spike-eval:{$issue->id}", 1, 30)) {
            return collect();
        }

        $now = now();
        $counts = [];

        return $withSpikeRule->filter(function (NotificationChannel $channel) use ($issue, $now, &$counts) {
            $rule = $channel->rules->firstWhere('trigger', NotificationRuleTrigger::Spike);
            $settings = $rule->spikeSettings();
            $minutes = $settings['window_minutes'];

            $counts[$minutes] ??= $this->spikeCounts($issue, $minutes, $now);
            [$current, $baseline] = $counts[$minutes];

            $averagePerWindow = $baseline / (1440 / $minutes);

            if ($current < $settings['min_count'] || $current < $settings['multiplier'] * $averagePerWindow) {
                return false;
            }

            return Cache::add("fault:spike-alert:{$channel->id}:{$issue->id}", 1, 3600);
        });
    }

    /**
     * @return array{0: int, 1: int} Events in the last window, events in the 24h before it.
     */
    protected function spikeCounts(FaultIssue $issue, int $minutes, Carbon $now): array
    {
        $windowStart = $now->copy()->subMinutes($minutes);

        $current = FaultEvent::query()
            ->where('fault_issue_id', $issue->id)
            ->where('occurred_at', '>=', $windowStart)
            ->count();

        $baseline = FaultEvent::query()
            ->where('fault_issue_id', $issue->id)
            ->where('occurred_at', '>=', $windowStart->copy()->subDay())
            ->where('occurred_at', '<', $windowStart)
            ->count();

        return [$current, $baseline];
    }

    protected function postDiscord(string $webhookUrl, FaultIssue $issue, string $label): void
    {
        $parts = parse_url($webhookUrl);
        $host = strtolower((string) ($parts['host'] ?? ''));

        if (($parts['scheme'] ?? null) !== 'https' || ! preg_match('/(^|\.)(discord|discordapp)\.com$/', $host)) {
            return;
        }

        $link = route('organizations.issues.show', [$issue->project->organization, $issue->project, $issue]);

        try {
            Http::timeout(5)->withoutRedirecting()->post($webhookUrl, [
                'content' => "**{$label}** in {$issue->project->name}: {$issue->title}\n{$link}",
            ]);
        } catch (Throwable $e) {
            report($e);
        }
    }

    protected function postWebhook(string $url, ?string $secret, FaultIssue $issue, string $label): void
    {
        try {
            app(OutboundUrlGuard::class)->assertAllowedUrl($url);

            $project = $issue->project;
            $body = json_encode([
                'event' => $label,
                'issue' => [
                    'id' => $issue->id,
                    'title' => $issue->title,
                    'level' => $issue->level,
                    'status' => $issue->status,
                    'times_seen' => $issue->times_seen,
                    'url' => route('organizations.issues.show', [$project->organization, $project, $issue]),
                ],
                'project' => ['name' => $project->name, 'slug' => $project->slug],
                'organization' => ['slug' => $project->organization->slug],
                'timestamp' => now()->toIso8601String(),
            ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);

            $headers = $secret ? ['X-Zerrors-Signature' => 'sha256='.hash_hmac('sha256', $body, $secret)] : [];

            Http::timeout(5)->withoutRedirecting()->withHeaders($headers)->withBody($body, 'application/json')->post($url);
        } catch (Throwable $e) {
            report($e);
        }
    }
}
