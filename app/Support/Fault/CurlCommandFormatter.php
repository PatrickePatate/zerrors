<?php

namespace App\Support\Fault;

use App\Models\FaultEvent;

class CurlCommandFormatter
{
    /**
     * Rebuild a curl command that reproduces the HTTP call captured on an event,
     * so a developer can paste it straight into a terminal.
     */
    public static function format(FaultEvent $event): string
    {
        $request = $event->request ?? [];

        $url = $request['url'] ?? '';
        if (! empty($request['query_string']) && ! str_contains($url, '?')) {
            $url .= '?'.$request['query_string'];
        }

        $parts = ['curl '.escapeshellarg($url), '-X '.strtoupper($request['method'] ?? 'GET')];

        foreach ($request['headers'] ?? [] as $name => $value) {
            $value = is_array($value) ? ($value[0] ?? '') : $value;
            $parts[] = '-H '.escapeshellarg("{$name}: {$value}");
        }

        if ($body = self::formatBody($request['data'] ?? null)) {
            $parts[] = '-d '.escapeshellarg($body);
        }

        return implode(" \\\n  ", $parts);
    }

    protected static function formatBody(mixed $data): ?string
    {
        if ($data === null || $data === '') {
            return null;
        }

        return is_array($data) ? json_encode($data, JSON_UNESCAPED_SLASHES) : (string) $data;
    }
}
