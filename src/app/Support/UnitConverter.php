<?php

namespace App\Support;

/**
 * Measurement units: recognising them, converting between them and choosing the
 * unit that reads best. Volume and weight are never converted into each other.
 */
final class UnitConverter
{
    /** key => [type, system, size in base unit (ml for volume, g for weight)] */
    private const UNITS = [
        'tsp' => ['volume', 'us', 4.92892159375],
        'tbsp' => ['volume', 'us', 14.78676478125],
        'floz' => ['volume', 'us', 29.5735295625],
        'cup' => ['volume', 'us', 236.5882365],
        'pt' => ['volume', 'us', 473.176473],
        'qt' => ['volume', 'us', 946.352946],
        'gal' => ['volume', 'us', 3785.411784],
        'ml' => ['volume', 'metric', 1.0],
        'l' => ['volume', 'metric', 1000.0],
        'oz' => ['weight', 'us', 28.349523125],
        'lb' => ['weight', 'us', 453.59237],
        'g' => ['weight', 'metric', 1.0],
        'kg' => ['weight', 'metric', 1000.0],
    ];

    private const ALIASES = [
        'tsp' => ['tsp', 'tsps', 'teaspoon', 'teaspoons'],
        'tbsp' => ['tbsp', 'tbsps', 'tbs', 'tbl', 'tbspn', 'tablespoon', 'tablespoons'],
        'floz' => ['floz', 'fluidounce', 'fluidounces'],
        'cup' => ['cup', 'cups', 'c'],
        'pt' => ['pt', 'pint', 'pints'],
        'qt' => ['qt', 'quart', 'quarts'],
        'gal' => ['gal', 'gallon', 'gallons'],
        'ml' => ['ml', 'milliliter', 'milliliters', 'millilitre', 'millilitres'],
        'l' => ['l', 'liter', 'liters', 'litre', 'litres'],
        'oz' => ['oz', 'ounce', 'ounces'],
        'lb' => ['lb', 'lbs', 'pound', 'pounds'],
        'g' => ['g', 'gm', 'gram', 'grams'],
        'kg' => ['kg', 'kgs', 'kilogram', 'kilograms'],
    ];

    /** Units that "Original" scaling may swap for a better-reading neighbour. */
    private const SMART = ['tsp', 'tbsp', 'cup', 'ml', 'l', 'g', 'kg', 'oz', 'lb'];

    private const LABELS = [
        'tsp' => 'tsp', 'tbsp' => 'tbsp', 'floz' => 'fl oz', 'cup' => 'cup', 'pt' => 'pt', 'qt' => 'qt',
        'gal' => 'gal', 'ml' => 'ml', 'l' => 'l', 'oz' => 'oz', 'lb' => 'lb', 'g' => 'g', 'kg' => 'kg',
    ];

    /** Countable units that are kept as-is (singular => plural). */
    private const COUNT = [
        'clove' => 'cloves', 'can' => 'cans', 'package' => 'packages', 'packet' => 'packets',
        'slice' => 'slices', 'stick' => 'sticks', 'pinch' => 'pinches', 'dash' => 'dashes',
        'bunch' => 'bunches', 'sprig' => 'sprigs', 'head' => 'heads', 'piece' => 'pieces',
        'jar' => 'jars', 'bottle' => 'bottles', 'fillet' => 'fillets', 'stalk' => 'stalks',
        'handful' => 'handfuls', 'bag' => 'bags', 'sheet' => 'sheets', 'cube' => 'cubes',
        'ear' => 'ears', 'strip' => 'strips', 'leaf' => 'leaves', 'knob' => 'knobs',
        'drop' => 'drops', 'rib' => 'ribs', 'wedge' => 'wedges', 'bulb' => 'bulbs',
        'bar' => 'bars', 'box' => 'boxes',
    ];

    /** Maps a typed unit ("Tbsp.", "cups", "T") to a measuring-unit key, or null. */
    public static function normalize(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }
        $unit = trim($unit);
        $unit = rtrim($unit, '.');
        if ($unit === '') {
            return null;
        }
        if ($unit === 'T') {
            return 'tbsp';
        }
        if ($unit === 't') {
            return 'tsp';
        }
        if ($unit === 'L') {
            return 'l';
        }
        $lower = strtolower(str_replace(['.', ' ', '-'], '', $unit));
        if (isset(self::UNITS[$lower])) {
            return $lower;
        }
        foreach (self::ALIASES as $key => $aliases) {
            if (in_array($lower, $aliases, true)) {
                return $key;
            }
        }

        return null;
    }

    /** Maps a typed countable unit ("cloves") to its singular key, or null. */
    public static function normalizeCount(?string $unit): ?string
    {
        if ($unit === null) {
            return null;
        }
        $lower = strtolower(rtrim(trim($unit), '.'));
        if (isset(self::COUNT[$lower])) {
            return $lower;
        }
        $singular = array_search($lower, self::COUNT, true);

        return $singular === false ? null : $singular;
    }

    public static function pluralCount(string $singular): string
    {
        return self::COUNT[$singular] ?? $singular.'s';
    }

    public static function type(string $key): string
    {
        return self::UNITS[$key][0];
    }

    public static function system(string $key): string
    {
        return self::UNITS[$key][1];
    }

    public static function isSmart(string $key): bool
    {
        return in_array($key, self::SMART, true);
    }

    public static function label(string $key, bool $plural = false): string
    {
        if ($key === 'cup' && $plural) {
            return 'cups';
        }

        return self::LABELS[$key];
    }

    /** Converts an amount between two units of the same type (tsp -> ml, oz -> g ...). */
    public static function convert(float $amount, string $from, string $to): float
    {
        if (self::type($from) !== self::type($to)) {
            throw new \InvalidArgumentException("Cannot convert {$from} to {$to}.");
        }

        return $amount * self::UNITS[$from][2] / self::UNITS[$to][2];
    }

    /**
     * Chooses the unit and amounts that read best for $target ('us' | 'metric' | 'original').
     *
     * @return array{unit: string, qty: float, max: ?float, style: string} style is 'us' (fractions) or 'metric' (rounded decimals)
     */
    public static function present(float $qty, ?float $max, string $unit, string $target): array
    {
        $type = self::type($unit);
        $system = $target === 'original' ? self::system($unit) : $target;
        $base = $qty * self::UNITS[$unit][2];

        $baseUnit = $type === 'volume' ? 'ml' : 'g';
        $chosen = $system === 'metric'
            ? self::pickMetric($base, $baseUnit)
            : ($type === 'volume' ? self::pickUsVolume($base) : self::pickUsWeight($base));

        return [
            'unit' => $chosen,
            'qty' => self::convert($qty, $unit, $chosen),
            'max' => $max === null ? null : self::convert($max, $unit, $chosen),
            'style' => $system === 'metric' ? 'metric' : 'us',
        ];
    }

    private static function pickMetric(float $base, string $baseUnit): string
    {
        if ($base >= 995) {
            return $baseUnit === 'ml' ? 'l' : 'kg';
        }

        return $baseUnit;
    }

    private static function pickUsVolume(float $ml): string
    {
        $tsp = $ml / self::UNITS['tsp'][2];
        if ($tsp < 2.9) {
            return 'tsp';
        }

        $tbsp = $ml / self::UNITS['tbsp'][2];
        if ($tbsp < 3.9) {
            return 'tbsp';
        }

        // Use cups only if the fraction written is within 3% of the real amount
        // (4½ tbsp is clearer than "¼ cup" ).
        $cups = $ml / self::UNITS['cup'][2];

        return Quantity::formatError($cups) <= 0.03 ? 'cup' : 'tbsp';
    }

    private static function pickUsWeight(float $g): string
    {
        $oz = $g / self::UNITS['oz'][2];
        if ($oz < 15.5) {
            return 'oz';
        }
        $lb = $g / self::UNITS['lb'][2];

        return Quantity::formatError($lb, false) <= 0.03 ? 'lb' : 'oz';
    }
}
