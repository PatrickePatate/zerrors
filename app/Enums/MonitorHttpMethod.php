<?php

namespace App\Enums;

enum MonitorHttpMethod: string
{
    case Get = 'GET';
    case Post = 'POST';

    public function label(): string
    {
        return match ($this) {
            self::Get => 'GET',
            self::Post => 'POST',
        };
    }
}
