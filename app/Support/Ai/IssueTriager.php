<?php

namespace App\Support\Ai;

use App\Ai\Agents\IssueTriageAgent;
use App\Models\FaultIssue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use RuntimeException;
use Throwable;

class IssueTriager
{
    public function __construct(protected TriageResultParser $parser) {}

    /**
     * Classify an issue through OpenRouter using the configured triage model
     * and store the labels on it. On failure the error is recorded on the
     * issue and rethrown; no other model is ever substituted.
     */
    public function triage(FaultIssue $issue): void
    {
        $organization = $issue->project->organization;
        $key = $organization->aiTriageKey();

        if ($key === null) {
            throw new RuntimeException('No OpenRouter key is available for triage.');
        }

        $modelId = $organization->aiTriageModelId();

        Config::set('ai.providers.openrouter', ['driver' => 'openrouter', 'key' => $key]);

        try {
            $response = (new IssueTriageAgent)->prompt(
                $this->describe($issue),
                provider: 'openrouter',
                model: $modelId,
                timeout: 30,
            );

            $result = $this->parser->parse($response->text);

            $issue->forceFill([
                'triage_category' => $result['category']?->value,
                'triage_priority' => $result['priority']?->value,
                'triage_model' => $modelId,
                'triaged_at' => now(),
                'triage_error' => null,
            ])->saveQuietly();
        } catch (Throwable $exception) {
            $issue->forceFill([
                'triage_model' => $modelId,
                'triage_error' => Str::limit($exception->getMessage(), 480),
            ])->saveQuietly();

            throw $exception;
        }
    }

    /**
     * A compact plain-text description of the issue for the classifier.
     */
    public function describe(FaultIssue $issue): string
    {
        $event = $issue->events()->latest('occurred_at')->first();
        $exception = $event?->exception['values'][0] ?? null;

        $frames = collect($exception['stacktrace']['frames'] ?? [])
            ->reverse()
            ->filter(fn (array $frame): bool => $frame['in_app'] ?? false)
            ->take(5)
            ->map(fn (array $frame): string => ($frame['filename'] ?? '?').':'.($frame['function'] ?? '?'))
            ->implode("\n");

        $handled = $event?->handled() ?? $issue->latest_event_handled;

        return implode("\n", array_filter([
            'type: '.($exception['type'] ?? $issue->type ?? 'unknown'),
            'message: '.Str::limit((string) ($exception['value'] ?? $issue->title), 500, ''),
            'culprit: '.($issue->culprit ?? 'unknown'),
            'level: '.$issue->level,
            'platform: '.($issue->project->platform->value ?? 'unknown'),
            'environment: '.($event?->environment ?? 'unknown'),
            'handled: '.($handled === null ? 'unknown' : ($handled ? 'yes' : 'no')),
            $frames !== '' ? "in-app frames:\n{$frames}" : null,
        ]));
    }
}
