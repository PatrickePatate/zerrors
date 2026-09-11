<?php

namespace App\Livewire;

use App\Enums\NotificationChannelType;
use App\Enums\NotificationRuleTrigger;
use App\Models\FaultProject;
use App\Models\NotificationChannel;
use App\Models\Organization;
use App\Support\Slack\SlackAppClient;
use Livewire\Component;

class NotificationChannelManager extends Component
{
    public Organization $organization;

    public FaultProject $project;

    public string $newChannelType = 'slack';

    public string $newSlackChannelId = '';

    public string $newBotToken = '';

    public string $newChatId = '';

    public string $newEmail = '';

    /** @var array<int, string> */
    public array $thresholdInputs = [];

    public function mount(Organization $organization, FaultProject $project): void
    {
        abort_unless($project->organization_id === $organization->id, 404);
        abort_unless(in_array($organization->roleFor(auth()->user()), ['owner', 'admin'], true), 403);

        $this->organization = $organization;
        $this->project = $project;

        foreach ($project->notificationChannels as $channel) {
            $rule = $channel->rules->firstWhere('trigger', NotificationRuleTrigger::OccurrenceThreshold);
            $this->thresholdInputs[$channel->id] = $rule ? implode(', ', $rule->thresholds ?? []) : '';
        }
    }

    public function addChannel(): void
    {
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
            default => abort(422),
        };

        $this->project->notificationChannels()->create([
            'type' => $data['type'],
            'name' => $data['name'],
            'config' => $data['config'],
            'enabled' => true,
        ]);

        $this->reset(['newSlackChannelId', 'newBotToken', 'newChatId', 'newEmail']);
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    public function slackChannels(): array
    {
        if (empty($this->organization->slack_bot_token)) {
            return [];
        }

        return app(SlackAppClient::class)->listChannels($this->organization->slack_bot_token);
    }

    public function deleteChannel(int $channelId): void
    {
        $this->findChannel($channelId)->delete();
        unset($this->thresholdInputs[$channelId]);
    }

    public function toggleChannel(int $channelId): void
    {
        $channel = $this->findChannel($channelId);
        $channel->update(['enabled' => ! $channel->enabled]);
    }

    public function toggleRule(int $channelId, string $trigger): void
    {
        $trigger = NotificationRuleTrigger::from($trigger);
        $channel = $this->findChannel($channelId);
        $rule = $channel->rules()->firstOrNew(['trigger' => $trigger]);
        $rule->enabled = ! $rule->exists || ! $rule->enabled;
        $rule->save();
    }

    public function updateThresholds(int $channelId): void
    {
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
            ],
            'slackChannels' => $this->newChannelType === 'slack' ? $this->slackChannels() : [],
        ]);
    }
}
