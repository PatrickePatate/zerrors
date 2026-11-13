<?php

namespace App\Enums;

enum TriagePriority: string
{
    case Critical = 'critical';
    case High = 'high';
    case Medium = 'medium';
    case Low = 'low';

    public function label(): string
    {
        return ucfirst($this->value);
    }

    public function color(): string
    {
        return match ($this) {
            self::Critical => 'red',
            self::High => 'amber',
            self::Medium => 'blue',
            self::Low => 'gray',
        };
    }
}
