<?php

namespace Tests\Unit\Support;

use App\Support\Fault\EventFingerprinter;
use PHPUnit\Framework\TestCase;

class EventFingerprinterTest extends TestCase
{
    public function test_it_prefers_the_innermost_in_app_frame_over_vendor_frames(): void
    {
        $payload = $this->exceptionPayload([
            ['filename' => 'app/Services/Billing.php', 'function' => 'charge', 'in_app' => true],
            ['filename' => 'vendor/laravel/framework/src/Pipeline.php', 'function' => 'handle'],
            ['filename' => 'vendor/guzzlehttp/guzzle/src/Client.php', 'function' => 'request'],
        ]);

        $this->assertSame(
            sha1('RuntimeException|app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_the_innermost_of_several_in_app_frames_is_used(): void
    {
        $payload = $this->exceptionPayload([
            ['filename' => 'app/Http/Controller.php', 'function' => 'store', 'in_app' => true],
            ['filename' => 'vendor/laravel/framework/src/Pipeline.php', 'function' => 'handle', 'in_app' => false],
            ['filename' => 'app/Services/Billing.php', 'function' => 'charge', 'in_app' => true],
        ]);

        $this->assertSame(
            sha1('RuntimeException|app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_the_same_bug_thrown_through_different_vendor_paths_groups_together(): void
    {
        $viaGuzzle = $this->exceptionPayload([
            ['filename' => 'app/Services/Billing.php', 'function' => 'charge'],
            ['filename' => 'vendor/guzzlehttp/guzzle/src/Client.php', 'function' => 'request'],
            ['filename' => 'vendor/laravel/framework/src/Pipeline.php', 'function' => 'handle'],
        ]);
        $viaHttpClient = $this->exceptionPayload([
            ['filename' => 'app/Services/Billing.php', 'function' => 'charge'],
            ['filename' => 'vendor/symfony/http-client/HttpClient.php', 'function' => 'send'],
        ]);

        $this->assertSame(EventFingerprinter::for($viaGuzzle), EventFingerprinter::for($viaHttpClient));
    }

    public function test_it_falls_back_to_the_innermost_non_vendor_frame_when_nothing_is_in_app(): void
    {
        $payload = $this->exceptionPayload([
            ['filename' => 'app/Services/Billing.php', 'function' => 'charge'],
            ['filename' => '/var/www/app/vendor/laravel/framework/src/Pipeline.php', 'function' => 'handle'],
            ['filename' => '/var/www/app/node_modules/lib/index.js', 'function' => 'run'],
        ]);

        $this->assertSame(
            sha1('RuntimeException|app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_it_treats_relative_vendor_paths_as_vendor(): void
    {
        $payload = $this->exceptionPayload([
            ['filename' => 'app/Services/Billing.php', 'function' => 'charge'],
            ['filename' => 'vendor/laravel/framework/src/Pipeline.php', 'function' => 'handle'],
        ]);

        $this->assertSame(
            sha1('RuntimeException|app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_it_falls_back_to_the_last_frame_when_every_frame_is_vendor_code(): void
    {
        $payload = $this->exceptionPayload([
            ['filename' => 'vendor/a/a/A.php', 'function' => 'outer'],
            ['filename' => 'vendor/b/b/B.php', 'function' => 'inner'],
        ]);

        $this->assertSame(
            sha1('RuntimeException|vendor/b/b/B.php|inner'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_it_uses_the_last_frame_when_frames_have_no_paths_at_all(): void
    {
        $payload = $this->exceptionPayload([
            ['function' => 'outer'],
            ['function' => 'inner'],
        ]);

        $this->assertSame(sha1('RuntimeException|inner'), EventFingerprinter::for($payload));
    }

    public function test_it_uses_abs_path_when_filename_is_missing(): void
    {
        $payload = $this->exceptionPayload([
            ['abs_path' => '/var/www/app/Services/Billing.php', 'function' => 'charge', 'in_app' => true],
        ]);

        $this->assertSame(
            sha1('RuntimeException|/var/www/app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_it_includes_the_module_when_filename_is_missing(): void
    {
        $payload = $this->exceptionPayload([
            ['module' => 'app.services.billing', 'function' => 'charge', 'in_app' => true],
        ]);

        $this->assertSame(
            sha1('RuntimeException|app.services.billing|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_it_does_not_include_the_module_when_filename_is_present(): void
    {
        $payload = $this->exceptionPayload([
            ['filename' => 'app/Services/Billing.php', 'module' => 'App\\Services\\Billing', 'function' => 'charge', 'in_app' => true],
        ]);

        $this->assertSame(
            sha1('RuntimeException|app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_it_uses_the_last_chained_exception_as_the_thrown_one(): void
    {
        $payload = [
            'exception' => [
                'values' => [
                    [
                        'type' => 'PDOException',
                        'stacktrace' => ['frames' => [['filename' => 'app/Repo.php', 'function' => 'find', 'in_app' => true]]],
                    ],
                    [
                        'type' => 'RuntimeException',
                        'stacktrace' => ['frames' => [['filename' => 'app/Services/Billing.php', 'function' => 'charge', 'in_app' => true]]],
                    ],
                ],
            ],
        ];

        $this->assertSame(
            sha1('RuntimeException|app/Services/Billing.php|charge'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_the_exception_message_does_not_affect_the_exception_fingerprint(): void
    {
        $first = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar', 'in_app' => true]], 'Order 1 failed');
        $second = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar', 'in_app' => true]], 'Order 2 failed');

        $this->assertSame(EventFingerprinter::for($first), EventFingerprinter::for($second));
    }

    public function test_an_exception_without_frames_groups_by_type(): void
    {
        $payload = ['exception' => ['values' => [['type' => 'LogicException']]]];

        $this->assertSame(sha1('LogicException'), EventFingerprinter::for($payload));
    }

    public function test_it_normalizes_numbers_in_messages(): void
    {
        $this->assertSame(
            EventFingerprinter::for(['message' => 'Order 42 failed after 3 retries']),
            EventFingerprinter::for(['message' => 'Order 99 failed after 15 retries']),
        );
        $this->assertSame(sha1('message|Order <n> failed'), EventFingerprinter::for(['message' => 'Order 42 failed']));
    }

    public function test_it_normalizes_uuids_to_a_placeholder(): void
    {
        $this->assertSame(
            sha1('message|Job <uuid> timed out'),
            EventFingerprinter::for(['message' => 'Job 3f2504e0-4f89-11d3-9a0c-0305e82c3301 timed out']),
        );
        $this->assertSame(
            EventFingerprinter::for(['message' => 'Job 3f2504e0-4f89-11d3-9a0c-0305e82c3301 timed out']),
            EventFingerprinter::for(['message' => 'Job 0b1c2d3e-aaaa-bbbb-cccc-ddddeeeeffff timed out']),
        );
    }

    public function test_it_normalizes_long_hex_strings_that_contain_a_digit(): void
    {
        $this->assertSame(
            sha1('message|Cache key <hex> missing'),
            EventFingerprinter::for(['message' => 'Cache key 9f86d081884c7d65 missing']),
        );
        $this->assertSame(
            sha1('message|Cache key <hex> missing'),
            EventFingerprinter::for(['message' => 'Cache key abcdef12 missing']),
        );
    }

    public function test_it_does_not_normalize_hex_words_without_a_digit_or_that_are_too_short(): void
    {
        $this->assertSame(sha1('message|Added deadbeef to the list'), EventFingerprinter::for(['message' => 'Added deadbeef to the list']));
        $this->assertSame(sha1('message|Code abc<n> rejected'), EventFingerprinter::for(['message' => 'Code abc1 rejected']));
    }

    public function test_it_normalizes_email_addresses(): void
    {
        $this->assertSame(
            sha1('message|Password reset sent to <email>'),
            EventFingerprinter::for(['message' => 'Password reset sent to jane.doe+test@example.com']),
        );
    }

    public function test_it_normalizes_ipv4_and_full_ipv6_addresses(): void
    {
        $this->assertSame(
            sha1('message|Rejected connection from <ip>'),
            EventFingerprinter::for(['message' => 'Rejected connection from 192.168.1.42']),
        );
        $this->assertSame(
            sha1('message|Rejected connection from <ip>'),
            EventFingerprinter::for(['message' => 'Rejected connection from 2001:0db8:85a3:0000:0000:8a2e:0370:7334']),
        );
    }

    public function test_it_does_not_normalize_version_numbers_into_ip_addresses(): void
    {
        $this->assertSame(sha1('message|Upgraded to <n>.<n>.<n>'), EventFingerprinter::for(['message' => 'Upgraded to 8.4.1']));
    }

    public function test_quoted_text_is_left_alone(): void
    {
        $this->assertSame(
            sha1('message|Lookup failed for "order 42" at <ip>'),
            EventFingerprinter::for(['message' => 'Lookup failed for "order 42" at 10.0.0.1']),
        );
        $this->assertSame(
            sha1("message|Unknown key '7f3a9c2b1d' for <n> rows"),
            EventFingerprinter::for(['message' => "Unknown key '7f3a9c2b1d' for 12 rows"]),
        );
    }

    public function test_messages_differing_only_by_variable_data_group_together(): void
    {
        $this->assertSame(
            EventFingerprinter::for(['message' => 'User 12 from 10.1.2.3 uploaded 5MB']),
            EventFingerprinter::for(['message' => 'User 980 from 172.16.0.9 uploaded 7MB']),
        );
    }

    public function test_the_message_template_is_preferred_for_fingerprinting(): void
    {
        $john = ['message' => ['message' => 'User %s has logged in!', 'formatted' => 'User John has logged in!']];
        $jane = ['message' => ['message' => 'User %s has logged in!', 'formatted' => 'User Jane has logged in!']];

        $this->assertSame(EventFingerprinter::for($john), EventFingerprinter::for($jane));
        $this->assertSame(sha1('message|User %s has logged in!'), EventFingerprinter::for($john));
    }

    public function test_the_logentry_template_is_preferred_for_fingerprinting(): void
    {
        $john = [
            'message' => 'User John has logged in!',
            'logentry' => ['message' => 'User %s has logged in!', 'params' => ['John']],
        ];
        $jane = [
            'message' => 'User Jane has logged in!',
            'logentry' => ['message' => 'User %s has logged in!', 'params' => ['Jane']],
        ];

        $this->assertSame(EventFingerprinter::for($john), EventFingerprinter::for($jane));
    }

    public function test_a_logentry_without_a_top_level_message_is_fingerprinted_as_a_message(): void
    {
        $payload = ['logentry' => ['message' => 'Queue %s stalled']];

        $this->assertSame(sha1('message|Queue %s stalled'), EventFingerprinter::for($payload));
    }

    public function test_the_formatted_message_is_used_when_no_template_exists(): void
    {
        $payload = ['message' => ['formatted' => 'Retry 3 of 5']];

        $this->assertSame(sha1('message|Retry <n> of <n>'), EventFingerprinter::for($payload));
    }

    public function test_exception_fingerprints_do_not_normalize_the_exception_value(): void
    {
        $payload = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar']], 'Order 42 failed');

        $this->assertSame(sha1('RuntimeException|app/Foo.php|bar'), EventFingerprinter::for($payload));
    }

    public function test_a_default_token_expands_to_the_default_fingerprint(): void
    {
        $payload = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar', 'in_app' => true]]);
        $payload['fingerprint'] = ['{{ default }}'];

        $this->assertSame(
            sha1('RuntimeException|app/Foo.php|bar'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_a_default_token_is_spliced_in_alongside_custom_parts(): void
    {
        $payload = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar', 'in_app' => true]]);
        $payload['fingerprint'] = ['{{ default }}', 'tenant-7'];

        $this->assertSame(
            sha1('RuntimeException|app/Foo.php|bar|tenant-7'),
            EventFingerprinter::for($payload),
        );
    }

    public function test_a_default_token_uses_the_message_default_for_message_events(): void
    {
        $payload = ['message' => 'Order 42 failed', 'fingerprint' => ['checkout', '{{ default }}']];

        $this->assertSame(sha1('checkout|message|Order <n> failed'), EventFingerprinter::for($payload));
    }

    public function test_a_default_token_is_not_substituted_when_the_fingerprint_is_otherwise_literal(): void
    {
        $payload = ['message' => 'Order 42 failed', 'fingerprint' => ['{{ default }}-custom']];

        $this->assertSame(sha1('{{ default }}-custom'), EventFingerprinter::for($payload));
    }

    public function test_explicit_fingerprints_without_the_default_token_are_hashed_unchanged(): void
    {
        $payload = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar', 'in_app' => true]]);
        $payload['fingerprint'] = ['same-issue', 42, 'other'];

        $this->assertSame(sha1('same-issue|42|other'), EventFingerprinter::for($payload));
    }

    public function test_an_empty_explicit_fingerprint_falls_back_to_the_default(): void
    {
        $payload = $this->exceptionPayload([['filename' => 'app/Foo.php', 'function' => 'bar', 'in_app' => true]]);
        $payload['fingerprint'] = [];

        $this->assertSame(sha1('RuntimeException|app/Foo.php|bar'), EventFingerprinter::for($payload));
    }

    public function test_events_with_no_exception_or_message_group_by_transaction_and_culprit(): void
    {
        $this->assertSame(
            sha1('unknown|GET /orders|OrderController@index'),
            EventFingerprinter::for(['transaction' => 'GET /orders', 'culprit' => 'OrderController@index']),
        );
    }

    /**
     * @param  list<array<string, mixed>>  $frames
     * @return array<string, mixed>
     */
    private function exceptionPayload(array $frames, string $value = 'Something broke'): array
    {
        return [
            'exception' => [
                'values' => [[
                    'type' => 'RuntimeException',
                    'value' => $value,
                    'stacktrace' => ['frames' => $frames],
                ]],
            ],
        ];
    }
}
