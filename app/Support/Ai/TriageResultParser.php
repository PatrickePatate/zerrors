<?php

namespace App\Support\Ai;

use App\Enums\TriageCategory;
use App\Enums\TriagePriority;

class TriageResultParser
{
    /**
     * Extract the category and priority from a classifier response, which may
     * be a JSON object or bare label text. Undeterminable dimensions are null.
     *
     * @return array{category: TriageCategory|null, priority: TriagePriority|null}
     */
    public function parse(string $output): array
    {
        $decoded = json_decode(trim($output), true);

        if (is_array($decoded)) {
            $category = TriageCategory::tryFrom(strtolower(trim((string) ($decoded['category'] ?? ''))));
            $priority = TriagePriority::tryFrom(strtolower(trim((string) ($decoded['priority'] ?? ''))));

            if ($category || $priority) {
                return ['category' => $category, 'priority' => $priority];
            }
        }

        return [
            'category' => $this->firstMatch($output, TriageCategory::cases()),
            'priority' => $this->firstMatch($output, TriagePriority::cases()),
        ];
    }

    /**
     * @template T of TriageCategory|TriagePriority
     *
     * @param  array<int, T>  $cases
     * @return T|null
     */
    protected function firstMatch(string $output, array $cases): TriageCategory|TriagePriority|null
    {
        $best = null;
        $bestPosition = PHP_INT_MAX;

        foreach ($cases as $case) {
            if (preg_match('/(?<![a-z_])'.preg_quote($case->value, '/').'(?![a-z_])/i', $output, $match, PREG_OFFSET_CAPTURE) && $match[0][1] < $bestPosition) {
                $best = $case;
                $bestPosition = $match[0][1];
            }
        }

        return $best;
    }
}
