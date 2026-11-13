<?php

namespace App\Support\Ai;

use App\Enums\TriageCategory;
use App\Enums\TriagePriority;
use App\Models\FaultIssue;
use Illuminate\Support\Facades\Config;
use Illuminate\Support\Str;
use Laravel\Ai\Classification;
use Laravel\Ai\Classification\Choice;
use Laravel\Ai\Responses\Data\ChoiceAnswer;
use RuntimeException;
use Throwable;

class IssueTriager
{
    /**
     * Classify an issue through the AI SDK's classification API using the
     * organization's triage provider and model, and store the labels on it.
     * On failure the error is recorded on the issue and rethrown; no other
     * provider or model is ever substituted.
     */
    public function triage(FaultIssue $issue): void
    {
        $organization = $issue->project->organization;
        $key = $organization->aiTriageKey();
        $provider = $organization->aiTriageProvider();

        if ($key === null) {
            throw new RuntimeException("No {$provider} key is available for triage.");
        }

        $modelId = $organization->aiTriageModelId();

        Config::set("ai.providers.{$provider}", ['driver' => $provider, 'key' => $key]);

        try {
            $response = Classification::of($this->describe($issue))
                ->question('category', new Choice(
                    'What kind of problem does this application error represent?',
                    $this->options(TriageCategory::class),
                ))
                ->question('priority', new Choice(
                    'How urgently should this application error be fixed?',
                    $this->options(TriagePriority::class),
                ))
                ->timeout(30)
                ->classify($provider, $modelId);

            $category = $this->choice($response->answer('category'), TriageCategory::class);
            $priority = $this->choice($response->answer('priority'), TriagePriority::class);

            $modelId = $response->meta->model ?? $modelId;

            $issue->forceFill([
                'triage_category' => $category->value,
                'triage_priority' => $priority->value,
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
     * The enum values as classification options, each with its label.
     *
     * @param  class-string<TriageCategory|TriagePriority>  $enum
     * @return array<string, string>
     */
    protected function options(string $enum): array
    {
        return collect($enum::cases())
            ->mapWithKeys(fn (TriageCategory|TriagePriority $case): array => [$case->value => $case->label()])
            ->all();
    }

    /**
     * @template T of TriageCategory|TriagePriority
     *
     * @param  class-string<T>  $enum
     * @return T
     */
    protected function choice(mixed $answer, string $enum): TriageCategory|TriagePriority
    {
        $case = $answer instanceof ChoiceAnswer ? $enum::tryFrom($answer->choice) : null;

        return $case ?? throw new RuntimeException('The classifier returned no usable label.');
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
