<?php

namespace App\Livewire\Recipes;

use App\Models\Recipe;
use App\Support\Quantity;
use App\Support\RecipeScaler;
use Livewire\Attributes\Computed;
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

    private function normalize(): void
    {
        if (! in_array($this->units, RecipeScaler::SYSTEMS, true)) {
            $this->units = 'original';
        }
        $this->scale = $this->formatScale($this->factor());
    }

    /** The scale as a plain number string, e.g. "0.5" or "2". */
    private function formatScale(float $factor): string
    {
        return Quantity::decimal($factor, 3);
    }

    #[Computed]
    public function factor(): float
    {
        $raw = trim(str_replace(['×', 'x', 'X'], '', $this->scale));
        $value = Quantity::parse($raw);
        if ($value === null && is_numeric($raw)) {
            $value = (float) $raw;
        }

        return RecipeScaler::clampFactor($value && $value > 0 ? $value : 1.0);
    }

    public function setScale(string $value): void
    {
        $this->scale = $value;
        unset($this->factor);
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
        $this->setScale($this->formatScale($target / $base));
    }

    public function render()
    {
        $factor = $this->factor();
        $recipe = $this->recipe->loadMissing(['ingredientGroups', 'ingredients', 'steps']);

        $sections = [];
        $loose = $recipe->ingredients->whereNull('ingredient_group_id');
        if ($loose->isNotEmpty()) {
            $sections[] = ['name' => null, 'lines' => $loose];
        }
        foreach ($recipe->ingredientGroups as $group) {
            $sections[] = ['name' => $group->name, 'lines' => $recipe->ingredients->where('ingredient_group_id', $group->id)];
        }

        $sections = array_map(function ($section) use ($factor) {
            $section['lines'] = $section['lines']->map(fn ($i) => RecipeScaler::scale($i, $factor, $this->units))->values();

            return $section;
        }, $sections);

        return view('livewire.recipes.scaler', [
            'sections' => $sections,
            'factor' => $factor,
            'servings' => $recipe->servings ? $recipe->servings * $factor : null,
            'presets' => ['0.5' => '½×', '1' => '1×', '2' => '2×', '3' => '3×'],
        ]);
    }
}
