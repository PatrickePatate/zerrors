<?php

namespace App\Enums;

enum NotificationChannelType: string
{
    case Slack = 'slack';
    case Telegram = 'telegram';
    case Email = 'email';
    case Discord = 'discord';
    case Webhook = 'webhook';

    public function label(): string
    {
        return match ($this) {
            self::Slack => 'Slack',
            self::Telegram => 'Telegram',
            self::Email => 'Email',
            self::Discord => 'Discord',
            self::Webhook => 'Webhook',
        };
    }
}
