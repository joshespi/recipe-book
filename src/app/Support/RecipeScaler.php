<?php

namespace App\Support;

/**
 * Scales ingredient amounts and presents them in the chosen unit system.
 *
 * Amounts without a quantity ("salt to taste") are never scaled.
 */
final class RecipeScaler
{
    public const SYSTEMS = ['original', 'us', 'metric'];

    /** The factor needed to turn a recipe written for $from servings into $to servings. */
    public static function factorForServings(?float $from, ?float $to): float
    {
        if (! $from || $from <= 0 || ! $to || $to <= 0) {
            return 1.0;
        }

        return $to / $from;
    }

    /** Clamps a requested factor to something sensible. */
    public static function clampFactor(float $factor): float
    {
        return min(max($factor, 0.125), 50.0);
    }

    /** Reads a typed scale such as "2", "0.5", "3/4" or "2×"; anything unusable means 1. */
    public static function factorFromInput(?string $raw): float
    {
        $raw = trim(str_replace(['×', 'x', 'X'], '', (string) $raw));
        $value = Quantity::parse($raw);
        if ($value === null && is_numeric($raw)) {
            $value = (float) $raw;
        }

        return self::clampFactor($value && $value > 0 ? $value : 1.0);
    }

    /**
     * The recipe ingredients grouped for display and scaled.
     *
     * @return list<array{name: ?string, lines: \Illuminate\Support\Collection<int, ScaledLine>}>
     */
    public static function sections(\App\Models\Recipe $recipe, float $factor, string $system): array
    {
        $recipe->loadMissing(['ingredientGroups', 'ingredients']);
        $scale = fn ($ingredients) => $ingredients->map(fn ($i) => self::scale($i, $factor, $system))->values();

        $sections = [];
        $loose = $recipe->ingredients->whereNull('ingredient_group_id');
        if ($loose->isNotEmpty()) {
            $sections[] = ['name' => null, 'lines' => $scale($loose)];
        }
        foreach ($recipe->ingredientGroups as $group) {
            $sections[] = ['name' => $group->name, 'lines' => $scale($recipe->ingredients->where('ingredient_group_id', $group->id))];
        }

        return $sections;
    }

    /**
     * @param  object{quantity: ?float, quantity_max: ?float, unit: ?string, name: string, note: ?string}  $ingredient
     */
    public static function scale(object $ingredient, float $factor, string $system = 'original'): ScaledLine
    {
        if (! in_array($system, self::SYSTEMS, true)) {
            $system = 'original';
        }

        $name = $ingredient->name;
        $note = $ingredient->note ?: null;

        if ($ingredient->quantity === null) {
            return new ScaledLine(null, self::countLabel($ingredient->unit, 2), $name, $note, false);
        }

        $qty = (float) $ingredient->quantity * $factor;
        $max = $ingredient->quantity_max === null ? null : (float) $ingredient->quantity_max * $factor;
        $measure = UnitConverter::normalize($ingredient->unit);

        // Counts and unrecognised units (eggs, cloves, a "can"): scale, never convert.
        if ($measure === null) {
            $text = Quantity::range($qty, $max, fn (float $v) => Quantity::format($v));

            return new ScaledLine($text, self::countLabel($ingredient->unit, $max ?? $qty), $name, $note, true);
        }

        $unchanged = abs($factor - 1.0) < 1e-9;
        $keepUnit = $system === 'original' && ($unchanged || ! UnitConverter::isSmart($measure));

        if ($keepUnit) {
            $metric = UnitConverter::system($measure) === 'metric';
            $m = ['unit' => $measure, 'qty' => $qty, 'max' => $max, 'style' => $metric ? 'plain' : 'us'];
        } else {
            $m = UnitConverter::present($qty, $max, $measure, $system);
        }

        $thirds = UnitConverter::type($m['unit']) === 'volume';
        $writer = match ($m['style']) {
            'metric' => fn (float $v) => self::metricText($v, $m['unit']),
            'plain' => fn (float $v) => Quantity::decimal($v),
            default => fn (float $v) => Quantity::format($v, $thirds),
        };

        $text = Quantity::range($m['qty'], $m['max'], $writer);
        $shown = $m['max'] ?? $m['qty'];
        $plural = $shown > 1.0 + 1e-9;

        return new ScaledLine($text, UnitConverter::label($m['unit'], $plural), $name, $note, ! $unchanged || $system !== 'original');
    }

    private static function metricText(float $value, string $unit): string
    {
        if ($unit === 'l' || $unit === 'kg') {
            return Quantity::decimal(round($value / 0.05) * 0.05, 2);
        }

        return Quantity::decimal(Quantity::roundMetric($value), 1);
    }

    /** The countable unit (clove, can ...) in singular or plural, or the unit exactly as typed. */
    private static function countLabel(?string $unit, float $amount): ?string
    {
        if ($unit === null || trim($unit) === '') {
            return null;
        }
        $count = UnitConverter::normalizeCount($unit);
        if ($count === null) {
            return $unit;
        }

        return $amount > 1.0 + 1e-9 ? UnitConverter::pluralCount($count) : $count;
    }
}
