<?php

namespace Tests\Unit;

use App\Support\Net\OutboundUrlGuard;
use InvalidArgumentException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OutboundUrlGuardTest extends TestCase
{
    /**
     * @param  array<string, list<string>>  $records
     */
    protected function guardWithRecords(array $records = []): OutboundUrlGuard
    {
        return new OutboundUrlGuard(fn (string $host): array => $records[$host] ?? []);
    }

    /**
     * @return array<string, array{string}>
     */
    public static function reservedAddresses(): array
    {
        return [
            'IPv4 loopback' => ['127.0.0.1'],
            'IPv4 loopback range' => ['127.200.10.3'],
            'RFC1918 10/8' => ['10.0.0.1'],
            'RFC1918 172.16/12 lower bound' => ['172.16.0.1'],
            'RFC1918 172.16/12 upper bound' => ['172.31.255.255'],
            'RFC1918 192.168/16' => ['192.168.1.1'],
            'cloud metadata link-local' => ['169.254.169.254'],
            'this network' => ['0.0.0.0'],
            'CGNAT 100.64/10' => ['100.64.0.1'],
            'multicast' => ['224.0.0.1'],
            'reserved 240/4' => ['240.0.0.1'],
            'IPv6 loopback' => ['::1'],
            'IPv6 unspecified' => ['::'],
            'IPv6 unique local fc00::/7' => ['fc00::1'],
            'IPv6 unique local fd00::/8' => ['fd12:3456::1'],
            'IPv6 link-local' => ['fe80::1'],
            'IPv4-mapped loopback' => ['::ffff:127.0.0.1'],
            'IPv4-mapped metadata' => ['::ffff:169.254.169.254'],
            'NAT64 prefix' => ['64:ff9b::7f00:1'],
            'IPv6 documentation' => ['2001:db8::1'],
        ];
    }

    /**
     * @return array<string, array{string}>
     */
    public static function publicAddresses(): array
    {
        return [
            'IPv4 public' => ['93.184.216.34'],
            'IPv4 just below 172.16' => ['172.15.255.255'],
            'IPv4 just above 172.31' => ['172.32.0.1'],
            'IPv6 public' => ['2606:4700:4700::1111'],
            'IPv4-mapped public' => ['::ffff:93.184.216.34'],
        ];
    }

    #[DataProvider('publicAddresses')]
    public function test_public_literal_ips_are_allowed(string $address): void
    {
        $guard = $this->guardWithRecords();

        $host = str_contains($address, ':') ? "[{$address}]" : $address;

        $this->assertTrue($guard->isAllowedHost($address));
        $this->assertTrue($guard->isAllowedUrl("https://{$host}/"));
    }

    #[DataProvider('reservedAddresses')]
    public function test_reserved_literal_ips_are_blocked(string $address): void
    {
        $guard = $this->guardWithRecords();

        $host = str_contains($address, ':') ? "[{$address}]" : $address;

        $this->assertFalse($guard->isAllowedHost($address));
        $this->assertFalse($guard->isAllowedUrl("http://{$host}/"));
    }

    public function test_bracketed_ipv6_literals_are_blocked_in_urls(): void
    {
        $guard = $this->guardWithRecords();

        $this->assertFalse($guard->isAllowedUrl('http://[::1]:8080/health'));
        $this->assertFalse($guard->isAllowedUrl('http://[fe80::1%25eth0]/'));
        $this->assertTrue($guard->isAllowedUrl('https://[2606:4700:4700::1111]/'));
    }

    public function test_hostname_resolving_to_a_private_address_is_blocked(): void
    {
        $guard = $this->guardWithRecords(['internal.example' => ['10.0.0.5']]);

        $this->assertFalse($guard->isAllowedHost('internal.example'));
        $this->assertFalse($guard->isAllowedUrl('https://internal.example/status'));
    }

    public function test_hostname_resolving_to_a_public_address_is_allowed(): void
    {
        $guard = $this->guardWithRecords(['example.com' => ['93.184.216.34'], 'v6.example.com' => ['2606:4700:4700::1111']]);

        $this->assertTrue($guard->isAllowedUrl('https://example.com/'));
        $this->assertTrue($guard->isAllowedHost('v6.example.com.'));
    }

    public function test_mixed_public_and_private_records_are_blocked(): void
    {
        $guard = $this->guardWithRecords(['mixed.example' => ['93.184.216.34', '127.0.0.1']]);

        $this->assertFalse($guard->isAllowedHost('mixed.example'));
    }

    public function test_mixed_public_ipv4_and_private_ipv6_records_are_blocked(): void
    {
        $guard = $this->guardWithRecords(['mixed.example' => ['93.184.216.34', 'fd00::5']]);

        $this->assertFalse($guard->isAllowedHost('mixed.example'));
    }

    public function test_unresolvable_hostname_is_blocked(): void
    {
        $guard = $this->guardWithRecords();

        $this->assertFalse($guard->isAllowedHost('does-not-exist.example'));
    }

    public function test_localhost_names_are_blocked_without_a_lookup(): void
    {
        $guard = $this->guardWithRecords(['localhost' => ['93.184.216.34']]);

        $this->assertFalse($guard->isAllowedHost('localhost'));
        $this->assertFalse($guard->isAllowedUrl('http://app.localhost:8000/'));
    }

    public function test_numeric_ipv4_forms_resolving_to_loopback_are_blocked(): void
    {
        $guard = new OutboundUrlGuard;

        $this->assertFalse($guard->isAllowedUrl('http://2130706433/'));
        $this->assertFalse($guard->isAllowedUrl('http://0x7f000001/'));
        $this->assertFalse($guard->isAllowedUrl('http://127.1/'));
    }

    /**
     * @return array<string, array{string}>
     */
    public static function disallowedSchemes(): array
    {
        return [
            'file' => ['file:///etc/passwd'],
            'gopher' => ['gopher://93.184.216.34:70/'],
            'ftp' => ['ftp://93.184.216.34/'],
            'no scheme' => ['93.184.216.34/health'],
        ];
    }

    #[DataProvider('disallowedSchemes')]
    public function test_non_http_schemes_are_blocked(string $url): void
    {
        $this->assertFalse($this->guardWithRecords()->isAllowedUrl($url));
    }

    public function test_assert_allowed_url_throws_with_a_reason(): void
    {
        $guard = $this->guardWithRecords();

        $this->expectException(InvalidArgumentException::class);
        $this->expectExceptionMessage('127.0.0.1');

        $guard->assertAllowedUrl('http://127.0.0.1/');
    }

    public function test_assert_allowed_host_throws_for_empty_host(): void
    {
        $this->expectException(InvalidArgumentException::class);

        $this->guardWithRecords()->assertAllowedHost('');
    }

    public function test_private_targets_are_allowed_when_the_flag_is_enabled(): void
    {
        config()->set('monitoring.allow_private_targets', true);

        $guard = new OutboundUrlGuard(function (): array {
            $this->fail('The resolver must not be called when private targets are allowed.');
        });

        $this->assertTrue($guard->isAllowedUrl('http://127.0.0.1:8080/health'));
        $this->assertTrue($guard->isAllowedUrl('http://169.254.169.254/latest/meta-data/'));
        $this->assertTrue($guard->isAllowedHost('nas.internal'));
    }

    public function test_the_scheme_check_still_applies_when_the_flag_is_enabled(): void
    {
        config()->set('monitoring.allow_private_targets', true);

        $this->assertFalse($this->guardWithRecords()->isAllowedUrl('file:///etc/passwd'));
    }

    public function test_private_targets_are_blocked_by_default(): void
    {
        config()->set('monitoring.allow_private_targets', false);

        $this->assertFalse($this->guardWithRecords()->isAllowedUrl('http://10.0.0.1/'));
    }
}
