<div class="space-y-4">
    <div class="card space-y-3 p-3 print:hidden" aria-label="Scale and units">
        <div class="flex flex-wrap items-center gap-x-5 gap-y-3">
            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-stone-600 dark:text-stone-400">Scale</span>
                <div class="inline-flex overflow-hidden rounded-lg border border-stone-300 dark:border-stone-700" role="group" aria-label="Scale">
                    @foreach ($presets as $value => $label)
                        <button type="button" wire:click="setScale('{{ $value }}')"
                                aria-pressed="{{ abs($factor - (float) $value) < 0.0005 ? 'true' : 'false' }}"
                                class="px-3 py-1.5 text-sm font-medium {{ abs($factor - (float) $value) < 0.0005 ? 'bg-red-600 text-white' : 'bg-white text-stone-700 hover:bg-stone-100 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800' }}">{{ $label }}</button>
                    @endforeach
                </div>
                <label class="sr-only" for="custom-scale-{{ $this->getId() }}">Custom scale</label>
                <input id="custom-scale-{{ $this->getId() }}" type="text" inputmode="decimal" wire:model.blur="scale" wire:keydown.enter="$refresh"
                       class="field w-20 py-1.5 text-center" title="Custom scale, e.g. 1.5 or 3/4" />
            </div>

            @if ($recipe->servings)
                <div class="flex items-center gap-2">
                    <span class="text-sm font-medium text-stone-600 dark:text-stone-400">Servings</span>
                    <div class="inline-flex items-center overflow-hidden rounded-lg border border-stone-300 dark:border-stone-700">
                        <button type="button" wire:click="stepServings(-1)" class="px-3 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="Fewer servings">−</button>
                        <span class="min-w-12 px-2 text-center text-sm font-semibold" aria-live="polite">{{ \App\Support\Quantity::format($servings) }}</span>
                        <button type="button" wire:click="stepServings(1)" class="px-3 py-1.5 hover:bg-stone-100 dark:hover:bg-stone-800" aria-label="More servings">+</button>
                    </div>
                    @if ($recipe->yield_label)<span class="text-sm text-stone-500">{{ $recipe->yield_label }}</span>@endif
                </div>
            @endif

            <div class="flex items-center gap-2">
                <span class="text-sm font-medium text-stone-600 dark:text-stone-400">Units</span>
                <div class="inline-flex overflow-hidden rounded-lg border border-stone-300 dark:border-stone-700" role="group" aria-label="Units">
                    @foreach (['original' => 'Original', 'us' => 'US', 'metric' => 'Metric'] as $key => $label)
                        <button type="button" wire:click="setUnits('{{ $key }}')" aria-pressed="{{ $units === $key ? 'true' : 'false' }}"
                                class="px-3 py-1.5 text-sm font-medium {{ $units === $key ? 'bg-red-600 text-white' : 'bg-white text-stone-700 hover:bg-stone-100 dark:bg-stone-900 dark:text-stone-200 dark:hover:bg-stone-800' }}">{{ $label }}</button>
                    @endforeach
                </div>
            </div>
        </div>

        @if (abs($factor - 1.0) > 0.0005)
            <p class="rounded-lg bg-red-50 px-3 py-2 text-sm text-red-900 dark:bg-red-900/20 dark:text-red-200" role="status">
                Scaled {{ \App\Support\Quantity::format($factor) }}× — amounts written inside the steps, the pan size and the cooking times may need adjusting.
            </p>
        @endif
    </div>

    <div class="grid gap-6 md:grid-cols-5 print:grid-cols-5">
        <section class="card p-4 md:col-span-2 print:col-span-2 print:border-0 print:shadow-none" aria-labelledby="ing-h-{{ $this->getId() }}">
            <h2 id="ing-h-{{ $this->getId() }}" class="mb-3 text-lg font-semibold">Ingredients</h2>
            @forelse ($sections as $section)
                @if ($section['name'])
                    <h3 class="mt-4 mb-1.5 font-semibold text-red-800 first:mt-0 dark:text-red-400">{{ $section['name'] }}</h3>
                @endif
                <ul class="space-y-1.5">
                    @foreach ($section['lines'] as $line)
                        <li class="flex gap-2">
                            <span class="mt-2 size-1.5 shrink-0 rounded-full bg-red-500" aria-hidden="true"></span>
                            <x-ingredient-line :line="$line" />
                        </li>
                    @endforeach
                </ul>
            @empty
                <p class="text-stone-500">No ingredients yet.</p>
            @endforelse
        </section>

        <section class="card p-4 md:col-span-3 print:col-span-3 print:border-0 print:shadow-none" aria-labelledby="steps-h-{{ $this->getId() }}">
            <h2 id="steps-h-{{ $this->getId() }}" class="mb-3 text-lg font-semibold">Method</h2>
            <ol class="space-y-4">
                @forelse ($recipe->steps as $n => $step)
                    <li class="flex gap-3">
                        <span class="flex size-7 shrink-0 items-center justify-center rounded-full bg-red-100 text-sm font-semibold text-red-800 dark:bg-red-900/40 dark:text-red-300">{{ $n + 1 }}</span>
                        <div class="min-w-0 flex-1 whitespace-pre-line pt-0.5">{{ $step->body }}@if ($step->timer_minutes) <span class="ml-1 whitespace-nowrap rounded bg-stone-100 px-1.5 text-xs text-stone-600 dark:bg-stone-800 dark:text-stone-300">⏲ {{ $step->timer_minutes }} min</span>@endif</div>
                    </li>
                @empty
                    <li class="text-stone-500">No steps yet.</li>
                @endforelse
            </ol>
        </section>
    </div>
</div>
