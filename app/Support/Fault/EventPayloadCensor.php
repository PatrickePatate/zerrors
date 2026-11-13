<?php

namespace App\Support\Fault;

class EventPayloadCensor
{
    public const CENSORED_VALUE = '<CENSORED>';

    /**
     * Key names (after lowercasing and turning "-" into "_") that are redacted
     * when they contain any of these fragments.
     */
    private const SENSITIVE_PATTERN = '/password|passwd|secret|token|api_?key|authorization|private_?key|credit_?card|card_?number|cvv|cvc/';

    /**
     * Keys too short to safely match as a fragment (e.g. "ssn" inside "classname").
     */
    private const SENSITIVE_EXACT = ['ssn', 'pwd', 'pin'];

    /**
     * Redact an event payload before it is persisted or forwarded: the given
     * header names (case-insensitively) from `request.headers`, and
     * sensitive-looking keys (password, token, secret, ...) anywhere inside
     * `request.data`, `request.cookies`, `request.query_string`, `request.env`,
     * `extra` and `contexts`. All cookie values are redacted.
     *
     * @param  array<string, mixed>  $payload
     * @param  list<string>  $headers
     * @return array<string, mixed>
     */
    public static function redact(array $payload, array $headers): array
    {
        if (isset($payload['request']) && is_array($payload['request'])) {
            $request = &$payload['request'];

            if ($headers !== [] && ! empty($request['headers']) && is_array($request['headers'])) {
                $censored = array_map(strtolower(...), $headers);

                foreach ($request['headers'] as $name => $value) {
                    if (in_array(strtolower((string) $name), $censored, true)) {
                        $request['headers'][$name] = self::CENSORED_VALUE;
                    }
                }
            }

            foreach (['data', 'env'] as $key) {
                if (isset($request[$key]) && is_array($request[$key])) {
                    $request[$key] = self::redactTree($request[$key]);
                }
            }

            if (isset($request['cookies'])) {
                $request['cookies'] = self::redactCookies($request['cookies']);
            }

            if (isset($request['query_string'])) {
                $request['query_string'] = self::redactQueryString($request['query_string']);
            }

            unset($request);
        }

        foreach (['extra', 'contexts'] as $key) {
            if (isset($payload[$key]) && is_array($payload[$key])) {
                $payload[$key] = self::redactTree($payload[$key]);
            }
        }

        return $payload;
    }

    private static function isSensitiveKey(string $key): bool
    {
        $key = str_replace('-', '_', strtolower($key));

        return in_array($key, self::SENSITIVE_EXACT, true) || preg_match(self::SENSITIVE_PATTERN, $key) === 1;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    private static function redactTree(array $data): array
    {
        // A [key, value] pair, as some SDKs encode query strings and cookies.
        if (count($data) === 2 && array_is_list($data) && is_string($data[0]) && self::isSensitiveKey($data[0])) {
            $data[1] = self::CENSORED_VALUE;

            return $data;
        }

        foreach ($data as $key => $value) {
            if (is_string($key) && self::isSensitiveKey($key)) {
                $data[$key] = self::CENSORED_VALUE;
            } elseif (is_array($value)) {
                $data[$key] = self::redactTree($value);
            }
        }

        return $data;
    }

    private static function redactCookies(mixed $cookies): mixed
    {
        if (is_array($cookies)) {
            if (array_is_list($cookies)) {
                // Pair form: [[name, value], ...]
                foreach ($cookies as $i => $pair) {
                    if (is_array($pair) && count($pair) === 2) {
                        $cookies[$i] = [array_values($pair)[0], self::CENSORED_VALUE];
                    } elseif (! is_array($pair)) {
                        $cookies[$i] = self::CENSORED_VALUE;
                    }
                }

                return $cookies;
            }

            return array_map(fn () => self::CENSORED_VALUE, $cookies);
        }

        if (is_string($cookies) && $cookies !== '') {
            $parts = [];

            foreach (explode(';', $cookies) as $part) {
                $name = trim(explode('=', $part, 2)[0]);
                $parts[] = $name.'='.self::CENSORED_VALUE;
            }

            return implode('; ', $parts);
        }

        return $cookies;
    }

    private static function redactQueryString(mixed $query): mixed
    {
        if (is_array($query)) {
            return self::redactTree($query);
        }

        if (! is_string($query) || $query === '') {
            return $query;
        }

        $parts = [];

        foreach (explode('&', $query) as $pair) {
            [$name, $value] = array_pad(explode('=', $pair, 2), 2, null);

            if ($value !== null && self::isSensitiveKey(urldecode($name))) {
                $value = self::CENSORED_VALUE;
            }

            $parts[] = $value === null ? $name : $name.'='.$value;
        }

        return implode('&', $parts);
    }
}
