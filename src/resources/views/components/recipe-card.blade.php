@props(['recipe', 'href'])
@php($cover = $recipe->cover())
<article class="card group relative flex flex-col overflow-hidden transition hover:shadow-md">
    <a href="{{ $href }}" class="block" wire:navigate>
        @if ($cover)
            <img src="{{ $cover->thumbUrl() }}" alt="{{ $recipe->title }}" loading="lazy"
                 class="aspect-[4/3] w-full object-cover" />
        @else
            <div class="flex aspect-[4/3] w-full items-center justify-center bg-amber-50 text-4xl dark:bg-stone-800" aria-hidden="true">🍽️</div>
        @endif
    </a>

    {{ $slot }}

    <div class="flex flex-1 flex-col gap-2 p-3">
        <a href="{{ $href }}" class="font-semibold leading-snug text-stone-900 hover:text-amber-700 dark:text-stone-100 dark:hover:text-amber-400" wire:navigate>
            {{ $recipe->title }}
        </a>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500 dark:text-stone-400">
            @if ($recipe->total_minutes)<span>⏱ {{ $recipe->total_minutes }} min</span>@endif
            @if ($recipe->servings)<span>🍽 {{ rtrim(rtrim(number_format($recipe->servings, 2), '0'), '.') }}</span>@endif
            @if ($recipe->rating)<span aria-label="{{ $recipe->rating }} of 5 stars">{{ str_repeat('★', $recipe->rating) }}</span>@endif
            @if (($recipe->cook_logs_count ?? 0) > 0)<span>Made {{ $recipe->cook_logs_count }}×</span>@endif
        </div>

        @if ($recipe->categories->isNotEmpty())
            <div class="mt-auto flex flex-wrap gap-1 pt-1">
                @foreach ($recipe->categories as $category)
                    <x-category-chip :category="$category" />
                @endforeach
            </div>
        @endif
    </div>
</article>
