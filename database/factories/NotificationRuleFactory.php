<?php

namespace Database\Factories;

use App\Enums\NotificationRuleTrigger;
use App\Models\NotificationChannel;
use Illuminate\Database\Eloquent\Factories\Factory;

class NotificationRuleFactory extends Factory
{
    public function definition(): array
    {
        return [
            'notification_channel_id' => NotificationChannel::factory(),
            'trigger' => NotificationRuleTrigger::NewIssue,
            'thresholds' => null,
            'enabled' => true,
        ];
    }

    public function trigger(NotificationRuleTrigger $trigger): static
    {
        return $this->state(['trigger' => $trigger]);
    }

    /**
     * @param  array<int, int>  $thresholds
     */
    public function thresholds(array $thresholds): static
    {
        return $this->state([
            'trigger' => NotificationRuleTrigger::OccurrenceThreshold,
            'thresholds' => $thresholds,
        ]);
    }

    /**
     * @param  array{window_minutes?: int, multiplier?: int, min_count?: int}  $settings
     */
    public function spike(array $settings = []): static
    {
        return $this->state([
            'trigger' => NotificationRuleTrigger::Spike,
            'thresholds' => $settings ?: null,
        ]);
    }
}
