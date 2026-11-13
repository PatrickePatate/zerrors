<?php

namespace App\Support\Net;

use InvalidArgumentException;

/**
 * Decides whether the server may open an outbound connection to a URL or host.
 *
 * Only http/https URLs are accepted, and every address the host resolves to
 * (A and AAAA, literal IPs included) must be public. Any private, loopback,
 * link-local, CGNAT, multicast or otherwise reserved address blocks the whole
 * host, so a mixed record set cannot slip through. Names that do not resolve
 * are blocked too (fail closed).
 *
 * Set `monitoring.allow_private_targets` to true to skip the address checks
 * (self-hosters monitoring LAN services). The scheme check still applies.
 */
class OutboundUrlGuard
{
    /**
     * CIDR ranges that are never a legitimate public destination.
     *
     * @var list<string>
     */
    protected const BLOCKED_RANGES = [
        '0.0.0.0/8',
        '10.0.0.0/8',
        '100.64.0.0/10',
        '127.0.0.0/8',
        '169.254.0.0/16',
        '172.16.0.0/12',
        '192.0.0.0/24',
        '192.0.2.0/24',
        '192.168.0.0/16',
        '198.18.0.0/15',
        '198.51.100.0/24',
        '203.0.113.0/24',
        '224.0.0.0/4',
        '240.0.0.0/4',
        '::/128',
        '::1/128',
        '64:ff9b::/96',
        '100::/64',
        '2001::/32',
        '2001:db8::/32',
        '2002::/16',
        'fc00::/7',
        'fe80::/10',
        'fec0::/10',
        'ff00::/8',
    ];

    /**
     * @var (callable(string): list<string>)|null
     */
    protected $resolver;

    /**
     * @param  (callable(string): list<string>)|null  $resolver  Maps a hostname to its resolved IP addresses. Defaults to a live DNS lookup.
     */
    public function __construct(?callable $resolver = null)
    {
        $this->resolver = $resolver;
    }

    public function isAllowedUrl(string $url): bool
    {
        return $this->check(fn () => $this->assertAllowedUrl($url));
    }

    public function isAllowedHost(string $host): bool
    {
        return $this->check(fn () => $this->assertAllowedHost($host));
    }

    /**
     * @throws InvalidArgumentException
     */
    public function assertAllowedUrl(string $url): void
    {
        $parts = parse_url($url);

        if ($parts === false || ! isset($parts['scheme'], $parts['host'])) {
            throw new InvalidArgumentException('The URL must be an absolute http or https URL with a host.');
        }

        if (! in_array(strtolower($parts['scheme']), ['http', 'https'], true)) {
            throw new InvalidArgumentException('Only http and https URLs are allowed.');
        }

        $this->assertAllowedHost($parts['host']);
    }

    /**
     * Accepts a bare hostname, an IPv4 address, or an IPv6 address (with or
     * without brackets).
     *
     * @throws InvalidArgumentException
     */
    public function assertAllowedHost(string $host): void
    {
        if (config('monitoring.allow_private_targets', false)) {
            return;
        }

        $host = strtolower(trim($host, '[] '));
        $host = rtrim($host, '.');

        if ($host === '') {
            throw new InvalidArgumentException('The host must not be empty.');
        }

        if ($host === 'localhost' || str_ends_with($host, '.localhost')) {
            throw new InvalidArgumentException("Host \"{$host}\" points to the local machine and is not allowed.");
        }

        $addresses = filter_var($host, FILTER_VALIDATE_IP) !== false
            ? [$host]
            : $this->resolve($host);

        if ($addresses === []) {
            throw new InvalidArgumentException("Host \"{$host}\" could not be resolved.");
        }

        foreach ($addresses as $address) {
            if ($this->isReservedAddress($address)) {
                throw new InvalidArgumentException("Host \"{$host}\" resolves to a private, loopback or reserved address ({$address}) and is not allowed.");
            }
        }
    }

    /**
     * Resolves a hostname to its IPv4 and IPv6 addresses. Numeric IPv4 forms
     * (decimal, hex, octal, short) are also expanded by the system resolver.
     *
     * @return list<string>
     */
    protected function resolve(string $host): array
    {
        if ($this->resolver !== null) {
            return array_values(array_map('strval', ($this->resolver)($host)));
        }

        $addresses = [];

        $ipv4 = @gethostbynamel($host);

        if (is_array($ipv4)) {
            $addresses = $ipv4;
        }

        $records = @dns_get_record($host, DNS_AAAA);

        foreach (is_array($records) ? $records : [] as $record) {
            if (isset($record['ipv6'])) {
                $addresses[] = $record['ipv6'];
            }
        }

        return array_values(array_unique($addresses));
    }

    protected function isReservedAddress(string $address): bool
    {
        $binary = @inet_pton($address);

        if ($binary === false) {
            return true;
        }

        // IPv4-mapped IPv6 (::ffff:a.b.c.d) is judged by the embedded IPv4 address.
        if (strlen($binary) === 16 && substr($binary, 0, 12) === str_repeat("\0", 10)."\xff\xff") {
            return $this->isReservedAddress(inet_ntop(substr($binary, 12)));
        }

        if (filter_var($address, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) === false) {
            return true;
        }

        foreach (self::BLOCKED_RANGES as $range) {
            if ($this->inRange($binary, $range)) {
                return true;
            }
        }

        return false;
    }

    protected function inRange(string $binaryAddress, string $cidr): bool
    {
        [$network, $prefix] = explode('/', $cidr);

        $binaryNetwork = inet_pton($network);

        if ($binaryNetwork === false || strlen($binaryNetwork) !== strlen($binaryAddress)) {
            return false;
        }

        $fullBytes = intdiv((int) $prefix, 8);
        $remainingBits = (int) $prefix % 8;

        if (substr($binaryAddress, 0, $fullBytes) !== substr($binaryNetwork, 0, $fullBytes)) {
            return false;
        }

        if ($remainingBits === 0) {
            return true;
        }

        $mask = (0xFF << (8 - $remainingBits)) & 0xFF;

        return (ord($binaryAddress[$fullBytes]) & $mask) === (ord($binaryNetwork[$fullBytes]) & $mask);
    }

    protected function check(callable $assertion): bool
    {
        try {
            $assertion();
        } catch (InvalidArgumentException) {
            return false;
        }

        return true;
    }
}
