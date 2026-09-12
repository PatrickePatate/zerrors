<?php

namespace App\Services\Monitoring;

use Carbon\Carbon;
use RuntimeException;

class CertificateInspector
{
    /**
     * Opens a TLS connection to the given URL's host and reads the peer
     * certificate's expiry. Trust is intentionally not verified here —
     * Http::get() already surfaces TLS trust failures as a request
     * exception elsewhere in the check; this connection exists purely to
     * read the certificate's expiry date.
     */
    public function expiresAt(string $url, int $timeoutSeconds): Carbon
    {
        $host = parse_url($url, PHP_URL_HOST);
        $port = parse_url($url, PHP_URL_PORT) ?? 443;

        if (! $host) {
            throw new RuntimeException('Unable to determine host for certificate check.');
        }

        $context = stream_context_create([
            'ssl' => [
                'capture_peer_cert' => true,
                'verify_peer' => false,
                'verify_peer_name' => false,
            ],
        ]);

        $client = @stream_socket_client(
            "ssl://{$host}:{$port}",
            $errno,
            $errstr,
            max(1, $timeoutSeconds),
            STREAM_CLIENT_CONNECT,
            $context
        );

        if (! $client) {
            throw new RuntimeException("Unable to establish TLS connection to read certificate: {$errstr}");
        }

        try {
            $cert = stream_context_get_params($client)['options']['ssl']['peer_certificate'] ?? null;
        } finally {
            fclose($client);
        }

        if (! $cert) {
            throw new RuntimeException('Unable to retrieve peer certificate.');
        }

        $parsed = openssl_x509_parse($cert);

        if (! $parsed || ! isset($parsed['validTo_time_t'])) {
            throw new RuntimeException('Unable to parse peer certificate.');
        }

        return Carbon::createFromTimestamp($parsed['validTo_time_t']);
    }
}
