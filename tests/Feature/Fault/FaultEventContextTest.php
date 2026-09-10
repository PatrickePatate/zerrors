<?php

namespace Tests\Feature\Fault;

use App\Models\FaultEvent;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class FaultEventContextTest extends TestCase
{
    use RefreshDatabase;

    public function test_it_reads_the_captured_user_from_the_payload(): void
    {
        $event = FaultEvent::factory()->create([
            'payload' => ['user' => ['id' => 1, 'email' => 'demo@example.com']],
        ]);

        $this->assertSame(['id' => 1, 'email' => 'demo@example.com'], $event->contextUser());
    }

    public function test_it_prefers_the_frontend_reported_page_url_over_the_request_url(): void
    {
        $event = FaultEvent::factory()->create([
            'request' => [
                'url' => 'https://example.test/api/list',
                'headers' => ['x-current-page-url' => ['https://example.test/dashboard']],
            ],
        ]);

        $this->assertSame('https://example.test/dashboard', $event->contextUrl());
    }

    public function test_it_falls_back_to_the_request_url_without_a_reported_page_url(): void
    {
        $event = FaultEvent::factory()->create([
            'request' => ['url' => 'https://example.test/api/list'],
        ]);

        $this->assertSame('https://example.test/api/list', $event->contextUrl());
    }

    public function test_it_labels_common_browsers_from_the_user_agent_header(): void
    {
        $chrome = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['Mozilla/5.0 Chrome/120.0 Safari/537.36']]],
        ]);
        $firefox = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['Mozilla/5.0 Gecko/20100101 Firefox/154.0']]],
        ]);

        $this->assertSame('Chrome', $chrome->browserLabel());
        $this->assertSame('Firefox', $firefox->browserLabel());
    }

    public function test_it_flags_common_crawler_user_agents_as_a_likely_bot(): void
    {
        $bot = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']]],
        ]);
        $human = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['Mozilla/5.0 Chrome/120.0 Safari/537.36']]],
        ]);

        $this->assertTrue($bot->isLikelyBot());
        $this->assertFalse($human->isLikelyBot());
    }

    public function test_it_names_the_specific_crawler_instead_of_an_unknown_browser(): void
    {
        $googlebot = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['Mozilla/5.0 (compatible; Googlebot/2.1; +http://www.google.com/bot.html)']]],
        ]);
        $semrush = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['Mozilla/5.0 (compatible; SemrushBot/7~bl; +http://www.semrush.com/bot.html)']]],
        ]);
        $unnamedCrawler = FaultEvent::factory()->create([
            'request' => ['headers' => ['user-agent' => ['SomeInternalCrawler/1.0']]],
        ]);

        $this->assertSame('Googlebot', $googlebot->browserLabel());
        $this->assertSame('SemrushBot', $semrush->browserLabel());
        $this->assertSame('Crawler', $unnamedCrawler->browserLabel());
    }
}
