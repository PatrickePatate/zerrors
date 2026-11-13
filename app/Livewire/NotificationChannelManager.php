<?php

namespace App\Livewire;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Livewire\Concerns\AuthorizesOrganizationAccess;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\NotificationRule;
use App\Models\Organization;
use App\Support\Net\OutboundUrlGuard;
use App\Support\Slack\SlackAppClient;
use InvalidArgumentException;
use Livewire\Attributes\Locked;
use Livewire\Component;

class NotificationChannelManager extends Component
{
    use AuthorizesOrganizationAccess;

    #[Locked]
    public Organization $organization;

    #[Locked]
    public FaultProject $project;

    public string $newChannelType = 'slack';

    public string $newSlackChannelId = '';

    public string $newBotToken = '';

    public string $newChatId = '';

    public string $newEmail = '';

    public string $newDiscordWebhookUrl = '';

    public string $newWebhookUrl = '';

    public string $newWebhookSecret = '';

    /** @var array<int, array{window_minutes: int|string, multiplier: int|string, min_count: int|string}> */
    public array $spikeInputs = [];

    /** @var array<int, string> */
    public array $thresholdInputs = [];

    public function mount(Organization $organization, FaultProject $project): void
    {
        abort_unless($project->organization_id === $organization->id, 404);
        $this->authorizeManage($organization);

        $this->organization = $organization;
        $this->project = $project;

        foreach ($project->notificationChannels as $channel) {
            $rule = $channel->rules->firstWhere('trigger', NotificationRuleTrigger::OccurrenceThreshold);
            $this->thresholdInputs[$channel->id] = $rule ? implode(', ', $rule->thresholds ?? []) : '';
            $this->spikeInputs[$channel->id] = ($channel->rules->firstWhere('trigger', NotificationRuleTrigger::Spike) ?? new NotificationRule(['trigger' => NotificationRuleTrigger::Spike]))->spikeSettings();
        }
    }

    public function addChannel(): void
    {
        $this->authorizeManage($this->organization);

        $data = match ($this->newChannelType) {
            'slack' => $this->validate([
                'newSlackChannelId' => ['required', 'string'],
            ]) + [
                'type' => NotificationChannelType::Slack,
                'name' => 'Slack',
                'config' => [
                    'channel_id' => $this->newSlackChannelId,
                    'channel_name' => collect($this->slackChannels())->firstWhere('id', $this->newSlackChannelId)['name'] ?? $this->newSlackChannelId,
                ],
            ],
            'telegram' => $this->validate([
                'newBotToken' => ['required', 'string', 'max:255'],
                'newChatId' => ['required', 'string', 'max:255'],
            ]) + [
                'type' => NotificationChannelType::Telegram,
                'name' => 'Telegram',
                'config' => ['bot_token' => $this->newBotToken, 'chat_id' => $this->newChatId],
            ],
            'email' => $this->validate([
                'newEmail' => ['required', 'email', 'max:255'],
            ]) + [
                'type' => NotificationChannelType::Email,
                'name' => 'Email',
                'config' => ['email' => $this->newEmail],
            ],
            'discord' => $this->validate([
                'newDiscordWebhookUrl' => ['required', 'url:https', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                    $host = strtolower((string) parse_url((string) $value, PHP_URL_HOST));

                    if (! in_array($host, ['discord.com', 'discordapp.com'], true) && ! preg_match('/\.(discord|discordapp)\.com$/', $host)) {
                        $fail('The Discord webhook URL must be on discord.com or discordapp.com.');
                    }
                }],
            ]) + [
                'type' => NotificationChannelType::Discord,
                'name' => 'Discord',
                'config' => ['webhook_url' => $this->newDiscordWebhookUrl],
            ],
            'webhook' => $this->validate([
                'newWebhookUrl' => ['required', 'url:http,https', 'max:500', function (string $attribute, mixed $value, \Closure $fail): void {
                    try {
                        app(OutboundUrlGuard::class)->assertAllowedUrl((string) $value);
                    } catch (InvalidArgumentException $e) {
                        $fail($e->getMessage());
                    }
                }],
                'newWebhookSecret' => ['nullable', 'string', 'max:255'],
            ]) + [
                'type' => NotificationChannelType::Webhook,
                'name' => 'Webhook',
                'config' => ['url' => $this->newWebhookUrl, 'secret' => $this->newWebhookSecret !== '' ? $this->newWebhookSecret : null],
            ],
            default => abort(422),
        };

        $channel = $this->project->notificationChannels()->create([
            'type' => $data['type'],
            'name' => $data['name'],
            'config' => $data['config'],
            'enabled' => true,
        ]);

        $this->spikeInputs[$channel->id] = (new NotificationRule(['trigger' => NotificationRuleTrigger::Spike]))->spikeSettings();

        $this->reset(['newSlackChannelId', 'newBotToken', 'newChatId', 'newEmail', 'newDiscordWebhookUrl', 'newWebhookUrl', 'newWebhookSecret']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function slackChannels(): array
    {
        $this->authorizeManage($this->organization);

        if (empty($this->organization->slack_bot_token)) {
            return [];
        }

        return app(SlackAppClient::class)->listChannels($this->organization->slack_bot_token);
    }

    public function deleteChannel(int $channelId): void
    {
        $this->authorizeManage($this->organization);

        $this->findChannel($channelId)->delete();
        unset($this->thresholdInputs[$channelId]);
    }

    public function toggleChannel(int $channelId): void
    {
        $this->authorizeManage($this->organization);

        $channel = $this->findChannel($channelId);
        $channel->update(['enabled' => ! $channel->enabled]);
    }

    public function toggleRule(int $channelId, string $trigger): void
    {
        $this->authorizeManage($this->organization);

        $trigger = NotificationRuleTrigger::from($trigger);
        $channel = $this->findChannel($channelId);
        $rule = $channel->rules()->firstOrNew(['trigger' => $trigger]);
        $rule->enabled = ! $rule->exists || ! $rule->enabled;
        $rule->save();
    }

    public function updateThresholds(int $channelId): void
    {
        $this->authorizeManage($this->organization);

        $channel = $this->findChannel($channelId);

        $thresholds = collect(explode(',', $this->thresholdInputs[$channelId] ?? ''))
            ->map(fn ($value) => trim($value))
            ->filter(fn ($value) => $value !== '')
            ->map(fn ($value) => (int) $value)
            ->filter(fn ($value) => $value > 0)
            ->unique()
            ->sort()
            ->values()
            ->all();

        $rule = $channel->rules()->firstOrNew(['trigger' => NotificationRuleTrigger::OccurrenceThreshold]);
        $rule->thresholds = $thresholds;
        $rule->enabled = ! empty($thresholds);
        $rule->save();
    }

    public function updateSpikeSettings(int $channelId): void
    {
        $this->authorizeManage($this->organization);

        $channel = $this->findChannel($channelId);
        $input = $this->spikeInputs[$channelId] ?? [];

        $rule = $channel->rules()->firstOrNew(['trigger' => NotificationRuleTrigger::Spike]);
        $rule->thresholds = [
            'window_minutes' => min(1440, max(1, (int) ($input['window_minutes'] ?? 5))),
            'multiplier' => max(1, (int) ($input['multiplier'] ?? 10)),
            'min_count' => max(1, (int) ($input['min_count'] ?? 20)),
        ];
        $rule->save();

        $this->spikeInputs[$channelId] = $rule->spikeSettings();
    }

    protected function findChannel(int $channelId): NotificationChannel
    {
        return $this->project->notificationChannels()->findOrFail($channelId);
    }

    public function render()
    {
        return view('livewire.notification-channel-manager', [
            'channels' => $this->project->notificationChannels()->with('rules')->latest()->get(),
            'triggers' => [
                NotificationRuleTrigger::NewIssue,
                NotificationRuleTrigger::Regression,
                NotificationRuleTrigger::EveryEvent,
                NotificationRuleTrigger::Spike,
            ],
            'slackChannels' => $this->newChannelType === 'slack' ? $this->slackChannels() : [],
        ]);
    }
}
