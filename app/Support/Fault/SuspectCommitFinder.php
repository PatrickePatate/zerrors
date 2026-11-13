<?php

namespace App\Support\Fault;

use App\Models\FaultIssue;
use App\Models\ReleaseCommit;
use Illuminate\Support\Collection;

class SuspectCommitFinder
{
    /**
     * Commits of the issue's first-seen release that touched a file appearing
     * in the in-app frames of the latest event. Each commit gets a
     * `matched_file` attribute.
     *
     * @return Collection<int, ReleaseCommit>
     */
    public function find(FaultIssue $issue): Collection
    {
        $release = $issue->linkedRelease();

        if ($release === null) {
            return collect();
        }

        $event = $issue->events()->latest('occurred_at')->first();
        $paths = $event ? $this->inAppPaths($event->payload) : [];

        if ($paths === []) {
            return collect();
        }

        return $release->commits()->get()
            ->map(function (ReleaseCommit $commit) use ($paths): ?ReleaseCommit {
                foreach ($commit->files ?? [] as $file) {
                    foreach ($paths as $path) {
                        if ($this->matches($path, (string) $file)) {
                            $commit->setAttribute('matched_file', $file);

                            return $commit;
                        }
                    }
                }

                return null;
            })
            ->filter()
            ->values();
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, string>
     */
    protected function inAppPaths(array $payload): array
    {
        $paths = [];

        foreach ($payload['exception']['values'] ?? [] as $value) {
            foreach ($value['stacktrace']['frames'] ?? [] as $frame) {
                if (! is_array($frame) || ($frame['in_app'] ?? false) !== true) {
                    continue;
                }

                $path = $frame['filename'] ?? $frame['abs_path'] ?? null;

                if (is_string($path) && $path !== '') {
                    $paths[] = ltrim(str_replace('\\', '/', $path), '/');
                }
            }
        }

        return array_values(array_unique($paths));
    }

    protected function matches(string $framePath, string $commitFile): bool
    {
        $commitFile = ltrim($commitFile, '/');

        return $commitFile !== ''
            && ($framePath === $commitFile || str_ends_with($framePath, '/'.$commitFile));
    }
}
