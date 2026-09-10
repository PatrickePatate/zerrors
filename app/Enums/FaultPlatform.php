<?php

namespace App\Enums;

use BladeUI\Icons\Svg;

enum FaultPlatform: string
{
    case Php = 'php';
    case Laravel = 'laravel';
    case Symfony = 'symfony';
    case WordPress = 'wordpress';
    case NodeJs = 'nodejs';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Php => 'PHP',
            self::Laravel => 'Laravel',
            self::Symfony => 'Symfony',
            self::WordPress => 'WordPress',
            self::NodeJs => 'Node.js',
            self::Other => 'Other',
        };
    }

    public function icon(string $size = '4'): Svg
    {
        $classes = "w-{$size} h-{$size}";

        return match ($this) {
            self::Php => svg('devicon-php', ['class' => $classes]),
            self::Laravel => svg('devicon-laravel', ['class' => $classes, 'style' => 'color:#F05340;']),
            self::Symfony => svg('icon-symfony', ['class' => $classes]),
            self::WordPress => svg('devicon-wordpress', ['class' => $classes]),
            self::NodeJs => svg('devicon-nodejs', ['class' => $classes]),
            self::Other => svg('lucide-earth', ['class' => $classes]),
        };
    }
}
