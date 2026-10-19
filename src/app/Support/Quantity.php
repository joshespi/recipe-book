<?php

namespace App\Support;

/**
 * Parsing and formatting of cooking quantities: "1 1/2", "1½", "2-3", 0.75 ...
 */
final class Quantity
{
    /** Vulgar fraction characters we understand when reading. */
    public const UNICODE = [
        '¼' => 0.25, '½' => 0.5, '¾' => 0.75,
        '⅐' => 1 / 7, '⅑' => 1 / 9, '⅒' => 0.1,
        '⅓' => 1 / 3, '⅔' => 2 / 3,
        '⅕' => 0.2, '⅖' => 0.4, '⅗' => 0.6, '⅘' => 0.8,
        '⅙' => 1 / 6, '⅚' => 5 / 6,
        '⅛' => 0.125, '⅜' => 0.375, '⅝' => 0.625, '⅞' => 0.875,
    ];

    /** Fractions we write. [value, glyph, isThird] */
    private const WRITE = [
        [0.0, '', false],
        [0.125, '⅛', false],
        [0.25, '¼', false],
        [1 / 3, '⅓', true],
        [0.375, '⅜', false],
        [0.5, '½', false],
        [0.625, '⅝', false],
        [2 / 3, '⅔', true],
        [0.75, '¾', false],
        [0.875, '⅞', false],
        [1.0, '', false],
    ];

    private const FR = '[\x{00BC}-\x{00BE}\x{2150}-\x{215E}]';

    /** A single number: mixed fraction, glyph fraction, fraction or decimal. */
    private static function numPattern(): string
    {
        $fr = self::FR;

        return '\d+\s+\d+\/\d+|\d+\s*'.$fr.'|\d+\/\d+|\d+(?:\.\d+)?|'.$fr;
    }

    /** Parses a whole string that is just one number. */
    public static function parse(string $text): ?float
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }
        $fr = self::FR;

        if (preg_match('/^(\d+)\s+(\d+)\/(\d+)$/', $text, $m)) {
            return $m[3] > 0 ? (int) $m[1] + $m[2] / $m[3] : null;
        }
        if (preg_match('/^(\d+)\s*('.$fr.')$/u', $text, $m)) {
            return (int) $m[1] + self::UNICODE[$m[2]];
        }
        if (preg_match('/^('.$fr.')$/u', $text, $m)) {
            return self::UNICODE[$m[1]];
        }
        if (preg_match('/^(\d+)\/(\d+)$/', $text, $m)) {
            return $m[2] > 0 ? $m[1] / $m[2] : null;
        }
        if (preg_match('/^\d+(?:\.\d+)?$/', $text)) {
            return (float) $text;
        }

        return null;
    }

    /**
     * Reads a quantity (or "2-3" / "2 to 3" range) from the start of $text.
     *
     * @return array{min: float, max: ?float, rest: string}|null
     */
    public static function parseLeading(string $text): ?array
    {
        $num = self::numPattern();
        $pattern = '/^\s*(?<a>(?:'.$num.'))(?:(?:\s*[-–—]\s*|\s+to\s+)(?<b>(?:'.$num.')))?\s*/u';
        if (! preg_match($pattern, $text, $m)) {
            return null;
        }

        $min = self::parse($m['a']);
        if ($min === null) {
            return null;
        }
        $max = isset($m['b']) && $m['b'] !== '' ? self::parse($m['b']) : null;

        if ($max !== null && $max < $min) {
            // "1-1/2 cups" is one and a half, not a range.
            if ($max < 1 && str_contains($m['b'], '/')) {
                $min += $max;
            }
            $max = null;
        } elseif ($max !== null && abs($max - $min) < 1e-9) {
            $max = null;
        }

        return ['min' => $min, 'max' => $max, 'rest' => substr($text, strlen($m[0]))];
    }

    /**
     * Writes a number as a whole number plus a kitchen fraction: 1.5 -> "1½".
     * A positive amount never shows as "0".
     */
    public static function format(float $value, bool $thirds = true): string
    {
        if ($value <= 0) {
            return '0';
        }
        $whole = (int) floor($value + 1e-9);
        $frac = max(0.0, $value - $whole);

        [$fv, $glyph] = self::nearest($frac, $thirds);
        if ($fv >= 1.0) {
            $whole++;
            $glyph = '';
        }
        if ($whole === 0 && $glyph === '') {
            $glyph = '⅛';
        }

        return $whole === 0 ? $glyph : $whole.$glyph;
    }

    /** The relative error made by writing $value with {@see format()}. */
    public static function formatError(float $value, bool $thirds = true): float
    {
        if ($value <= 0) {
            return 0.0;
        }
        $whole = floor($value + 1e-9);
        [$fv] = self::nearest(max(0.0, $value - $whole), $thirds);
        $written = max($whole + $fv, 0.125);

        return abs($written - $value) / $value;
    }

    /** @return array{0: float, 1: string} */
    private static function nearest(float $frac, bool $thirds): array
    {
        $best = null;
        foreach (self::WRITE as [$value, $glyph, $isThird]) {
            if ($isThird && ! $thirds) {
                continue;
            }
            $diff = abs($frac - $value);
            if ($best === null || $diff < $best[2] - 1e-12) {
                $best = [$value, $glyph, $diff];
            }
        }

        return [$best[0], $best[1]];
    }

    /** A plain decimal without trailing zeros: 2.50 -> "2.5". */
    public static function decimal(float $value, int $places = 2): string
    {
        $text = rtrim(rtrim(number_format($value, $places, '.', ''), '0'), '.');

        return $text === '' || $text === '-0' ? '0' : $text;
    }

    /** Sensible rounding for metric amounts of ml or g. */
    public static function roundMetric(float $value): float
    {
        $step = match (true) {
            $value < 10 => 0.5,
            $value < 50 => 1,
            $value < 200 => 5,
            default => 10,
        };

        return max($step, round($value / $step) * $step);
    }

    /** Writes "min" or "min–max" using $writer for each number. */
    public static function range(float $min, ?float $max, callable $writer): string
    {
        $a = $writer($min);
        if ($max === null) {
            return $a;
        }
        $b = $writer($max);

        return $a === $b ? $a : $a.'–'.$b;
    }
}
