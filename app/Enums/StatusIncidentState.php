<?php

namespace App\Enums;

enum StatusIncidentState: string
{
    case Investigating = 'investigating';
    case Identified = 'identified';
    case Monitoring = 'monitoring';
    case Resolved = 'resolved';

    public function label(): string
    {
        return ucfirst($this->value);
    }
}
