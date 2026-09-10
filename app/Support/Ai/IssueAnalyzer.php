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

        return trim(view('prompts.issue-analysis', [
            'issue' => $issue,
            'event' => $event,
            'exception' => $exception,
            'frames' => $exception ? $this->formatFrames($exception['stacktrace']['frames'] ?? []) : null,
        ])->render());
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
