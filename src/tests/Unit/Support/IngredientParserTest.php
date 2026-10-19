<?php

namespace Tests\Unit\Support;

use App\Support\IngredientParser;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class IngredientParserTest extends TestCase
{
    #[DataProvider('lines')]
    public function test_parse(string $line, ?float $qty, ?float $max, ?string $unit, string $name, ?string $note): void
    {
        $p = IngredientParser::parse($line);

        $this->assertEqualsWithDelta($qty, $p['quantity'], 1e-9, 'quantity');
        $this->assertEqualsWithDelta($max, $p['quantity_max'], 1e-9, 'max');
        $this->assertSame($unit, $p['unit'], 'unit');
        $this->assertSame($name, $p['name'], 'name');
        $this->assertSame($note, $p['note'], 'note');
        $this->assertSame(trim(preg_replace('/\s+/', ' ', $line)), $p['original_text']);
    }

    public static function lines(): array
    {
        return [
            'mixed fraction' => ['1 1/2 cups flour', 1.5, null, 'cup', 'flour', null],
            'glyph fraction' => ['½ tsp salt', 0.5, null, 'tsp', 'salt', null],
            'whole plus glyph' => ['1½ cups milk', 1.5, null, 'cup', 'milk', null],
            'decimal' => ['0.5 cup sugar', 0.5, null, 'cup', 'sugar', null],
            'range' => ['2-3 cloves garlic', 2.0, 3.0, 'clove', 'garlic', null],
            'range with to' => ['2 to 3 tbsp olive oil', 2.0, 3.0, 'tbsp', 'olive oil', null],
            'trailing note' => ['1 cup onion, finely chopped', 1.0, null, 'cup', 'onion', 'finely chopped'],
            'parenthetical' => ['1 tsp vanilla (optional)', 1.0, null, 'tsp', 'vanilla', 'optional'],
            'parenthetical size' => ['2 (14 oz) cans tomatoes', 2.0, null, 'can', 'tomatoes', '14 oz'],
            'capital T' => ['2 T butter', 2.0, null, 'tbsp', 'butter', null],
            'lowercase t' => ['1 t baking soda', 1.0, null, 'tsp', 'baking soda', null],
            'c dot' => ['3 c. flour', 3.0, null, 'cup', 'flour', null],
            'tablespoons' => ['3 tablespoons sugar', 3.0, null, 'tbsp', 'sugar', null],
            'attached unit' => ['200g flour', 200.0, null, 'g', 'flour', null],
            'ml' => ['250 ml milk', 250.0, null, 'ml', 'milk', null],
            'ounces' => ['8 ounces cream cheese', 8.0, null, 'oz', 'cream cheese', null],
            'pounds' => ['1 lb ground beef', 1.0, null, 'lb', 'ground beef', null],
            'fluid ounces' => ['4 fl oz cream', 4.0, null, 'floz', 'cream', null],
            'no unit' => ['3 eggs', 3.0, null, null, 'eggs', null],
            'adjective not a unit' => ['2 large eggs', 2.0, null, null, 'large eggs', null],
            'of is dropped' => ['1 pinch of salt', 1.0, null, 'pinch', 'salt', null],
            'no quantity' => ['Salt and pepper', null, null, null, 'Salt and pepper', null],
            'to taste' => ['salt to taste', null, null, null, 'salt', 'to taste'],
            'qty plus to taste' => ['1 tsp salt to taste', 1.0, null, 'tsp', 'salt', 'to taste'],
            'bullet' => ['- 2 cups rice', 2.0, null, 'cup', 'rice', null],
            'extra whitespace' => ['  2   cups   water ', 2.0, null, 'cup', 'water', null],
            'mixed dash' => ['1-1/2 cups sugar', 1.5, null, 'cup', 'sugar', null],
            'of after unit' => ['2 cups of water', 2.0, null, 'cup', 'water', null],
            'tomatoes is not a range' => ['2 tomatoes', 2.0, null, null, 'tomatoes', null],
        ];
    }
}
