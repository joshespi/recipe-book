<x-layouts.app title="Browse public recipes">
    <x-slot:head>
        <meta name="description" content="Recipes shared publicly on {{ config('app.name') }}">
    </x-slot:head>

    <div class="space-y-6">
        <div class="collection-heading">
            <h1>Public recipes</h1>
            <flux:icon name="book-open" class="size-10 opacity-70" />
        </div>

        <form method="GET" action="{{ route('browse') }}" class="card flex flex-wrap items-center gap-3 p-4 sm:p-5">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search recipes…" class="field min-w-0 basis-full sm:flex-1" aria-label="Search public recipes" />
            @if ($tag !== '')
                <input type="hidden" name="tag" value="{{ $tag }}" />
                <a href="{{ route('browse', array_filter(['q' => request('q'), 'category' => $category])) }}" class="rounded-full border border-herb-600 bg-herb-600 px-2.5 py-0.5 text-xs text-white" title="Remove tag filter">#{{ $tag }} ✕</a>
            @endif
            @if ($categories->isNotEmpty())
                <select name="category" class="field w-auto" aria-label="Category">
                    <option value="">All categories</option>
                    @foreach ($categories as $c)
                        <option value="{{ $c->slug }}" @selected($category === $c->slug)>{{ $c->name }}</option>
                    @endforeach
                </select>
            @endif
            <button type="submit" class="btn-primary">Search</button>
        </form>

        @if ($recipes->isEmpty())
            <div class="card flex flex-col items-center gap-4 px-5 py-14 text-center">
                <flux:icon name="book-open" class="size-9 text-herb-500 dark:text-herb-300" />
                @if (request()->filled('q') || $category || $tag !== '')
                    <p class="font-serif text-2xl">No recipes match those filters.</p>
                    <a href="{{ route('browse') }}" class="btn-secondary" wire:navigate>Clear filters</a>
                @else
                    <p class="font-serif text-2xl">No public recipes yet.</p>
                @endif
            </div>
        @else
            <div class="grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($recipes as $recipe)
                    <x-recipe-card :recipe="$recipe" :href="route('shared.show', $recipe->share_token)" />
                @endforeach
            </div>
            <div>{{ $recipes->links() }}</div>
        @endif
    </div>
</x-layouts.app>
