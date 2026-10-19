<?php

namespace App\Livewire\Recipes;

use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use Livewire\Attributes\Layout;
use Livewire\Attributes\Title;
use Livewire\Attributes\Url;
use Livewire\Component;
use Livewire\WithPagination;

#[Layout('components.layouts.app')]
#[Title('Recipes')]
class Index extends Component
{
    use WithPagination;

    #[Url(as: 'q', except: '')]
    public string $search = '';

    /** @var list<int|string> */
    #[Url(as: 'cats')]
    public array $categoryIds = [];

    #[Url(as: 'fav', except: false)]
    public bool $favoritesOnly = false;

    #[Url(as: 'photos', except: false)]
    public bool $withPhotos = false;

    #[Url(except: 'recent')]
    public string $sort = 'recent';

    #[Url(except: '')]
    public string $tag = '';

    public function updated($name): void
    {
        $this->resetPage();
    }

    public function toggleCategory(int $id): void
    {
        $ids = array_map('intval', $this->categoryIds);
        $this->categoryIds = in_array($id, $ids, true)
            ? array_values(array_diff($ids, [$id]))
            : [...$ids, $id];
        $this->resetPage();
    }

    public function toggleTag(string $slug): void
    {
        $this->tag = $this->tag === $slug ? '' : $slug;
        $this->resetPage();
    }

    public function clearFilters(): void
    {
        $this->reset('search', 'categoryIds', 'favoritesOnly', 'withPhotos', 'sort', 'tag');
        $this->resetPage();
    }

    public function toggleFavorite(int $id): void
    {
        $recipe = Recipe::findOrFail($id);
        $this->authorize('update', $recipe);
        $recipe->update(['is_favorite' => ! $recipe->is_favorite]);
    }

    public function render()
    {
        $ids = array_values(array_unique(array_filter(array_map('intval', $this->categoryIds))));
        $user = auth()->user();

        $query = $user->recipes()
            ->search($this->search)
            // A recipe must be in every selected category.
            ->when($ids, fn ($q) => $q->whereHas('categories', fn ($c) => $c->whereIn('categories.id', $ids), '=', count($ids)))
            ->when($this->tag !== '', fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('tags.slug', $this->tag)))
            ->when($this->favoritesOnly, fn ($q) => $q->where('is_favorite', true))
            ->when($this->withPhotos, fn ($q) => $q->has('photos'))
            ->with(['categories', 'coverPhoto', 'photos'])
            ->withCount('cookLogs');

        match ($this->sort) {
            'az' => $query->orderBy('title'),
            'cooked' => $query->orderByDesc('cook_logs_count')->orderBy('title'),
            'rating' => $query->orderByRaw('rating is null')->orderByDesc('rating')->orderBy('title'),
            default => $query->latest('id'),
        };

        return view('livewire.recipes.index', [
            'recipes' => $query->paginate(24),
            'categories' => Category::ordered()->get(),
            'tags' => Tag::whereHas('recipes', fn ($q) => $q->whereBelongsTo($user))->orderBy('name')->get(),
            'filtered' => $this->search !== '' || $ids || $this->favoritesOnly || $this->withPhotos || $this->tag !== '',
            'total' => $user->recipes()->count(),
        ]);
    }
}
