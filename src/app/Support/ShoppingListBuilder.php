<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Combines the ingredients of several (scaled) recipes into one shopping list.
 *
 * Lines are merged when they are the same ingredient and the amounts can be added:
 *   - volume with volume (cups + tbsp + ml ...) and weight with weight, never volume with weight
 *   - counts of the same thing ("3 eggs" + "2 eggs")
 * Anything else stays on its own line. An ingredient with no amount ("salt to taste")
 * is listed once, unless the same ingredient also appears with an amount.
 */
final class ShoppingListBuilder
{
    /**
     * @param  iterable<array{ingredient: object, factor: float}>  $items
     * @param  string  $system  'us' or 'metric'
     * @return list<ScaledLine>
     */
    public static function build(iterable $items, string $system = 'us'): array
    {
        $system = $system === 'metric' ? 'metric' : 'us';
        $buckets = [];
        $hasAmount = [];

        foreach ($items as ['ingredient' => $ingredient, 'factor' => $factor]) {
            $name = Str::squish($ingredient->name);
            $norm = self::normalize($ingredient->name);

            if ($ingredient->quantity === null) {
                $buckets["$norm|none"] ??= ['kind' => 'none', 'name' => $name, 'total' => 0.0, 'unit' => null, 'norm' => $norm];

                continue;
            }

            // Use the top of a range ("2-3 cloves") so the list never comes up short.
            $amount = (float) ($ingredient->quantity_max ?? $ingredient->quantity) * $factor;
            $measure = UnitConverter::normalize($ingredient->unit);

            if ($measure !== null) {
                $type = UnitConverter::type($measure);
                $base = UnitConverter::convert($amount, $measure, $type === 'volume' ? 'ml' : 'g');
                $key = "$norm|$type";
                $buckets[$key] ??= ['kind' => $type, 'name' => $name, 'total' => 0.0, 'unit' => $type === 'volume' ? 'ml' : 'g', 'norm' => $norm];
                $buckets[$key]['total'] += $base;
            } else {
                $count = UnitConverter::normalizeCount($ingredient->unit) ?? ($ingredient->unit !== null ? strtolower(trim($ingredient->unit)) : '');
                $key = "$norm|count|$count";
                $buckets[$key] ??= ['kind' => 'count', 'name' => $name, 'total' => 0.0, 'unit' => $count !== '' ? $count : null, 'norm' => $norm];
                $buckets[$key]['total'] += $amount;
            }
            $hasAmount[$norm] = true;
        }

        $lines = [];
        foreach ($buckets as $bucket) {
            if ($bucket['kind'] === 'none') {
                if (! isset($hasAmount[$bucket['norm']])) {
                    $lines[] = new ScaledLine(null, null, $bucket['name'], null, false);
                }

                continue;
            }

            $name = $bucket['name'];
            if ($bucket['kind'] === 'count' && $bucket['unit'] === null) {
                // "1 egg" + "3 eggs" is "4 eggs", whichever spelling came first.
                $name = $bucket['total'] > 1.0 + 1e-9 ? Str::plural(Str::singular($name)) : Str::singular($name);
            }

            $pseudo = (object) [
                'quantity' => $bucket['total'], 'quantity_max' => null,
                'unit' => $bucket['unit'], 'name' => $name, 'note' => null,
            ];
            $lines[] = RecipeScaler::scale($pseudo, 1.0, $bucket['kind'] === 'count' ? 'original' : $system);
        }

        usort($lines, fn (ScaledLine $a, ScaledLine $b) => strcasecmp($a->name, $b->name));

        return $lines;
    }

    /** "Tomatoes " and "tomato" are the same thing. */
    private static function normalize(string $name): string
    {
        return Str::singular(Str::lower(Str::squish($name)));
    }
}
