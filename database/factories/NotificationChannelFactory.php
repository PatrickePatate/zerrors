<?php

namespace Database\Factories;

use App\Enums\NotificationChannelType;
use App\Models\FaultProject;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationChannelFactory extends Factory
{
    public function definition(): array
    {
        return [
            'fault_project_id' => FaultProject::factory(),
            'type' => NotificationChannelType::Slack,
            'name' => 'Slack',
            'config' => ['webhook_url' => 'https://hooks.slack.com/services/'.$this->faker->uuid()],
            'enabled' => true,
        ];
    }

    public function slack(string $webhookUrl): static
    {
        return $this->state([
            'type' => NotificationChannelType::Slack,
            'name' => 'Slack',
            'config' => ['webhook_url' => $webhookUrl],
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
