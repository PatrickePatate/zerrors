<?php

namespace App\Support\Fault;

use Illuminate\Support\Str;

class EventFingerprinter
{
    private const DEFAULT_FINGERPRINT_TOKEN = '{{ default }}';

    /**
     * Placeholder substitutions applied to unquoted message text, in order.
     *
     * @var array<string, string>
     */
    private const MESSAGE_NORMALIZATIONS = [
        '/\b[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}\b/i' => '<uuid>',
        '/[a-z0-9._%+\-]+@[a-z0-9.\-]+\.[a-z]{2,}/i' => '<email>',
        '/\b(?:[0-9a-f]{1,4}:){7}[0-9a-f]{1,4}\b/i' => '<ip>',
        '/\b(?:\d{1,3}\.){3}\d{1,3}\b/' => '<ip>',
        '/\b(?=[0-9a-f]*\d)[0-9a-f]{8,}\b/i' => '<hex>',
        '/\d+/' => '<n>',
    ];

    /**
     * Derive a stable grouping fingerprint for an incoming Sentry event payload.
     *
     * An explicit fingerprint wins, where a `{{ default }}` entry expands to the default grouping parts.
     * Otherwise the default mirrors Sentry: exception type + innermost meaningful frame, then message
     * template, then transaction/culprit.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function for(array $payload): string
    {
        $explicit = $payload['fingerprint'] ?? null;

        if (is_array($explicit) && $explicit !== []) {
            $parts = [];

            foreach ($explicit as $entry) {
                $parts = $entry === self::DEFAULT_FINGERPRINT_TOKEN
                    ? array_merge($parts, self::defaultParts($payload))
                    : [...$parts, strval($entry)];
            }

            return sha1(implode('|', $parts));
        }

        return sha1(implode('|', self::defaultParts($payload)));
    }

    /**
     * Human-readable issue title, similar to what Sentry shows as the issue header.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function title(array $payload): string
    {
        $exception = self::lastException($payload);

        if ($exception) {
            return $exception['type'] ?? 'Error';
        }

        if (isset($payload['message'])) {
            $message = is_array($payload['message']) ? ($payload['message']['formatted'] ?? $payload['message']['message'] ?? '') : $payload['message'];

            return Str::limit($message, 120);
        }

        return $payload['transaction'] ?? 'Unknown event';
    }

    /**
     * The grouping parts used when the event carries no explicit fingerprint.
     *
     * @param  array<string, mixed>  $payload
     * @return list<string>
     */
    private static function defaultParts(array $payload): array
    {
        $exception = self::lastException($payload);

        if ($exception !== null) {
            return self::exceptionParts($exception);
        }

        $message = self::messageTemplate($payload);

        if ($message !== null) {
            return ['message', self::normalizeMessage($message)];
        }

        return ['unknown', (string) ($payload['transaction'] ?? ''), (string) ($payload['culprit'] ?? '')];
    }

    /**
     * The exception actually thrown. Sentry orders chained exceptions oldest-first, so it is the last one.
     *
     * @param  array<string, mixed>  $payload
     * @return array<string, mixed>|null
     */
    private static function lastException(array $payload): ?array
    {
        $values = $payload['exception']['values'] ?? null;

        if (! is_array($values) || $values === []) {
            return null;
        }

        $last = end($values);

        return is_array($last) && $last !== [] ? $last : null;
    }

    /**
     * @param  array<string, mixed>  $exception
     * @return list<string>
     */
    private static function exceptionParts(array $exception): array
    {
        $frame = self::groupingFrame($exception['stacktrace']['frames'] ?? null);

        $parts = [$exception['type'] ?? 'Error'];

        if ($frame !== null) {
            $parts[] = $frame['filename'] ?? $frame['abs_path'] ?? null;

            if (! isset($frame['filename'])) {
                $parts[] = $frame['module'] ?? null;
            }

            $parts[] = $frame['function'] ?? null;
        }

        return array_map(strval(...), array_values(array_filter($parts, fn ($part) => $part !== null)));
    }

    /**
     * Pick the frame that best locates the error. Frames are oldest-first, so search from the end:
     * the innermost in_app frame; failing that, the innermost frame outside vendor/node_modules;
     * failing that, the last frame.
     *
     * @return array<string, mixed>|null
     */
    private static function groupingFrame(mixed $frames): ?array
    {
        if (! is_array($frames)) {
            return null;
        }

        $frames = array_values(array_filter($frames, 'is_array'));

        if ($frames === []) {
            return null;
        }

        for ($index = count($frames) - 1; $index >= 0; $index--) {
            if (($frames[$index]['in_app'] ?? false) === true) {
                return $frames[$index];
            }
        }

        for ($index = count($frames) - 1; $index >= 0; $index--) {
            if (! self::isVendorFrame($frames[$index])) {
                return $frames[$index];
            }
        }

        return $frames[count($frames) - 1];
    }

    /**
     * @param  array<string, mixed>  $frame
     */
    private static function isVendorFrame(array $frame): bool
    {
        $path = '/'.str_replace('\\', '/', (string) ($frame['filename'] ?? $frame['abs_path'] ?? ''));

        return str_contains($path, '/vendor/') || str_contains($path, '/node_modules/');
    }

    /**
     * The message to fingerprint: Sentry's un-interpolated template when present, otherwise the formatted text.
     *
     * @param  array<string, mixed>  $payload
     */
    private static function messageTemplate(array $payload): ?string
    {
        $logentryMessage = $payload['logentry']['message'] ?? null;

        if (is_string($logentryMessage) && $logentryMessage !== '') {
            return $logentryMessage;
        }

        $message = $payload['message'] ?? null;

        if (is_scalar($message)) {
            return (string) $message;
        }

        if (! is_array($message)) {
            return null;
        }

        $template = $message['message'] ?? null;

        if (is_string($template) && $template !== '') {
            return $template;
        }

        $formatted = $message['formatted'] ?? null;

        return is_string($formatted) ? $formatted : '';
    }

    /**
     * Replace variable data (ids, addresses, numbers) with placeholders so messages that differ only by
     * such values group together. Text inside quotes is left untouched.
     */
    private static function normalizeMessage(string $message): string
    {
        $segments = preg_split('/("[^"]*"|\'[^\']*\')/', $message, -1, PREG_SPLIT_DELIM_CAPTURE);

        if ($segments === false) {
            return $message;
        }

        foreach ($segments as $index => $segment) {
            if ($index % 2 === 0) {
                $segments[$index] = preg_replace(
                    array_keys(self::MESSAGE_NORMALIZATIONS),
                    array_values(self::MESSAGE_NORMALIZATIONS),
                    $segment,
                ) ?? $segment;
            }
        }

        return implode('', $segments);
    }
}
