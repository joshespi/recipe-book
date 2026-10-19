<?php

namespace Tests\Unit\Support;

use App\Support\RecipeScaler;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

class RecipeScalerTest extends TestCase
{
    private function line(?float $qty, ?string $unit, string $name = 'thing', ?float $max = null, ?string $note = null): object
    {
        return (object) ['quantity' => $qty, 'quantity_max' => $max, 'unit' => $unit, 'name' => $name, 'note' => $note];
    }

    #[DataProvider('cases')]
    public function test_scaling_and_conversion(?float $qty, ?string $unit, float $factor, string $system, string $expected, ?float $max = null): void
    {
        $line = RecipeScaler::scale($this->line($qty, $unit, 'x', $max), $factor, $system);
        $this->assertSame($expected, trim(($line->quantity ?? '').' '.($line->unit ?? '')));
    }

    public static function cases(): array
    {
        return [
            'half of 1/3 cup' => [1 / 3, 'cup', 0.5, 'original', '2⅔ tbsp'],
            'double 3/4 tsp' => [0.75, 'tsp', 2, 'original', '1½ tsp'],
            'triple 2 tbsp is 3/8 cup' => [2, 'tbsp', 3, 'original', '⅜ cup'],
            'double 1 cup' => [1, 'cup', 2, 'original', '2 cups'],
            'half 1 cup' => [1, 'cup', 0.5, 'original', '½ cup'],
            'half 1 tbsp' => [1, 'tbsp', 0.5, 'original', '1½ tsp'],
            'half 1/2 tsp' => [0.5, 'tsp', 0.5, 'original', '¼ tsp'],
            '48 tsp becomes a cup' => [16, 'tbsp', 1, 'us', '1 cup'],
            '4 1/2 tbsp stays tbsp' => [4.5, 'tbsp', 1, 'us', '4½ tbsp'],
            '4 tbsp is a quarter cup' => [4, 'tbsp', 1, 'us', '¼ cup'],
            'unchanged original is exact' => [1.5, 'cup', 1, 'original', '1½ cups'],
            'original 1x metric is untouched' => [237, 'ml', 1, 'original', '237 ml'],
            'cup to metric' => [1, 'cup', 1, 'metric', '240 ml'],
            'half cup to metric' => [0.5, 'cup', 1, 'metric', '120 ml'],
            'tsp to metric' => [1, 'tsp', 1, 'metric', '5 ml'],
            'tbsp to metric' => [1, 'tbsp', 1, 'metric', '15 ml'],
            'quarter tsp to metric' => [0.25, 'tsp', 1, 'metric', '1 ml'],
            'four and a quarter cups to litres' => [4.25, 'cup', 1, 'metric', '1 l'],
            'oz to grams' => [8, 'oz', 1, 'metric', '230 g'],
            'lb to grams' => [1, 'lb', 1, 'metric', '450 g'],
            'many lb to kg' => [3, 'lb', 1, 'metric', '1.35 kg'],
            'grams to oz' => [100, 'g', 1, 'us', '3½ oz'],
            'grams to lb' => [1000, 'g', 1, 'us', '2¼ lb'],
            'ml to cups' => [480, 'ml', 1, 'us', '2 cups'],
            'ml to tbsp' => [30, 'ml', 1, 'us', '2 tbsp'],
            'triple 500 g becomes kg' => [500, 'g', 3, 'original', '1.5 kg'],
            'half 1 l' => [1, 'l', 0.5, 'original', '500 ml'],
            'eggs scale' => [3, null, 0.5, 'original', '1½'],
            'eggs double' => [3, null, 2, 'metric', '6'],
            'cloves pluralise' => [1, 'clove', 2, 'original', '2 cloves'],
            'cloves singular' => [2, 'cloves', 0.5, 'original', '1 clove'],
            'can is never converted' => [1, 'can', 2, 'metric', '2 cans'],
            'range scales' => [2, 'cup', 2, 'original', '4–6 cups', 3.0],
            'range with fractions' => [1, 'tbsp', 0.5, 'original', '1½–3 tsp', 2.0],
            'null quantity' => [null, null, 3, 'original', ''],
            'quart is kept in original' => [1, 'qt', 2, 'original', '2 qt'],
        ];
    }

    public function test_null_quantity_is_never_scaled(): void
    {
        $line = RecipeScaler::scale($this->line(null, null, 'Salt', null, 'to taste'), 4.0, 'metric');
        $this->assertNull($line->quantity);
        $this->assertFalse($line->scaled);
        $this->assertSame('Salt, to taste', $line->text());
    }

    public function test_text_joins_quantity_unit_name_and_note(): void
    {
        $line = RecipeScaler::scale($this->line(2, 'cup', 'flour', null, 'sifted'), 1.0);
        $this->assertSame('2 cups flour, sifted', $line->text());
    }

    public function test_factor_for_servings(): void
    {
        $this->assertSame(2.0, RecipeScaler::factorForServings(4, 8));
        $this->assertSame(1.0, RecipeScaler::factorForServings(null, 8));
        $this->assertSame(1.0, RecipeScaler::factorForServings(0, 8));
    }

    public function test_unknown_system_falls_back_to_original(): void
    {
        $line = RecipeScaler::scale($this->line(1, 'cup'), 1.0, 'bogus');
        $this->assertSame('1', $line->quantity);
        $this->assertSame('cup', $line->unit);
    }

    public function test_clamp_factor(): void
    {
        $this->assertSame(0.125, RecipeScaler::clampFactor(0.0));
        $this->assertSame(50.0, RecipeScaler::clampFactor(5000));
        $this->assertSame(2.0, RecipeScaler::clampFactor(2));
    }
}
