<?php

namespace App\Ai\Agents;

use App\Enums\TriageCategory;
use App\Enums\TriagePriority;
use Laravel\Ai\Attributes\MaxTokens;
use Laravel\Ai\Attributes\Temperature;
use Laravel\Ai\Contracts\Agent;
use Laravel\Ai\Promptable;

#[MaxTokens(60)]
#[Temperature(0)]
final class IssueTriageAgent implements Agent
{
    use Promptable;

    public function instructions(): string
    {
        $categories = implode(', ', array_column(TriageCategory::cases(), 'value'));
        $priorities = implode(', ', array_column(TriagePriority::cases(), 'value'));

        return 'Classify the error. Reply with only JSON: {"category": "...", "priority": "..."}. '
            ."category is one of: {$categories}. priority is one of: {$priorities}.";
    }
}
