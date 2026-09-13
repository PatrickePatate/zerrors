<?php

namespace Tests\Unit\Support\Fault;

use App\Models\FaultEvent;
use App\Support\Fault\CurlCommandFormatter;
use PHPUnit\Framework\TestCase;

class CurlCommandFormatterTest extends TestCase
{
    public function test_it_builds_a_get_request_with_headers_and_query_string(): void
    {
        $event = new FaultEvent([
            'request' => [
                'method' => 'GET',
                'url' => 'https://example.test/api/list',
                'query_string' => 'page=2',
                'headers' => ['Accept' => 'application/json', 'User-Agent' => 'test-agent'],
            ],
        ]);

        $command = CurlCommandFormatter::format($event);

        $this->assertStringContainsString("curl 'https://example.test/api/list?page=2'", $command);
        $this->assertStringContainsString('-X GET', $command);
        $this->assertStringContainsString("-H 'Accept: application/json'", $command);
        $this->assertStringContainsString("-H 'User-Agent: test-agent'", $command);
    }

    public function test_it_includes_the_json_encoded_body_for_array_data(): void
    {
        $event = new FaultEvent([
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.test/api/orders',
                'data' => ['amount' => -5],
            ],
        ]);

        $command = CurlCommandFormatter::format($event);

        $this->assertStringContainsString('-X POST', $command);
        $this->assertStringContainsString('-d \'{"amount":-5}\'', $command);
    }

    public function test_it_passes_a_raw_string_body_through_unchanged(): void
    {
        $event = new FaultEvent([
            'request' => [
                'method' => 'POST',
                'url' => 'https://example.test/api/orders',
                'data' => 'amount=5&currency=usd',
            ],
        ]);

        $command = CurlCommandFormatter::format($event);

        $this->assertStringContainsString("-d 'amount=5&currency=usd'", $command);
    }

    public function test_it_omits_the_data_flag_when_there_is_no_body(): void
    {
        $event = new FaultEvent([
            'request' => [
                'method' => 'GET',
                'url' => 'https://example.test/api/list',
                'headers' => ['Accept' => 'application/json'],
            ],
        ]);

        $this->assertStringNotContainsString('-d ', CurlCommandFormatter::format($event));
    }
}
