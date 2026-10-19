<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
    <head>
        @include('partials.head')
        {{ $head ?? '' }}
        @livewireStyles
    </head>
    <body class="min-h-screen bg-stone-50 text-stone-800 antialiased dark:bg-stone-950 dark:text-stone-200">
        <header class="border-b border-stone-200 bg-white/80 backdrop-blur dark:border-stone-800 dark:bg-stone-900/80 print:hidden"
                x-data="{ open: false }">
            <div class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-3">
                <a href="{{ auth()->check() ? route('recipes.index') : route('home') }}" class="flex items-center gap-2 font-semibold text-amber-700 dark:text-amber-400" wire:navigate>
                    <span aria-hidden="true" class="text-xl">🍲</span>
                    <span class="hidden sm:inline">{{ config('app.name') }}</span>
                </a>

                <nav class="ml-2 hidden items-center gap-1 text-sm md:flex">
                    @auth
                        <x-nav-link :href="route('recipes.index')" :active="request()->routeIs('recipes.index', 'recipes.show', 'recipes.edit')">Recipes</x-nav-link>
                        <x-nav-link :href="route('categories.index')" :active="request()->routeIs('categories.*')">Categories</x-nav-link>
                        <x-nav-link :href="route('shopping.index')" :active="request()->routeIs('shopping.*')">Shopping list</x-nav-link>
                    @endauth
                    <x-nav-link :href="route('browse')" :active="request()->routeIs('browse')">Browse public</x-nav-link>
                </nav>

                <div class="ml-auto flex items-center gap-2">
                    @auth
                        <a href="{{ route('recipes.create') }}" class="hidden rounded-lg bg-amber-600 px-3 py-1.5 text-sm font-medium text-white hover:bg-amber-700 sm:inline-block" wire:navigate>New recipe</a>
                    @endauth

                    <div x-data class="flex items-center rounded-lg border border-stone-200 p-0.5 dark:border-stone-700" role="radiogroup" aria-label="Theme">
                        @foreach (['light' => 'sun', 'dark' => 'moon', 'system' => 'computer-desktop'] as $mode => $icon)
                            <button type="button" role="radio" title="{{ ucfirst($mode) }}"
                                    :aria-checked="$flux.appearance === '{{ $mode }}'"
                                    x-on:click="$flux.appearance = '{{ $mode }}'"
                                    class="rounded-md p-1.5 text-stone-500 hover:text-stone-900 dark:text-stone-400 dark:hover:text-stone-100"
                                    :class="$flux.appearance === '{{ $mode }}' && 'bg-amber-100 text-amber-800 dark:bg-stone-700 dark:text-amber-300'">
                                <flux:icon :name="$icon" class="size-4" />
                            </button>
                        @endforeach
                    </div>

                    @auth
                        <flux:dropdown position="bottom" align="end">
                            <flux:profile :initials="auth()->user()->initials()" icon-trailing="chevron-down" />
                            <flux:menu>
                                <flux:menu.item href="{{ route('settings.profile') }}" icon="cog" wire:navigate>Settings</flux:menu.item>
                                <flux:menu.item href="{{ route('export.markdown') }}" icon="arrow-down-tray">Export (Markdown)</flux:menu.item>
                                <flux:menu.item href="{{ route('export.json') }}" icon="arrow-down-tray">Export (JSON)</flux:menu.item>
                                <flux:menu.separator />
                                <form method="POST" action="{{ route('logout') }}" class="w-full">
                                    @csrf
                                    <flux:menu.item as="button" type="submit" icon="arrow-right-start-on-rectangle" class="w-full">Log out</flux:menu.item>
                                </form>
                            </flux:menu>
                        </flux:dropdown>
                    @else
                        <a href="{{ route('login') }}" class="text-sm font-medium text-stone-600 hover:text-stone-900 dark:text-stone-300 dark:hover:text-white">Log in</a>
                    @endauth

                    <button type="button" class="rounded-md p-1.5 md:hidden" x-on:click="open = !open" aria-label="Menu">
                        <flux:icon name="bars-2" class="size-5" />
                    </button>
                </div>
            </div>

            <nav x-show="open" x-cloak class="border-t border-stone-200 px-4 py-2 text-sm dark:border-stone-800 md:hidden">
                @auth
                    <a href="{{ route('recipes.index') }}" class="block py-2" wire:navigate>Recipes</a>
                    <a href="{{ route('recipes.create') }}" class="block py-2" wire:navigate>New recipe</a>
                    <a href="{{ route('categories.index') }}" class="block py-2" wire:navigate>Categories</a>
                    <a href="{{ route('shopping.index') }}" class="block py-2" wire:navigate>Shopping list</a>
                @endauth
                <a href="{{ route('browse') }}" class="block py-2" wire:navigate>Browse public</a>
            </nav>
        </header>

        <main class="mx-auto max-w-6xl px-4 py-6 print:max-w-none print:p-0">
            {{ $slot }}
        </main>

        @livewireScripts
        @fluxScripts
    </body>
</html>
