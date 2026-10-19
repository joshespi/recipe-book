{{-- Description, categories, tags, timings and source. Shared by the owner page and the public share page. --}}
@props(['recipe', 'shared' => false])
@php
    $categoryUrl = fn ($c) => $shared ? route('browse', ['category' => $c->slug]) : route('recipes.index', ['cats' => [$c->id]]);
    $tagUrl = fn ($t) => $shared ? route('browse', ['tag' => $t->slug]) : route('recipes.index', ['tag' => $t->slug]);
    $facts = array_filter([
        'Prep' => $recipe->prep_minutes !== null ? $recipe->prep_minutes.' min' : null,
        'Cook' => $recipe->cook_minutes !== null ? $recipe->cook_minutes.' min' : null,
        'Total' => $recipe->total_minutes !== null ? $recipe->total_minutes.' min' : null,
        'Serves' => $recipe->servings ? \App\Support\Quantity::format($recipe->servings).($recipe->yield_label ? ' ('.$recipe->yield_label.')' : '') : null,
        'Made' => ($recipe->cook_logs_count ?? 0) > 0 ? $recipe->cook_logs_count.'×' : null,
    ]);
@endphp

@if ($recipe->description)<p class="text-stone-600 dark:text-stone-300">{{ $recipe->description }}</p>@endif

@if ($recipe->categories->isNotEmpty())
    <div class="flex flex-wrap gap-1.5">
        @foreach ($recipe->categories as $category)
            <a href="{{ $categoryUrl($category) }}" @unless ($shared) wire:navigate @endunless><x-category-chip :category="$category" /></a>
        @endforeach
    </div>
@endif

@if ($recipe->tags->isNotEmpty())
    <p class="flex flex-wrap gap-x-3 gap-y-1 text-sm text-stone-500 dark:text-stone-400">
        @foreach ($recipe->tags as $tag)
            <a href="{{ $tagUrl($tag) }}" class="hover:text-amber-700 dark:hover:text-amber-400" @unless ($shared) wire:navigate @endunless>#{{ $tag->name }}</a>
        @endforeach
    </p>
@endif

@if ($facts)
    <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
        @foreach ($facts as $label => $value)
            <div><dt class="text-stone-500">{{ $label }}</dt><dd class="font-medium">{{ $value }}</dd></div>
        @endforeach
    </dl>
@endif

@if ($recipe->source_name || $recipe->source_url)
    <p class="text-sm text-stone-500">Source:
        @if ($recipe->source_url)
            <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-amber-700 underline dark:text-amber-400">{{ $recipe->source_name ?: parse_url($recipe->source_url, PHP_URL_HOST) }}</a>
        @else
            {{ $recipe->source_name }}
        @endif
    </p>
@endif
