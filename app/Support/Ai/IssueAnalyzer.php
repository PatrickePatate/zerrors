<?php

namespace App\Support\Ai;

use App\Ai\Agents\IssueAnalystAgent;
use App\Models\FaultIssue;
use App\Models\Organization;
use Illuminate\Support\Facades\Config;
use RuntimeException;

class IssueAnalyzer
{
    /**
     * Ask the organization's configured AI provider to suggest a cause and fix
     * for an issue, store the result on it, and return the analysis text.
     */
    public function analyze(FaultIssue $issue): string
    {
        $organization = $issue->project->organization;

        if (! $organization->hasAiConfigured()) {
            throw new RuntimeException('No AI provider is configured for this organization.');
        }

        $this->useOrganizationCredentials($organization);

        $response = (new IssueAnalystAgent)->prompt(
            $this->buildPrompt($issue),
            provider: $organization->ai_provider,
            model: $organization->ai_model ?: null,
        );

        $issue->forceFill([
            'ai_analysis' => $response->text,
            'ai_analyzed_at' => now(),
        ])->save();

        return $response->text;
    }

    /**
     * Point the driver's config at the organization's own key for this process,
     * rather than requiring a global .env credential per provider.
     */
    protected function useOrganizationCredentials(Organization $organization): void
    {
        Config::set("ai.providers.{$organization->ai_provider}", [
            'driver' => $organization->ai_provider,
            'key' => $organization->ai_api_key,
        ]);
    }

    protected function buildPrompt(FaultIssue $issue): string
    {
        $event = $issue->events()->latest('occurred_at')->first();
        $exception = $event?->exception['values'][0] ?? null;

        $lines = [
            'You will be given a raw exception stacktrace and some context captured alongside it. Your job is to:',
            '1. Identify the most likely root cause (one or two sentences, no hedging unless genuinely ambiguous — if ambiguous, name the top 2 candidates).',
            '2. Point to the exact file/line/function where the fault likely originates.',
            "3. Give a brief, concrete fix — a code change, a config check, or a next diagnostic step if you can't be certain from the trace alone.",
            'Keep the whole response under 150 words. No preamble, no restating the stacktrace back to me.',
            "Exception type: {$issue->type}",
            "Message: {$issue->title}",
            'Culprit: '.($issue->culprit ?: 'unknown'),
            'Level: '.$issue->level,
            'Times seen: '.$issue->times_seen,
        ];

        if ($event?->environment) {
            $lines[] = "Environment: {$event->environment}";
        }

        if ($event?->release) {
            $lines[] = "Release: {$event->release}";
        }

        if ($event?->tags) {
            $lines[] = 'Tags: '.collect($event->tags)->map(fn ($v, $k) => "{$k}={$v}")->implode(', ');
        }

        if ($exception) {
            $lines[] = '';
            $lines[] = 'Stack trace (top of stack first):';
            $lines[] = $this->formatFrames($exception['stacktrace']['frames'] ?? []);
        }

        return implode("\n", $lines);
    }

    /**
     * @param  array<int, array<string, mixed>>  $frames
     */
    protected function formatFrames(array $frames): string
    {
        return collect($frames)
            ->reverse()
            ->take(15)
            ->map(function (array $frame) {
                $inApp = ($frame['in_app'] ?? true) ? '[in-app]' : '[vendor]';
                $location = ($frame['filename'] ?? '?').':'.($frame['lineno'] ?? '?');
                $function = $frame['function'] ?? null;
                $context = trim($frame['context_line'] ?? '');

                return trim("{$inApp} {$location}".($function ? " in {$function}()" : '').($context !== '' ? "\n    {$context}" : ''));
            })
            ->implode("\n");
    }
}
