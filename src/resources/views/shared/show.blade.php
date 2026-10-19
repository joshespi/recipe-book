@php
    $cover = $recipe->cover();
    $description = \Illuminate\Support\Str::limit($recipe->description ?: ('A recipe on '.config('app.name')), 200);
    $iso = fn (?int $m) => $m === null ? null : 'PT'.$m.'M';
    $jsonLd = [
        '@context' => 'https://schema.org',
        '@type' => 'Recipe',
        'name' => $recipe->title,
        'description' => $description,
        'image' => $cover ? [$cover->mediumUrl()] : null,
        'recipeYield' => $recipe->servings ? (string) ($recipe->yield_label ? $recipe->servings.' '.$recipe->yield_label : $recipe->servings) : null,
        'prepTime' => $iso($recipe->prep_minutes),
        'cookTime' => $iso($recipe->cook_minutes),
        'totalTime' => $iso($recipe->total_minutes),
        'recipeCategory' => $recipe->categories->pluck('name')->join(', ') ?: null,
        'recipeIngredient' => $recipe->ingredients->map(fn ($i) => $i->original_text ?: $i->name)->values()->all(),
        'recipeInstructions' => $recipe->steps->map(fn ($s) => ['@type' => 'HowToStep', 'text' => $s->body])->values()->all(),
    ];
    $jsonLd = array_filter($jsonLd, fn ($v) => $v !== null && $v !== []);
@endphp
<x-layouts.app :title="$recipe->title">
    <x-slot:head>
        <meta name="description" content="{{ $description }}">
        <meta property="og:type" content="article">
        <meta property="og:site_name" content="{{ config('app.name') }}">
        <meta property="og:title" content="{{ $recipe->title }}">
        <meta property="og:description" content="{{ $description }}">
        <meta property="og:url" content="{{ $recipe->shareUrl() }}">
        <meta name="twitter:card" content="{{ $cover ? 'summary_large_image' : 'summary' }}">
        <meta name="twitter:title" content="{{ $recipe->title }}">
        <meta name="twitter:description" content="{{ $description }}">
        @if ($cover)
            <meta property="og:image" content="{{ $cover->mediumUrl() }}">
            <meta name="twitter:image" content="{{ $cover->mediumUrl() }}">
        @endif
        @if ($recipe->visibility !== \App\Models\Recipe::PUBLIC)
            <meta name="robots" content="noindex, nofollow">
        @else
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @endif
    </x-slot:head>

    <article class="space-y-6">
        <header class="grid gap-5 md:grid-cols-5">
            <div class="space-y-3 md:col-span-3">
                <h1 class="text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>
                @if ($recipe->description)<p class="text-stone-600 dark:text-stone-300">{{ $recipe->description }}</p>@endif

                @if ($recipe->categories->isNotEmpty())
                    <div class="flex flex-wrap gap-1.5">
                        @foreach ($recipe->categories as $category)
                            <a href="{{ route('browse', ['category' => $category->slug]) }}"><x-category-chip :category="$category" /></a>
                        @endforeach
                    </div>
                @endif

                @if ($recipe->tags->isNotEmpty())
                    <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-stone-500 dark:text-stone-400">
                        @foreach ($recipe->tags as $tag)
                            <a href="{{ route('browse', ['tag' => $tag->slug]) }}" class="hover:text-amber-700 dark:hover:text-amber-400">#{{ $tag->name }}</a>
                        @endforeach
                    </p>
                @endif

                <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                    @if ($recipe->prep_minutes !== null)<div><dt class="text-stone-500">Prep</dt><dd class="font-medium">{{ $recipe->prep_minutes }} min</dd></div>@endif
                    @if ($recipe->cook_minutes !== null)<div><dt class="text-stone-500">Cook</dt><dd class="font-medium">{{ $recipe->cook_minutes }} min</dd></div>@endif
                    @if ($recipe->total_minutes !== null)<div><dt class="text-stone-500">Total</dt><dd class="font-medium">{{ $recipe->total_minutes }} min</dd></div>@endif
                    @if ($recipe->servings)<div><dt class="text-stone-500">Serves</dt><dd class="font-medium">{{ \App\Support\Quantity::format($recipe->servings) }}{{ $recipe->yield_label ? ' ('.$recipe->yield_label.')' : '' }}</dd></div>@endif
                    @if ($recipe->cook_logs_count)<div><dt class="text-stone-500">Made</dt><dd class="font-medium">{{ $recipe->cook_logs_count }}×</dd></div>@endif
                </dl>

                @if ($recipe->source_name || $recipe->source_url)
                    <p class="text-sm text-stone-500">Source:
                        @if ($recipe->source_url)
                            <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-amber-700 underline dark:text-amber-400">{{ $recipe->source_name ?: parse_url($recipe->source_url, PHP_URL_HOST) }}</a>
                        @else
                            {{ $recipe->source_name }}
                        @endif
                    </p>
                @endif

                <div class="flex flex-wrap gap-2 print:hidden">
                    <a href="{{ route('shared.cook', $recipe->share_token) }}" class="btn-primary" x-data x-on:click="$el.search = location.search">👩‍🍳 Cook mode</a>
                    <button type="button" onclick="window.print()" class="btn-secondary">🖨️ Print</button>
                </div>
            </div>

            @if ($cover)
                <img src="{{ $cover->mediumUrl() }}" alt="{{ $recipe->title }}" class="aspect-[4/3] w-full rounded-xl object-cover shadow md:col-span-2" />
            @endif
        </header>

        <livewire:recipes.scaler :recipe="$recipe" :key="'scaler-'.$recipe->id" />

        @if ($recipe->notes)
            <section class="card p-4">
                <h2 class="mb-2 text-lg font-semibold">Notes</h2>
                <p class="whitespace-pre-line">{{ $recipe->notes }}</p>
            </section>
        @endif

        <livewire:recipes.photo-gallery :recipe="$recipe" :key="'gallery-'.$recipe->id" />
    </article>
</x-layouts.app>
