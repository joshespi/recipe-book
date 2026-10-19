<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * Turns a free-text ingredient line ("1 1/2 cups flour, sifted") into parts.
 */
final class IngredientParser
{
    /**
     * Parses a block of lines. A line starting with "#" (or a text line ending in a colon,
     * like "For the sauce:") starts a new group; the lines after it belong to that group.
     *
     * @return list<array{group: ?string, parsed: array}>
     */
    public static function parseBlock(string $text): array
    {
        $rows = [];
        $group = null;

        foreach (preg_split('/\R/u', $text) as $line) {
            $line = trim($line);
            if ($line === '') {
                continue;
            }

            if (preg_match('/^#+\s*(.+?)\s*:?$/u', $line, $m)) {
                $group = $m[1];

                continue;
            }
            if (preg_match('/^(\p{L}[^:]*):$/u', $line, $m)) {
                $group = trim($m[1]);

                continue;
            }

            $rows[] = ['group' => $group, 'parsed' => self::parse($line)];
        }

        return $rows;
    }

    /**
     * @return array{quantity: ?float, quantity_max: ?float, unit: ?string, name: string, note: ?string, original_text: string}
     */
    public static function parse(string $line): array
    {
        $original = Str::squish($line);
        $text = trim(preg_replace('/^[\-\*\x{2022}\x{2013}]\s+/u', '', $original));

        $qty = $max = null;
        $unit = null;
        $notes = [];

        $lead = Quantity::parseLeading($text);
        if ($lead !== null) {
            $qty = $lead['min'];
            $max = $lead['max'];
            $text = $lead['rest'];

            // "2 (14 oz) cans tomatoes": the parenthetical belongs in the note, not the name.
            if (preg_match('/^\(([^)]*)\)\s*/u', $text, $m)) {
                $notes[] = trim($m[1]);
                $text = substr($text, strlen($m[0]));
            }

            [$unit, $text] = self::takeUnit($text);
        }

        // Remaining "(optional)" style parentheticals.
        $text = preg_replace_callback('/\(([^)]*)\)/u', function ($m) use (&$notes) {
            if (trim($m[1]) !== '') {
                $notes[] = trim($m[1]);
            }

            return ' ';
        }, $text);

        $text = Str::squish($text);
        $text = preg_replace('/^of\s+/iu', '', $text);

        // ", finely chopped"
        if (str_contains($text, ',')) {
            [$text, $after] = array_map('trim', explode(',', $text, 2));
            if ($after !== '') {
                $notes[] = $after;
            }
        }

        // "salt to taste"
        if (preg_match('/^(.*?)\s+(to taste|as needed|for serving|for garnish|for dusting)$/iu', $text, $m) && $m[1] !== '') {
            $text = $m[1];
            array_unshift($notes, $m[2]);
        }

        $name = trim($text, " \t,;:-");
        if ($name === '') {
            $name = $original;
        }

        return [
            'quantity' => $qty,
            'quantity_max' => $max,
            'unit' => $unit,
            'name' => $name,
            'note' => $notes ? implode(', ', $notes) : null,
            'original_text' => $original,
        ];
    }

    /** @return array{0: ?string, 1: string} unit key (or count word) and the remaining text */
    private static function takeUnit(string $text): array
    {
        // Two-word units first.
        if (preg_match('/^(fl\.?\s*oz\.?|fluid\s+ounces?)\b\.?\s*/iu', $text, $m)) {
            return ['floz', substr($text, strlen($m[0]))];
        }

        if (! preg_match('/^([\p{L}]+)\.?(?=\s|$|,|\()\s*/u', $text, $m)) {
            return [null, $text];
        }

        $word = $m[1];
        $rest = substr($text, strlen($m[0]));

        if (($measure = UnitConverter::normalize($word)) !== null) {
            return [$measure, $rest];
        }
        if (($count = UnitConverter::normalizeCount($word)) !== null) {
            return [$count, $rest];
        }

        return [null, $text];
    }
}
