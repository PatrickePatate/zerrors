<?php

namespace App\Support\Fault;

use Illuminate\Support\Str;

class EventFingerprinter
{
    /**
     * Derive a stable grouping fingerprint for an incoming Sentry event payload,
     * mirroring Sentry's default grouping: explicit fingerprint > exception type+location.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function for(array $payload): string
    {
        if (isset($payload['fingerprint']) && is_array($payload['fingerprint']) && $payload['fingerprint'] !== []) {
            return sha1(implode('|', array_map('strval', $payload['fingerprint'])));
        }

        $exception = $payload['exception']['values'][0] ?? null;

        if ($exception) {
            $frames = $exception['stacktrace']['frames'] ?? [];
            $topFrame = end($frames) ?: null;

            $parts = [
                $exception['type'] ?? 'Error',
                $topFrame['filename'] ?? $topFrame['abs_path'] ?? null,
                $topFrame['function'] ?? null,
            ];

            return sha1(implode('|', array_filter($parts, fn ($p) => $p !== null)));
        }

        if (isset($payload['message'])) {
            $message = is_array($payload['message']) ? ($payload['message']['formatted'] ?? $payload['message']['message'] ?? '') : $payload['message'];

            return sha1('message|'.$message);
        }

        return sha1('unknown|'.($payload['transaction'] ?? '').'|'.($payload['culprit'] ?? ''));
    }

    /**
     * Human-readable issue title, similar to what Sentry shows as the issue header.
     *
     * @param  array<string, mixed>  $payload
     */
    public static function title(array $payload): string
    {
        $exception = $payload['exception']['values'][0] ?? null;

        if ($exception) {
            return $exception['type'] ?? 'Error';
        }

        if (isset($payload['message'])) {
            $message = is_array($payload['message']) ? ($payload['message']['formatted'] ?? $payload['message']['message'] ?? '') : $payload['message'];

            return Str::limit($message, 120);
        }

        return $payload['transaction'] ?? 'Unknown event';
    }
}
