<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">Recipes <span class="text-base font-normal text-stone-500">({{ $total }})</span></h1>
        <a href="{{ route('recipes.create') }}" class="btn-primary" wire:navigate>+ New recipe</a>
    </div>

    <div class="card space-y-3 p-3">
        <div class="flex flex-wrap items-center gap-2">
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Search titles, descriptions and ingredients…"
                   class="field min-w-0 flex-1" aria-label="Search recipes" />

            <select wire:model.live="sort" class="field w-auto" aria-label="Sort recipes">
                <option value="recent">Recently added</option>
                <option value="az">A–Z</option>
                <option value="cooked">Most cooked</option>
                <option value="rating">Highest rated</option>
            </select>
        </div>

        <div class="flex flex-wrap items-center gap-x-4 gap-y-2 text-sm">
            <label class="inline-flex items-center gap-1.5"><input type="checkbox" wire:model.live="favoritesOnly" class="rounded border-stone-300 text-amber-600 focus:ring-amber-500"> Favorites</label>
            <label class="inline-flex items-center gap-1.5"><input type="checkbox" wire:model.live="withPhotos" class="rounded border-stone-300 text-amber-600 focus:ring-amber-500"> Has photos</label>

            @if ($categories->isNotEmpty())
                <span class="hidden h-4 w-px bg-stone-300 sm:block dark:bg-stone-700"></span>
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($categories as $category)
                        @php($on = in_array($category->id, array_map('intval', $categoryIds), true))
                        <button type="button" wire:click="toggleCategory({{ $category->id }})" aria-pressed="{{ $on ? 'true' : 'false' }}">
                            <x-category-chip :category="$category" :active="$on" class="cursor-pointer" />
                        </button>
                    @endforeach
                </div>
            @endif

            @if ($filtered)
                <button type="button" wire:click="clearFilters" class="ml-auto text-amber-700 underline dark:text-amber-400">Clear filters</button>
            @endif
        </div>
    </div>

    @if ($recipes->isEmpty())
        <div class="card p-10 text-center text-stone-500 dark:text-stone-400">
            @if ($filtered)
                <p>No recipes match those filters.</p>
            @else
                <p class="text-lg">Your recipe box is empty.</p>
                <a href="{{ route('recipes.create') }}" class="btn-primary mt-4" wire:navigate>Add your first recipe</a>
            @endif
        </div>
    @else
        <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
            @foreach ($recipes as $recipe)
                <x-recipe-card :recipe="$recipe" :href="route('recipes.show', $recipe)" wire:key="recipe-{{ $recipe->id }}">
                    <button type="button" wire:click="toggleFavorite({{ $recipe->id }})"
                            class="absolute top-2 right-2 rounded-full bg-white/90 p-1.5 text-lg leading-none shadow dark:bg-stone-900/90"
                            aria-label="{{ $recipe->is_favorite ? 'Remove from favorites' : 'Add to favorites' }}"
                            aria-pressed="{{ $recipe->is_favorite ? 'true' : 'false' }}">
                        <span class="{{ $recipe->is_favorite ? 'text-red-500' : 'text-stone-400' }}">{{ $recipe->is_favorite ? '♥' : '♡' }}</span>
                    </button>
                    @if ($recipe->visibility !== 'unlisted')
                        <span class="absolute top-2 left-2 rounded-full bg-white/90 px-2 py-0.5 text-xs font-medium shadow dark:bg-stone-900/90">{{ $recipe->visibility === 'public' ? '🌐 Public' : '🔒 Private' }}</span>
                    @endif
                </x-recipe-card>
            @endforeach
        </div>

        <div>{{ $recipes->links() }}</div>
    @endif
</div>
