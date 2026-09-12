<?php

namespace App\Enums;

use BladeUI\Icons\Svg;

enum MonitorType: string
{
    case Http = 'http';
    case Ping = 'ping';
    case LaravelHealth = 'laravel_health';

    public function label(): string
    {
        return match ($this) {
            self::Http => 'HTTP(S)',
            self::Ping => 'Ping',
            self::LaravelHealth => 'Laravel Health',
        };
    }

    public function icon(string $size = '4'): Svg
    {
        $classes = "w-{$size} h-{$size}";

        return match ($this) {
            self::Http => svg('lucide-globe', ['class' => $classes]),
            self::Ping => svg('lucide-radio', ['class' => $classes]),
            self::LaravelHealth => svg('devicon-laravel', ['class' => $classes, 'style' => 'color:#F05340;']),
        };
    }
}
