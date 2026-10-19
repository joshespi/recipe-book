<?php

namespace App\Livewire\Recipes;

use App\Models\Recipe;
use App\Support\Quantity;
use App\Support\RecipeScaler;
use Livewire\Attributes\Url;
use Livewire\Component;

/**
 * Ingredients and steps with a scale (½×, 2×, custom servings) and a US/metric switch.
 * Works for guests on the share page: it only reads, and the scale lives in the URL.
 */
class Scaler extends Component
{
    public Recipe $recipe;

    #[Url(as: 'scale')]
    public string $scale = '1';

    #[Url(as: 'units')]
    public string $units = 'original';

    public function mount(Recipe $recipe): void
    {
        $this->recipe = $recipe;
        $this->normalize();
    }

    public function updated(): void
    {
        $this->normalize();
    }

    /** Keeps the URL state valid: a known unit system and the scale as a plain number ("0.5", "2"). */
    private function normalize(): void
    {
        if (! in_array($this->units, RecipeScaler::SYSTEMS, true)) {
            $this->units = 'original';
        }
        $this->scale = Quantity::decimal($this->factor(), 3);
    }

    public function factor(): float
    {
        return RecipeScaler::factorFromInput($this->scale);
    }

    public function setScale(string $value): void
    {
        $this->scale = $value;
        $this->normalize();
    }

    public function setUnits(string $units): void
    {
        $this->units = $units;
        $this->normalize();
    }

    /** Moves the target servings up or down by one and converts it back into a scale. */
    public function stepServings(int $direction): void
    {
        $base = $this->recipe->servings;
        if (! $base) {
            return;
        }
        $current = $base * $this->factor();
        $target = $direction < 0 ? ceil($current - 1.0001) : floor($current + 1.0001);
        $target = max($target, 1);
        $this->setScale((string) ($target / $base));
    }

    public function render()
    {
        $factor = $this->factor();
        $recipe = $this->recipe->loadMissing(['steps']);

        return view('livewire.recipes.scaler', [
            'sections' => RecipeScaler::sections($recipe, $factor, $this->units),
            'factor' => $factor,
            'servings' => $recipe->servings ? $recipe->servings * $factor : null,
            'presets' => ['0.5' => '½×', '1' => '1×', '2' => '2×', '3' => '3×'],
        ]);
    }
}
