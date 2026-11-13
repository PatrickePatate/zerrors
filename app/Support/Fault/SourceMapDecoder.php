<?php

namespace App\Support\Fault;

/**
 * Decodes a version 3 source map and resolves generated positions to their
 * original source position. Lines are 1-based and columns 0-based, as in the
 * reference source-map library.
 */
class SourceMapDecoder
{
    private const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /**
     * @param  array<int, array<int, array{0: int, 1: ?int, 2: ?int, 3: ?int, 4: ?int}>>  $lines  segments per generated line (0-based), sorted by generated column
     * @param  array<int, string>  $sources
     * @param  array<int, string>  $names
     * @param  array<int, ?string>  $sourcesContent
     */
    private function __construct(
        private readonly array $lines,
        private readonly array $sources,
        private readonly array $names,
        private readonly array $sourcesContent,
    ) {}

    /**
     * @throws InvalidSourceMapException
     */
    public static function fromJson(string $json): self
    {
        $map = json_decode($json, true);

        if (! is_array($map)) {
            throw new InvalidSourceMapException('The source map is not valid JSON.');
        }

        if (isset($map['sections'])) {
            throw new InvalidSourceMapException('Index source maps (sections) are not supported.');
        }

        if (($map['version'] ?? null) !== 3) {
            throw new InvalidSourceMapException('Only version 3 source maps are supported.');
        }

        if (! is_string($map['mappings'] ?? null) || ! is_array($map['sources'] ?? null)) {
            throw new InvalidSourceMapException('The source map is missing "mappings" or "sources".');
        }

        $root = is_string($map['sourceRoot'] ?? null) && $map['sourceRoot'] !== '' ? rtrim($map['sourceRoot'], '/').'/' : '';

        $sources = array_map(
            fn ($source) => self::cleanSource($root.(string) $source),
            array_values($map['sources']),
        );

        return new self(
            self::decodeMappings($map['mappings'], count($sources)),
            $sources,
            array_map('strval', array_values($map['names'] ?? [])),
            array_values($map['sourcesContent'] ?? []),
        );
    }

    /**
     * @return array{source: string, line: int, column: int, name: ?string}|null
     */
    public function lookup(int $line, int $column): ?array
    {
        $segments = $this->lines[$line - 1] ?? null;

        if (! $segments) {
            return null;
        }

        $low = 0;
        $high = count($segments) - 1;
        $found = null;

        while ($low <= $high) {
            $mid = intdiv($low + $high, 2);

            if ($segments[$mid][0] <= $column) {
                $found = $mid;
                $low = $mid + 1;
            } else {
                $high = $mid - 1;
            }
        }

        if ($found === null || $segments[$found][1] === null) {
            return null;
        }

        [, $sourceIndex, $originalLine, $originalColumn, $nameIndex] = $segments[$found];

        return [
            'source' => $this->sources[$sourceIndex],
            'line' => $originalLine + 1,
            'column' => $originalColumn,
            'name' => $nameIndex !== null ? ($this->names[$nameIndex] ?? null) : null,
        ];
    }

    /**
     * @return array{pre_context: array<int, string>, context_line: string, post_context: array<int, string>}|null
     */
    public function context(string $source, int $line, int $radius = 5): ?array
    {
        $index = array_search($source, $this->sources, true);
        $content = $index === false ? null : ($this->sourcesContent[$index] ?? null);

        if (! is_string($content)) {
            return null;
        }

        $all = preg_split('/\r\n|\n|\r/', $content);

        if (! isset($all[$line - 1])) {
            return null;
        }

        return [
            'pre_context' => array_slice($all, max(0, $line - 1 - $radius), min($radius, $line - 1)),
            'context_line' => $all[$line - 1],
            'post_context' => array_slice($all, $line, $radius),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function sources(): array
    {
        return $this->sources;
    }

    private static function cleanSource(string $source): string
    {
        $source = (string) preg_replace('#^webpack://[^/]*/#', '', $source);

        return str_starts_with($source, './') ? substr($source, 2) : $source;
    }

    /**
     * @return array<int, array<int, array{0: int, 1: ?int, 2: ?int, 3: ?int, 4: ?int}>>
     */
    private static function decodeMappings(string $mappings, int $sourceCount): array
    {
        $lookup = array_flip(str_split(self::BASE64));
        $lines = [];
        $source = 0;
        $originalLine = 0;
        $originalColumn = 0;
        $name = 0;

        foreach (explode(';', $mappings) as $lineMappings) {
            $generatedColumn = 0;
            $segments = [];

            foreach ($lineMappings === '' ? [] : explode(',', $lineMappings) as $encoded) {
                $values = self::decodeVlq($encoded, $lookup);
                $count = count($values);

                if ($count !== 1 && $count !== 4 && $count !== 5) {
                    throw new InvalidSourceMapException('Malformed mapping segment.');
                }

                $generatedColumn += $values[0];

                if ($count === 1) {
                    $segments[] = [$generatedColumn, null, null, null, null];

                    continue;
                }

                $source += $values[1];
                $originalLine += $values[2];
                $originalColumn += $values[3];

                if ($source < 0 || $source >= $sourceCount || $originalLine < 0 || $originalColumn < 0) {
                    throw new InvalidSourceMapException('Mapping segment points outside the source map.');
                }

                if ($count === 5) {
                    $name += $values[4];
                }

                $segments[] = [$generatedColumn, $source, $originalLine, $originalColumn, $count === 5 ? $name : null];
            }

            usort($segments, fn (array $a, array $b) => $a[0] <=> $b[0]);
            $lines[] = $segments;
        }

        return $lines;
    }

    /**
     * @param  array<string, int>  $lookup
     * @return array<int, int>
     */
    private static function decodeVlq(string $encoded, array $lookup): array
    {
        $values = [];
        $shift = 0;
        $value = 0;

        foreach (str_split($encoded) as $char) {
            if (! isset($lookup[$char])) {
                throw new InvalidSourceMapException('Invalid base64 VLQ character.');
            }

            $digit = $lookup[$char];
            $value += ($digit & 31) << $shift;

            if ($digit & 32) {
                $shift += 5;

                continue;
            }

            $values[] = ($value & 1) ? -($value >> 1) : ($value >> 1);
            $value = 0;
            $shift = 0;
        }

        if ($shift !== 0) {
            throw new InvalidSourceMapException('Truncated base64 VLQ value.');
        }

        return $values;
    }
}
