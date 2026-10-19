<x-layouts.app title="Browse public recipes">
    <x-slot:head>
        <meta name="description" content="Recipes shared publicly on {{ config('app.name') }}">
    </x-slot:head>

    <div class="space-y-5">
        <h1 class="text-2xl font-semibold tracking-tight">Public recipes</h1>

        <form method="GET" action="{{ route('browse') }}" class="card flex flex-wrap items-center gap-2 p-3">
            <input type="search" name="q" value="{{ request('q') }}" placeholder="Search recipes…" class="field min-w-0 flex-1" aria-label="Search public recipes" />
            @if ($tag !== '')
                <input type="hidden" name="tag" value="{{ $tag }}" />
                <a href="{{ route('browse', array_filter(['q' => request('q'), 'category' => $category])) }}" class="rounded-full border border-amber-600 bg-amber-600 px-2.5 py-0.5 text-xs text-white" title="Remove tag filter">#{{ $tag }} ✕</a>
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
            <div class="card p-10 text-center text-stone-500 dark:text-stone-400">No public recipes yet.</div>
        @else
            <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-3 xl:grid-cols-4">
                @foreach ($recipes as $recipe)
                    <x-recipe-card :recipe="$recipe" :href="route('shared.show', $recipe->share_token)" />
                @endforeach
            </div>
            <div>{{ $recipes->links() }}</div>
        @endif
    </div>
</x-layouts.app>
