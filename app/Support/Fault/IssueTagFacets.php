<?php

namespace App\Support\Fault;

use App\Models\FaultIssue;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

class IssueTagFacets
{
    private const SAMPLE_SIZE = 500;

    private const TOP_KEYS = 5;

    private const TOP_VALUES = 5;

    /**
     * The most frequent tag keys among the issue's latest events, each with
     * its value distribution. Cached for 60 seconds per issue.
     *
     * @return list<array{key: string, total: int, values: list<array{value: string, count: int, percent: float}>}>
     */
    public function for(FaultIssue $issue): array
    {
        return Cache::remember("fault:issue:{$issue->id}:tag-facets", 60, fn () => $this->compute($issue));
    }

    /**
     * @return list<array{key: string, total: int, values: list<array{value: string, count: int, percent: float}>}>
     */
    private function compute(FaultIssue $issue): array
    {
        /** @var array<string, array<string, int>> $counts */
        $counts = [];

        $issue->events()
            ->whereNotNull('tags')
            ->orderByDesc('occurred_at')
            ->limit(self::SAMPLE_SIZE)
            ->get(['id', 'tags'])
            ->each(function ($event) use (&$counts): void {
                foreach ($this->normalize($event->tags) as $key => $value) {
                    $counts[$key][$value] = ($counts[$key][$value] ?? 0) + 1;
                }
            });

        uasort($counts, fn (array $a, array $b) => array_sum($b) <=> array_sum($a));

        $facets = [];

        foreach (array_slice($counts, 0, self::TOP_KEYS, true) as $key => $values) {
            $total = array_sum($values);
            arsort($values);

            $facets[] = [
                'key' => (string) $key,
                'total' => $total,
                'values' => collect(array_slice($values, 0, self::TOP_VALUES, true))
                    ->map(fn (int $count, int|string $value) => [
                        'value' => (string) $value,
                        'count' => $count,
                        'percent' => round($count / $total * 100, 1),
                    ])->values()->all(),
            ];
        }

        return $facets;
    }

    /**
     * Sentry tags arrive either as a map or as a list of [key, value] pairs.
     *
     * @return array<string, string>
     */
    private function normalize(mixed $tags): array
    {
        $normalized = [];

        foreach (is_array($tags) ? $tags : [] as $key => $value) {
            if (is_array($value) && array_is_list($value) && count($value) === 2) {
                [$key, $value] = $value;
            }

            if (is_scalar($value) && (is_string($key) || is_int($key))) {
                $normalized[(string) $key] = Str::limit((string) $value, 80);
            }
        }

        return $normalized;
    }
}
