<?php

namespace App\Enums;

enum TriageCategory: string
{
    case CodeBug = 'code_bug';
    case Configuration = 'configuration';
    case ExternalDependency = 'external_dependency';
    case Database = 'database';
    case UserInput = 'user_input';
    case Infrastructure = 'infrastructure';
    case Noise = 'noise';

    public function label(): string
    {
        return match ($this) {
            self::CodeBug => 'Code bug',
            self::Configuration => 'Configuration',
            self::ExternalDependency => 'External dependency',
            self::Database => 'Database',
            self::UserInput => 'User input',
            self::Infrastructure => 'Infrastructure',
            self::Noise => 'Noise',
        };
    }

    public function color(): string
    {
        return match ($this) {
            self::CodeBug => 'red',
            self::Configuration => 'amber',
            self::ExternalDependency => 'indigo',
            self::Database => 'blue',
            self::UserInput => 'green',
            self::Infrastructure => 'amber',
            self::Noise => 'gray',
        };
    }
}
