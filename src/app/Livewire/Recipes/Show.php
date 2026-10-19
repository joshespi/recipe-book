<?php

namespace App\Livewire\Recipes;

use App\Models\Recipe;
use Illuminate\Support\Facades\DB;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Show extends Component
{
    public Recipe $recipe;

    public function mount(Recipe $recipe): void
    {
        $this->authorize('view', $recipe);
        $this->recipe = $recipe->load(['categories', 'tags', 'ingredientGroups', 'ingredients', 'steps']);
    }

    public function toggleFavorite(): void
    {
        $this->authorize('update', $this->recipe);
        $this->recipe->update(['is_favorite' => ! $this->recipe->is_favorite]);
    }

    /** Clicking the current rating again clears it. */
    public function setRating(int $stars): void
    {
        $this->authorize('update', $this->recipe);
        abort_unless($stars >= 1 && $stars <= 5, 422);
        $this->recipe->update(['rating' => $this->recipe->rating === $stars ? null : $stars]);
    }

    public function setVisibility(string $visibility): void
    {
        $this->authorize('update', $this->recipe);
        abort_unless(in_array($visibility, Recipe::VISIBILITIES, true), 422);
        $this->recipe->update(['visibility' => $visibility]);
    }

    /** Replaces the share token, which turns the old link into a 404. */
    public function regenerateLink(): void
    {
        $this->authorize('update', $this->recipe);
        $this->recipe->regenerateShareToken();
    }

    public function duplicate()
    {
        $this->authorize('view', $this->recipe);
        $source = $this->recipe->load(['categories', 'tags', 'ingredientGroups', 'ingredients', 'steps']);

        $copy = DB::transaction(function () use ($source) {
            $copy = $source->replicate(['slug', 'share_token', 'cover_photo_id', 'is_favorite', 'rating']);
            $copy->title = 'Copy of '.$source->title;
            $copy->slug = Recipe::uniqueSlug($copy->title);
            $copy->visibility = Recipe::PRIVATE;
            $copy->save();

            $copy->categories()->sync($source->categories->pluck('id'));
            $copy->tags()->sync($source->tags->pluck('id'));

            $groupMap = [];
            foreach ($source->ingredientGroups as $group) {
                $groupMap[$group->id] = $copy->ingredientGroups()->create(['name' => $group->name, 'position' => $group->position])->id;
            }
            foreach ($source->ingredients as $ingredient) {
                $copy->ingredients()->create($ingredient->only([
                    'position', 'quantity', 'quantity_max', 'unit', 'name', 'note', 'original_text',
                ]) + ['ingredient_group_id' => $groupMap[$ingredient->ingredient_group_id] ?? null]);
            }
            foreach ($source->steps as $step) {
                $copy->steps()->create($step->only(['position', 'body', 'timer_minutes']));
            }

            return $copy;
        });

        return $this->redirectRoute('recipes.edit', $copy, navigate: true);
    }

    public function delete()
    {
        $this->authorize('delete', $this->recipe);
        $this->recipe->delete();
        session()->flash('status', 'Recipe deleted.');

        return $this->redirectRoute('recipes.index', navigate: true);
    }

    public function render()
    {
        return view('livewire.recipes.show')->title($this->recipe->title);
    }
}
