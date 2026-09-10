<?php

namespace App\Enums;

enum NotificationChannelType: string
{
    case Slack = 'slack';
    case Telegram = 'telegram';
    case Email = 'email';

    public function label(): string
    {
        return match ($this) {
            self::Slack => 'Slack',
            self::Telegram => 'Telegram',
            self::Email => 'Email',
        };
    }
}
