<?php

namespace App\Enums;

enum MonitorStatus: string
{
    case Up = 'up';
    case Down = 'down';
    case Unknown = 'unknown';

    public function label(): string
    {
        return match ($this) {
            self::Up => 'Up',
            self::Down => 'Down',
            self::Unknown => 'Unknown',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::Up => 'green',
            self::Down => 'red',
            self::Unknown => 'gray',
        };
    }
}
