<?php

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class NotificationChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'type' => NotificationChannelType::Slack,
            'name' => 'Slack',
            'config' => ['channel_id' => 'C'.Str::upper(Str::random(8)), 'channel_name' => 'alerts'],
            'enabled' => true,
        ];
    }

    public function slack(string $channelId, string $channelName = 'alerts'): static
    {
        return $this->state([
            'type' => NotificationChannelType::Slack,
            'name' => 'Slack',
            'config' => ['channel_id' => $channelId, 'channel_name' => $channelName],
        ]);
    }

    public function telegram(string $botToken, string $chatId): static
    {
        return $this->state([
            'type' => NotificationChannelType::Telegram,
            'name' => 'Telegram',
            'config' => ['bot_token' => $botToken, 'chat_id' => $chatId],
        ]);
    }

    public function email(string $email): static
    {
        return $this->state([
            'type' => NotificationChannelType::Email,
            'name' => 'Email',
            'config' => ['email' => $email],
        ]);
    }
}
