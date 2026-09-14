<?php

namespace App\Support\Fault;

class EventPayloadCensor
{
    public const CENSORED_VALUE = '<CENSORED>';

    /**
     * Redact the given header names (case-insensitively) from an event payload's
     * `request.headers`. The `payload` field stored on FaultEvent is the same
     * decoded array, so redacting it here before it's persisted or forwarded
     * covers both.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    public static function redact(array $payload, array $headers): array
    {
        if ($headers === [] || empty($payload['request']['headers']) || ! is_array($payload['request']['headers'])) {
            return $payload;
        }

        $censored = array_map(strtolower(...), $headers);

        foreach ($payload['request']['headers'] as $name => $value) {
            if (in_array(strtolower((string) $name), $censored, true)) {
                $payload['request']['headers'][$name] = self::CENSORED_VALUE;
            }
        }

        return $payload;
    }
}
