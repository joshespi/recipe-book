<form wire:submit="save" class="mx-auto max-w-3xl space-y-6">
    <div class="flex items-center justify-between gap-3">
        <h1 class="text-2xl font-semibold tracking-tight">{{ $recipe ? 'Edit recipe' : 'New recipe' }}</h1>
        <a href="{{ $recipe ? route('recipes.show', $recipe) : route('recipes.index') }}" class="text-sm text-stone-500 underline" wire:navigate>Cancel</a>
    </div>

    @unless ($recipe)
        <section class="card space-y-2 p-4" aria-labelledby="import-h">
            <h2 id="import-h" class="font-semibold">Import from a web page</h2>
            <p class="text-sm text-stone-500 dark:text-stone-400">Paste the link to a recipe and we will fill in the form for you to check. Works with most recipe sites.</p>
            <div class="flex gap-2">
                <input type="url" wire:model="importUrl" wire:keydown.enter.prevent="import" placeholder="https://…" class="field min-w-0 flex-1" aria-label="Recipe web address" />
                <button type="button" wire:click="import" wire:loading.attr="disabled" wire:target="import" class="btn-secondary">
                    <span wire:loading.remove wire:target="import">Import</span><span wire:loading wire:target="import">Reading…</span>
                </button>
            </div>
            @error('importUrl') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
            @if ($importNotice) <p class="text-sm text-emerald-700 dark:text-emerald-400" role="status">{{ $importNotice }}</p> @endif
        </section>
    @endunless

    <section class="card space-y-4 p-4">
        <div>
            <label for="title" class="label">Title</label>
            <input id="title" type="text" wire:model="title" class="field" autofocus required />
            @error('title') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="description" class="label">Description</label>
            <textarea id="description" wire:model="description" rows="2" class="field"></textarea>
            @error('description') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>

        <div class="grid grid-cols-2 gap-4 sm:grid-cols-4">
            <div>
                <label for="servings" class="label">Servings</label>
                <input id="servings" type="number" step="any" min="0" wire:model="servings" class="field" />
                @error('servings') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="yield_label" class="label">Makes (e.g. cookies)</label>
                <input id="yield_label" type="text" wire:model="yield_label" class="field" />
            </div>
            <div>
                <label for="prep" class="label">Prep (min)</label>
                <input id="prep" type="number" min="0" wire:model="prep_minutes" class="field" />
                @error('prep_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
            <div>
                <label for="cook" class="label">Cook (min)</label>
                <input id="cook" type="number" min="0" wire:model="cook_minutes" class="field" />
                @error('cook_minutes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
    </section>

    <section class="card space-y-3 p-4">
        <h2 class="font-semibold">Categories</h2>
        <div class="flex flex-wrap gap-2">
            @forelse ($this->allCategories as $category)
                <label class="cursor-pointer">
                    <input type="checkbox" wire:model="categoryIds" value="{{ $category->id }}" class="peer sr-only" />
                    <x-category-chip :category="$category" class="opacity-60 peer-checked:opacity-100 peer-checked:ring-2 peer-checked:ring-amber-500 peer-focus-visible:ring-2" />
                </label>
            @empty
                <p class="text-sm text-stone-500">No categories yet. Add one below.</p>
            @endforelse
        </div>
        <div class="flex gap-2">
            <input type="text" wire:model="newCategory" wire:keydown.enter.prevent="addCategory" placeholder="New category…" class="field max-w-xs" aria-label="New category name" />
            <button type="button" wire:click="addCategory" class="btn-secondary">Add</button>
        </div>
        @error('newCategory') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        <div>
            <label for="tags" class="label">Tags</label>
            <input id="tags" type="text" wire:model="tagsText" list="tag-suggestions" class="field" placeholder="freezer-friendly, kid favorite, gluten-free" />
            <datalist id="tag-suggestions">
                @foreach ($this->allTags as $tag)<option value="{{ $tag }}"></option>@endforeach
            </datalist>
            @error('tagsText') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
    </section>

    <section class="card space-y-3 p-4">
        <div>
            <h2 class="font-semibold">Ingredients</h2>
            <p class="text-sm text-stone-500 dark:text-stone-400">
                One per line, e.g. <code class="rounded bg-stone-100 px-1 dark:bg-stone-800">1 1/2 cups flour, sifted</code>.
                Start a line with <code class="rounded bg-stone-100 px-1 dark:bg-stone-800"># </code> for a heading such as <em># For the sauce</em>.
            </p>
        </div>

        <textarea wire:model.live.debounce.400ms="ingredientsText" rows="10" class="field font-mono" spellcheck="false"
                  placeholder="2 cups all-purpose flour&#10;1 tsp salt&#10;3 eggs&#10;&#10;# For the sauce&#10;1/2 cup butter, melted"></textarea>
        @error('ingredientsText') <p class="text-sm text-red-600">{{ $message }}</p> @enderror

        @if (count($this->preview))
            <div class="rounded-lg bg-stone-50 p-3 text-sm dark:bg-stone-800/60">
                <p class="mb-2 text-xs font-medium tracking-wide text-stone-500 uppercase">How these will be saved</p>
                @php($lastGroup = null)
                <ul class="space-y-1">
                    @foreach ($this->preview as $row)
                        @if ($row['group'] !== $lastGroup)
                            <li class="pt-2 font-semibold">{{ $row['group'] }}</li>
                            @php($lastGroup = $row['group'])
                        @endif
                        @php($p = $row['parsed'])
                        <li class="flex flex-wrap gap-x-2">
                            @if ($p['quantity'] !== null)
                                @php($shown = \App\Support\RecipeScaler::scale((object) $p, 1.0, 'original'))
                                <span class="rounded bg-amber-100 px-1.5 font-medium text-amber-900 dark:bg-amber-900/40 dark:text-amber-200">{{ $shown->quantity }}{{ $shown->unit ? ' '.$shown->unit : '' }}</span>
                            @else
                                <span class="rounded bg-stone-200 px-1.5 text-stone-600 dark:bg-stone-700 dark:text-stone-300" title="No amount found, so this will not scale">no amount</span>
                            @endif
                            <span>{{ $p['name'] }}</span>
                            @if ($p['note'])<span class="text-stone-500">({{ $p['note'] }})</span>@endif
                        </li>
                    @endforeach
                </ul>
            </div>
        @endif
    </section>

    <section class="card space-y-3 p-4">
        <h2 class="font-semibold">Steps</h2>
        <ol class="space-y-3">
            @foreach ($steps as $i => $step)
                <li class="flex gap-2" wire:key="step-{{ $i }}">
                    <span class="mt-2 flex size-6 shrink-0 items-center justify-center rounded-full bg-amber-100 text-xs font-semibold text-amber-800 dark:bg-amber-900/40 dark:text-amber-300">{{ $i + 1 }}</span>
                    <div class="min-w-0 flex-1 space-y-1.5">
                        <textarea wire:model="steps.{{ $i }}.body" rows="2" class="field" aria-label="Step {{ $i + 1 }}"></textarea>
                        <div class="flex flex-wrap items-center gap-2 text-sm">
                            <label class="inline-flex items-center gap-1.5 text-stone-500">⏲ Timer
                                <input type="number" min="1" wire:model="steps.{{ $i }}.timer" class="field w-20 py-1" placeholder="min" />
                            </label>
                            <button type="button" wire:click="moveStep({{ $i }}, -1)" class="btn-secondary px-2 py-1" @disabled($i === 0) aria-label="Move step up">↑</button>
                            <button type="button" wire:click="moveStep({{ $i }}, 1)" class="btn-secondary px-2 py-1" @disabled($i === count($steps) - 1) aria-label="Move step down">↓</button>
                            <button type="button" wire:click="removeStep({{ $i }})" class="btn-danger px-2 py-1" aria-label="Remove step">Remove</button>
                        </div>
                        @error("steps.$i.body") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                        @error("steps.$i.timer") <p class="text-sm text-red-600">{{ $message }}</p> @enderror
                    </div>
                </li>
            @endforeach
        </ol>
        <button type="button" wire:click="addStep" class="btn-secondary">+ Add step</button>
    </section>

    <section class="card space-y-4 p-4">
        <div>
            <label for="notes" class="label">Notes</label>
            <textarea id="notes" wire:model="notes" rows="3" class="field"></textarea>
        </div>
        <div class="grid gap-4 sm:grid-cols-2">
            <div>
                <label for="source_name" class="label">Source</label>
                <input id="source_name" type="text" wire:model="source_name" class="field" placeholder="Grandma, a cookbook, a website…" />
            </div>
            <div>
                <label for="source_url" class="label">Source link</label>
                <input id="source_url" type="url" wire:model="source_url" class="field" placeholder="https://…" />
                @error('source_url') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>
        </div>
        <div>
            <label for="visibility" class="label">Who can see it</label>
            <select id="visibility" wire:model="visibility" class="field sm:max-w-xs">
                <option value="private">Private - only me</option>
                <option value="unlisted">Anyone with the link</option>
                <option value="public">Public - listed on Browse</option>
            </select>
        </div>
    </section>

    <div class="sticky bottom-0 -mx-4 flex items-center justify-end gap-3 border-t border-stone-200 bg-stone-50/90 px-4 py-3 backdrop-blur dark:border-stone-800 dark:bg-stone-950/90">
        <span wire:loading wire:target="save" class="text-sm text-stone-500">Saving…</span>
        <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save">Save recipe</button>
    </div>
</form>
