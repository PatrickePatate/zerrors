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
    case JavaScript = 'javascript';
    case React = 'react';
    case Vue = 'vue';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Php => 'PHP',
            self::Laravel => 'Laravel',
            self::Symfony => 'Symfony',
            self::WordPress => 'WordPress',
            self::NodeJs => 'Node.js',
            self::JavaScript => 'JavaScript (browser)',
            self::React => 'React',
            self::Vue => 'Vue',
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
            self::JavaScript => svg('devicon-javascript', ['class' => $classes]),
            self::React => svg('lucide-atom', ['class' => $classes, 'style' => 'color:#00B8D9;']),
            self::Vue => svg('devicon-vuejs', ['class' => $classes]),
            self::Other => svg('lucide-earth', ['class' => $classes]),
        };
    }

    /**
     * Whether events from this platform carry minified browser/Node JavaScript
     * frames that can be symbolicated with uploaded source maps.
     */
    public function isJavaScript(): bool
    {
        return in_array($this, [self::JavaScript, self::React, self::Vue, self::NodeJs], true);
    }
}
