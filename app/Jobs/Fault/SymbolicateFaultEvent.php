<?php

namespace App\Jobs\Fault;

use App\Models\FaultEvent;
use App\Models\SourceMap;
use App\Support\Fault\InvalidSourceMapException;
use App\Support\Fault\SourceMapDecoder;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

class SymbolicateFaultEvent implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public function __construct(public int $eventId) {}

    public function handle(): void
    {
        $event = FaultEvent::find($this->eventId);

        if (! $event || ! $event->release || empty($event->exception['values'])) {
            return;
        }

        $maps = SourceMap::query()
            ->where('fault_project_id', $event->fault_project_id)
            ->where('release', $event->release)
            ->get()
            ->keyBy(fn (SourceMap $map) => self::normalizePath($map->artifact_name));

        if ($maps->isEmpty()) {
            return;
        }

        $exception = $event->exception;
        $remaining = (int) config('fault.symbolication_max_frames');
        $changed = false;

        /** @var array<int, SourceMapDecoder|false> $decoders */
        $decoders = [];

        foreach ($exception['values'] as $valueIndex => $value) {
            foreach ($value['stacktrace']['frames'] ?? [] as $frameIndex => $frame) {
                if ($remaining <= 0) {
                    break 2;
                }

                $remaining--;

                $symbolicated = $this->symbolicateFrame($frame, $maps, $decoders);

                if ($symbolicated !== null) {
                    $exception['values'][$valueIndex]['stacktrace']['frames'][$frameIndex] = $symbolicated;
                    $changed = true;
                }
            }
        }

        if ($changed) {
            $event->exception = $exception;
            $event->save();
        }
    }

    /**
     * The URL path of an artifact or frame location, without scheme, host,
     * query string, fragment or Sentry's "~" host placeholder.
     */
    public static function normalizePath(string $location): string
    {
        $path = parse_url($location, PHP_URL_PATH);
        $path = is_string($path) && $path !== '' ? $path : $location;
        $path = ltrim(preg_replace('/[?#].*$/', '', $path), '~');

        return '/'.ltrim($path, '/');
    }

    /**
     * @param  array<string, mixed>  $frame
     * @param  Collection<string, SourceMap>  $maps
     * @param  array<int, SourceMapDecoder|false>  $decoders
     * @return array<string, mixed>|null
     */
    private function symbolicateFrame(array $frame, $maps, array &$decoders): ?array
    {
        if (isset($frame['data']['minified']) || empty($frame['lineno'])) {
            return null;
        }

        $map = null;

        foreach ([$frame['abs_path'] ?? null, $frame['filename'] ?? null] as $location) {
            if (is_string($location) && $location !== '' && $map = $maps->get(self::normalizePath($location))) {
                break;
            }
        }

        if (! $map) {
            return null;
        }

        $decoder = $decoders[$map->id] ??= $this->loadDecoder($map);

        if (! $decoder) {
            return null;
        }

        $column = max(0, ((int) ($frame['colno'] ?? 1)) - 1);
        $original = $decoder->lookup((int) $frame['lineno'], $column);

        if (! $original) {
            return null;
        }

        $frame['data']['minified'] = array_filter([
            'filename' => $frame['filename'] ?? $frame['abs_path'] ?? null,
            'lineno' => $frame['lineno'],
            'colno' => $frame['colno'] ?? null,
            'function' => $frame['function'] ?? null,
        ], fn ($value) => $value !== null);

        $frame['filename'] = $original['source'];
        $frame['lineno'] = $original['line'];
        $frame['colno'] = $original['column'] + 1;

        if ($original['name'] !== null && in_array($frame['function'] ?? null, [null, '', '?', '<anonymous>'], true)) {
            $frame['function'] = $original['name'];
        }

        if ($context = $decoder->context($original['source'], $original['line'])) {
            $frame = array_merge($frame, $context);
        } else {
            unset($frame['pre_context'], $frame['context_line'], $frame['post_context']);
        }

        if (str_contains($original['source'], 'node_modules')) {
            $frame['in_app'] = false;
        }

        return $frame;
    }

    private function loadDecoder(SourceMap $map): SourceMapDecoder|false
    {
        if ($map->size > (int) config('fault.max_source_map_bytes') || ! Storage::exists($map->path)) {
            return false;
        }

        try {
            return SourceMapDecoder::fromJson((string) Storage::get($map->path));
        } catch (InvalidSourceMapException) {
            return false;
        }
    }
}
