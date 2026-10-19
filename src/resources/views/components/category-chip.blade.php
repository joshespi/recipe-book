@props(['category', 'active' => false])
@php
    // Full class names so Tailwind scanner can see them.
    $palette = [
        'amber' => 'bg-amber-100 text-amber-800 dark:bg-amber-900/40 dark:text-amber-300',
        'orange' => 'bg-orange-100 text-orange-800 dark:bg-orange-900/40 dark:text-orange-300',
        'red' => 'bg-red-100 text-red-800 dark:bg-red-900/40 dark:text-red-300',
        'rose' => 'bg-rose-100 text-rose-800 dark:bg-rose-900/40 dark:text-rose-300',
        'lime' => 'bg-lime-100 text-lime-800 dark:bg-lime-900/40 dark:text-lime-300',
        'emerald' => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/40 dark:text-emerald-300',
        'teal' => 'bg-teal-100 text-teal-800 dark:bg-teal-900/40 dark:text-teal-300',
        'sky' => 'bg-sky-100 text-sky-800 dark:bg-sky-900/40 dark:text-sky-300',
        'indigo' => 'bg-indigo-100 text-indigo-800 dark:bg-indigo-900/40 dark:text-indigo-300',
        'violet' => 'bg-violet-100 text-violet-800 dark:bg-violet-900/40 dark:text-violet-300',
        'stone' => 'bg-stone-200 text-stone-800 dark:bg-stone-700 dark:text-stone-200',
    ];
    $classes = $palette[$category->color] ?? $palette['stone'];
@endphp
<span {{ $attributes->class(['inline-flex items-center rounded-full px-2.5 py-0.5 text-xs font-medium', $classes, 'ring-2 ring-amber-500' => $active]) }}>{{ $category->name }}</span>
