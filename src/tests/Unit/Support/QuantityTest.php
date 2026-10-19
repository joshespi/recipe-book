<?php

namespace Tests\Unit\Support;

use App\Support\Quantity;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class QuantityTest extends TestCase
{
    #[DataProvider('parseCases')]
    public function test_parse(string $text, ?float $expected): void
    {
        $actual = Quantity::parse($text);
        $expected === null ? $this->assertNull($actual) : $this->assertEqualsWithDelta($expected, $actual, 1e-9);
    }

    public static function parseCases(): array
    {
        return [
            'whole' => ['2', 2.0],
            'decimal' => ['0.75', 0.75],
            'fraction' => ['1/3', 1 / 3],
            'mixed' => ['1 1/2', 1.5],
            'glyph' => ['½', 0.5],
            'whole + glyph' => ['1½', 1.5],
            'whole space glyph' => ['2 ¾', 2.75],
            'zero denominator' => ['1/0', null],
            'words' => ['some', null],
            'empty' => ['', null],
        ];
    }

    public function test_parse_leading_reads_quantity_and_returns_the_rest(): void
    {
        $r = Quantity::parseLeading('1 1/2 cups flour');
        $this->assertSame(1.5, $r['min']);
        $this->assertNull($r['max']);
        $this->assertSame('cups flour', $r['rest']);
    }

    public function test_parse_leading_reads_ranges(): void
    {
        $r = Quantity::parseLeading('2-3 cloves garlic');
        $this->assertSame([2.0, 3.0, 'cloves garlic'], [$r['min'], $r['max'], $r['rest']]);

        $r = Quantity::parseLeading('2 to 3 tbsp oil');
        $this->assertSame([2.0, 3.0], [$r['min'], $r['max']]);

        $r = Quantity::parseLeading('1½–2 cups milk');
        $this->assertSame([1.5, 2.0], [$r['min'], $r['max']]);
    }

    public function test_dash_before_a_fraction_is_a_mixed_number_not_a_range(): void
    {
        $r = Quantity::parseLeading('1-1/2 cups sugar');
        $this->assertSame(1.5, $r['min']);
        $this->assertNull($r['max']);
    }

    public function test_a_word_that_starts_with_to_is_not_a_range(): void
    {
        $r = Quantity::parseLeading('2 tomatoes');
        $this->assertSame(2.0, $r['min']);
        $this->assertNull($r['max']);
        $this->assertSame('tomatoes', $r['rest']);
    }

    public function test_parse_leading_returns_null_without_a_number(): void
    {
        $this->assertNull(Quantity::parseLeading('salt and pepper'));
    }

    #[DataProvider('formatCases')]
    public function test_format(float $value, string $expected): void
    {
        $this->assertSame($expected, Quantity::format($value));
    }

    public static function formatCases(): array
    {
        return [
            [2.0, '2'],
            [1.5, '1½'],
            [0.5, '½'],
            [1 / 3, '⅓'],
            [2 / 3, '⅔'],
            [0.375, '⅜'],
            [1.25, '1¼'],
            [2.97, '3'],
            [0.99, '1'],
            [0.02, '⅛'],
            [0.0, '0'],
        ];
    }

    public function test_format_without_thirds_uses_eighths(): void
    {
        $this->assertSame('⅜', Quantity::format(1 / 3, false));
    }

    public function test_decimal_trims_zeros(): void
    {
        $this->assertSame('2.5', Quantity::decimal(2.50));
        $this->assertSame('3', Quantity::decimal(3.0));
        $this->assertSame('0.33', Quantity::decimal(1 / 3));
    }

    #[DataProvider('metricCases')]
    public function test_round_metric(float $value, float $expected): void
    {
        $this->assertEqualsWithDelta($expected, Quantity::roundMetric($value), 1e-9);
    }

    public static function metricCases(): array
    {
        return [
            [1.23, 1.0],
            [2.46, 2.5],
            [4.93, 5.0],
            [14.79, 15.0],
            [28.35, 28.0],
            [59.1, 60.0],
            [118.3, 120.0],
            [236.59, 240.0],
            [453.6, 450.0],
        ];
    }

    public function test_range_collapses_equal_ends(): void
    {
        $this->assertSame('2–3', Quantity::range(2, 3, fn ($v) => (string) $v));
        $this->assertSame('2', Quantity::range(2, 2, fn ($v) => (string) $v));
        $this->assertSame('2', Quantity::range(2, null, fn ($v) => (string) $v));
    }
}
