<div class="space-y-5">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">Shopping list</h1>
        <div class="flex items-center gap-2 text-sm">
            <span class="font-medium text-stone-600 dark:text-stone-400">Units</span>
            <div class="inline-flex overflow-hidden rounded-lg border border-stone-300 dark:border-stone-700" role="group" aria-label="Units">
                @foreach (['us' => 'US', 'metric' => 'Metric'] as $key => $label)
                    <button type="button" wire:click="$set('units', '{{ $key }}')" aria-pressed="{{ $units === $key ? 'true' : 'false' }}"
                            class="px-3 py-1.5 font-medium {{ $units === $key ? 'bg-red-600 text-white' : 'bg-white text-stone-700 hover:bg-stone-100 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800' }}">{{ $label }}</button>
                @endforeach
            </div>
        </div>
    </div>

    <div class="grid gap-6 md:grid-cols-5">
        <section class="card space-y-3 p-4 md:col-span-2" aria-labelledby="pick-h">
            <h2 id="pick-h" class="font-semibold">Pick recipes</h2>
            <input type="search" wire:model.live.debounce.300ms="search" placeholder="Find a recipe…" class="field" aria-label="Find a recipe" />

            @forelse ($recipes as $recipe)
                <div class="flex items-center gap-2" wire:key="pick-{{ $recipe->id }}">
                    <label class="flex min-w-0 flex-1 cursor-pointer items-center gap-2">
                        <input type="checkbox" wire:model.live="selected" value="{{ $recipe->id }}" class="rounded border-stone-300 text-red-600 focus:ring-red-500" />
                        <span class="truncate">{{ $recipe->title }}</span>
                    </label>
                    @if (in_array((string) $recipe->id, array_map('strval', $selected), true))
                        <select wire:model.live="scales.{{ $recipe->id }}" class="field w-auto py-1" aria-label="Scale for {{ $recipe->title }}">
                            @foreach (['0.5' => '½×', '1' => '1×', '2' => '2×', '3' => '3×', '4' => '4×'] as $value => $label)
                                <option value="{{ $value }}">{{ $label }}</option>
                            @endforeach
                        </select>
                    @endif
                </div>
            @empty
                <p class="text-sm text-stone-500">No recipes found.</p>
            @endforelse

            @if (count($selected))
                <button type="button" wire:click="clearSelection" class="text-sm text-stone-500 underline">Clear selection</button>
            @endif
        </section>

        <section class="card space-y-3 p-4 md:col-span-3" aria-labelledby="list-h"
                 x-data="{
                    done: (() => { try { return JSON.parse(localStorage.getItem('shopping-done') || '{}') } catch (e) { return {} } })(),
                    copied: false,
                    toggle(key) {
                        this.done[key] = !this.done[key];
                        try { localStorage.setItem('shopping-done', JSON.stringify(this.done)) } catch (e) {}
                    },
                    async copy() {
                        try { await navigator.clipboard.writeText(@js($text)); this.copied = true; setTimeout(() => this.copied = false, 2000) } catch (e) {}
                    },
                    reset() { this.done = {}; try { localStorage.removeItem('shopping-done') } catch (e) {} },
                 }">
            <div class="flex flex-wrap items-center justify-between gap-2">
                <h2 id="list-h" class="font-semibold">To buy @if ($lines)<span class="font-normal text-stone-500">({{ count($lines) }})</span>@endif</h2>
                @if ($lines)
                    <div class="flex gap-2 print:hidden">
                        <button type="button" class="btn-secondary px-3 py-1.5" x-on:click="copy()"><span x-show="!copied">Copy as text</span><span x-show="copied" x-cloak>Copied ✓</span></button>
                        <button type="button" class="btn-secondary px-3 py-1.5" x-on:click="reset()">Uncheck all</button>
                        <button type="button" class="btn-secondary px-3 py-1.5" onclick="window.print()">Print</button>
                    </div>
                @endif
            </div>

            @if ($chosen->isNotEmpty())
                <p class="text-sm text-stone-500">From: {{ $chosen->pluck('title')->join(', ') }}</p>
            @endif

            @forelse ($lines as $line)
                @php($key = \Illuminate\Support\Str::lower($line->name).'|'.$line->text())
                <label class="flex cursor-pointer items-start gap-3 rounded-lg p-2 text-lg hover:bg-stone-50 dark:hover:bg-stone-800/60" wire:key="line-{{ md5($key) }}">
                    <input type="checkbox" class="mt-1.5 size-5 shrink-0 rounded border-stone-400 text-red-600 focus:ring-red-500" x-on:change="toggle(@js($key))" :checked="done[@js($key)]" />
                    <x-ingredient-line :line="$line" ::class="done[{{ \Illuminate\Support\Js::from($key) }}] && 'text-stone-400 line-through dark:text-stone-600'" />
                </label>
            @empty
                <p class="py-6 text-center text-stone-500">Tick some recipes on the left and the ingredients you need will be added up here.</p>
            @endforelse
        </section>
    </div>
</div>
