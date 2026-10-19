@props(['active' => false])
<a {{ $attributes->class([
    'rounded-md px-3 py-1.5 font-medium',
    'bg-amber-100 text-amber-900 dark:bg-stone-800 dark:text-amber-300' => $active,
    'text-stone-600 hover:bg-stone-100 dark:text-stone-300 dark:hover:bg-stone-800' => ! $active,
]) }} wire:navigate>{{ $slot }}</a>
