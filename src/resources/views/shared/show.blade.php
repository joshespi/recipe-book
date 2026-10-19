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
        @if ($recipe->isIndexable())
            <script type="application/ld+json">{!! json_encode($jsonLd, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE | JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) !!}</script>
        @else
            <meta name="robots" content="noindex, nofollow">
        @endif
    </x-slot:head>

    <article class="space-y-6">
        <header class="grid gap-5 md:grid-cols-5">
            <div class="space-y-3 md:col-span-3">
                <h1 class="text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>
                <x-recipe-details :recipe="$recipe" shared />


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

        <x-recipe-notes :recipe="$recipe" />

        <livewire:recipes.photo-gallery :recipe="$recipe" :key="'gallery-'.$recipe->id" />
    </article>
</x-layouts.app>
