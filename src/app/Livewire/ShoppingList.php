<?php

namespace App\Livewire;

use App\Models\Recipe;
use App\Support\ShoppingListBuilder;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;

#[Layout('components.layouts.app')]
#[Title('Shopping list')]
class ShoppingList extends Component
{
    /** @var list<string> recipe ids */
    #[Url(as: 'r')]
    public array $selected = [];

    /** @var array<string, string> recipe id => scale */
    #[Url(as: 'scales')]
    public array $scales = [];

    #[Url]
    public string $units = 'us';

    public string $search = '';

    /** A newly ticked recipe starts at 1×, so the dropdown shows what the list is using. */
    public function updatedSelected(): void
    {
        foreach ($this->selected as $id) {
            $this->scales[(string) $id] ??= '1';
        }
    }

    public function clearSelection(): void
    {
        $this->selected = [];
        $this->scales = [];
    }

    public function render()
    {
        if (! in_array($this->units, ['us', 'metric'], true)) {
            $this->units = 'us';
        }

        $ids = array_values(array_unique(array_map('intval', $this->selected)));

        $recipes = Recipe::query()
            ->where('user_id', auth()->id())
            ->when($this->search !== '', fn ($q) => $q->where('title', 'like', '%'.str_replace(['%', '_'], ['\\%', '\\_'], $this->search).'%')
                ->orWhereIn('id', $ids)->where('user_id', auth()->id()))
            ->orderBy('title')
            ->get(['id', 'title', 'servings']);

        $chosen = $ids
            ? Recipe::where('user_id', auth()->id())->whereIn('id', $ids)->with('ingredients')->orderBy('title')->get()
            : collect();

        $items = [];
        foreach ($chosen as $recipe) {
            $factor = \App\Support\RecipeScaler::factorFromInput($this->scales[$recipe->id] ?? '1');
            foreach ($recipe->ingredients as $ingredient) {
                $items[] = ['ingredient' => $ingredient, 'factor' => $factor];
            }
        }

        $lines = ShoppingListBuilder::build($items, $this->units);

        return view('livewire.shopping-list', [
            'recipes' => $recipes,
            'chosen' => $chosen,
            'lines' => $lines,
            'text' => collect($lines)->map(fn ($l) => '- '.$l->text())->implode("\n"),
        ]);
    }
}
