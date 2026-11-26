@props(['recipe', 'href'])
@php($cover = $recipe->cover())
<article {{ $attributes->class('card group relative flex flex-col overflow-hidden transition-shadow hover:shadow-md') }}>
    <a href="{{ $href }}" class="block" tabindex="-1" aria-hidden="true" wire:navigate>
        @if ($cover)
            <img src="{{ $cover->thumbUrl() }}" alt="{{ $recipe->title }}" loading="lazy"
                 class="aspect-[4/3] w-full object-cover" />
        @else
            <div class="bookcloth flex aspect-[4/3] w-full items-center justify-center" aria-hidden="true"><img src="/icons/logo.png" alt="" class="size-16 -rotate-6 shadow-md rounded-xl"></div>
        @endif
    </a>

    {{ $slot }}

    <div class="flex flex-1 flex-col gap-3 p-5">
        <a href="{{ $href }}" class="font-serif text-2xl font-medium leading-snug text-stone-900 hover:text-herb-700 dark:text-stone-100 dark:hover:text-herb-400" wire:navigate>
            {{ $recipe->title }}
        </a>

        <div class="flex flex-wrap items-center gap-x-3 gap-y-1 text-xs text-stone-500 dark:text-stone-400">
            @if ($recipe->total_minutes)<span><flux:icon name="clock" class="icon-inline" /> {{ $recipe->total_minutes }} min</span>@endif
            @if ($recipe->servings)<span><flux:icon name="user-group" class="icon-inline" /> {{ \App\Support\Quantity::format($recipe->servings) }}</span>@endif
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
