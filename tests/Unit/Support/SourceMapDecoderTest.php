<?php

namespace Tests\Unit\Support;

use App\Support\Fault\InvalidSourceMapException;
use App\Support\Fault\SourceMapDecoder;
use PHPUnit\Framework\TestCase;

class SourceMapDecoderTest extends TestCase
{
    private const BASE64 = 'ABCDEFGHIJKLMNOPQRSTUVWXYZabcdefghijklmnopqrstuvwxyz0123456789+/';

    /**
     * @param  array<int, int>  $values
     */
    public static function vlq(array $values): string
    {
        $out = '';

        foreach ($values as $value) {
            $value = $value < 0 ? ((-$value) << 1) | 1 : $value << 1;

            do {
                $digit = $value & 31;
                $value >>= 5;
                $out .= self::BASE64[$digit | ($value > 0 ? 32 : 0)];
            } while ($value > 0);
        }

        return $out;
    }

    /**
     * A map for a single minified line whose segments are (genCol, source, line, col, name).
     */
    public static function sampleMap(): string
    {
        $mappings = implode(',', [
            self::vlq([0, 0, 0, 0]),
            self::vlq([20, 0, 2, 4, 0]),
            self::vlq([10, 0, 0, 3, 1]),
        ]);

        return json_encode([
            'version' => 3,
            'file' => 'app.min.js',
            'sources' => ['./src/app.js'],
            'sourcesContent' => ["line one\nline two\n  throw boom();\nline four\nline five"],
            'names' => ['boom', 'other'],
            'mappings' => $mappings,
        ]);
    }

    public function test_it_encodes_and_decodes_known_vlq_values(): void
    {
        $this->assertSame('AAgBC', self::vlq([0, 0, 16, 1]));
        $this->assertSame('D', self::vlq([-1]));
    }

    public function test_it_resolves_generated_positions_to_original_ones(): void
    {
        $decoder = SourceMapDecoder::fromJson(self::sampleMap());

        $this->assertSame(['source' => 'src/app.js', 'line' => 1, 'column' => 0, 'name' => null], $decoder->lookup(1, 0));
        $this->assertSame(['source' => 'src/app.js', 'line' => 3, 'column' => 4, 'name' => 'boom'], $decoder->lookup(1, 20));
        $this->assertSame(['source' => 'src/app.js', 'line' => 3, 'column' => 4, 'name' => 'boom'], $decoder->lookup(1, 29));
        $this->assertSame(['source' => 'src/app.js', 'line' => 3, 'column' => 7, 'name' => 'other'], $decoder->lookup(1, 500));
    }

    public function test_it_returns_null_for_unmapped_lines(): void
    {
        $decoder = SourceMapDecoder::fromJson(self::sampleMap());

        $this->assertNull($decoder->lookup(2, 0));
        $this->assertNull($decoder->lookup(0, 0));
    }

    public function test_it_extracts_context_lines_from_sources_content(): void
    {
        $context = SourceMapDecoder::fromJson(self::sampleMap())->context('src/app.js', 3, 2);

        $this->assertSame(['line one', 'line two'], $context['pre_context']);
        $this->assertSame('  throw boom();', $context['context_line']);
        $this->assertSame(['line four', 'line five'], $context['post_context']);
        $this->assertNull(SourceMapDecoder::fromJson(self::sampleMap())->context('missing.js', 1));
    }

    public function test_it_rejects_index_maps_and_invalid_input(): void
    {
        $this->expectException(InvalidSourceMapException::class);
        $this->expectExceptionMessage('Index source maps');

        SourceMapDecoder::fromJson('{"version":3,"sections":[]}');
    }

    public function test_it_rejects_malformed_input(): void
    {
        foreach (['not json', '{"version":2,"sources":[],"mappings":""}', '{"version":3,"sources":["a.js"],"mappings":"A!"}', '{"version":3,"sources":["a.js"],"mappings":"ACAA"}'] as $json) {
            try {
                SourceMapDecoder::fromJson($json);
                $this->fail("Expected rejection of {$json}");
            } catch (InvalidSourceMapException) {
                $this->addToAssertionCount(1);
            }
        }
    }
}
