@php($cover = $recipe->cover())
<article class="space-y-6">
    @if (session('status'))
        <p class="rounded-lg bg-emerald-50 px-3 py-2 text-sm text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-200" role="status">{{ session('status') }}</p>
    @endif

    <header class="grid gap-5 md:grid-cols-5">
        <div class="space-y-3 md:col-span-3">
            <div class="flex items-start gap-3">
                <h1 class="flex-1 text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>
                <button type="button" wire:click="toggleFavorite" class="print:hidden text-2xl leading-none"
                        aria-label="{{ $recipe->is_favorite ? 'Remove from favorites' : 'Add to favorites' }}" aria-pressed="{{ $recipe->is_favorite ? 'true' : 'false' }}">
                    <span class="{{ $recipe->is_favorite ? 'text-red-500' : 'text-stone-400' }}">{{ $recipe->is_favorite ? '♥' : '♡' }}</span>
                </button>
            </div>

            @if ($recipe->description)<p class="text-stone-600 dark:text-stone-300">{{ $recipe->description }}</p>@endif

            @if ($recipe->categories->isNotEmpty())
                <div class="flex flex-wrap gap-1.5">
                    @foreach ($recipe->categories as $category)
                        <a href="{{ route('recipes.index', ['cats' => [$category->id]]) }}" wire:navigate><x-category-chip :category="$category" /></a>
                    @endforeach
                </div>
            @endif

            <dl class="flex flex-wrap gap-x-6 gap-y-2 text-sm">
                @if ($recipe->prep_minutes !== null)<div><dt class="text-stone-500">Prep</dt><dd class="font-medium">{{ $recipe->prep_minutes }} min</dd></div>@endif
                @if ($recipe->cook_minutes !== null)<div><dt class="text-stone-500">Cook</dt><dd class="font-medium">{{ $recipe->cook_minutes }} min</dd></div>@endif
                @if ($recipe->total_minutes !== null)<div><dt class="text-stone-500">Total</dt><dd class="font-medium">{{ $recipe->total_minutes }} min</dd></div>@endif
                @if ($recipe->servings)<div><dt class="text-stone-500">Serves</dt><dd class="font-medium">{{ \App\Support\Quantity::format($recipe->servings) }}{{ $recipe->yield_label ? ' ('.$recipe->yield_label.')' : '' }}</dd></div>@endif
            </dl>

            <div class="flex items-center gap-1 print:hidden" role="group" aria-label="Rating">
                @foreach (range(1, 5) as $star)
                    <button type="button" wire:click="setRating({{ $star }})" class="text-2xl leading-none {{ $recipe->rating && $star <= $recipe->rating ? 'text-amber-500' : 'text-stone-300 dark:text-stone-600' }}" aria-label="Rate {{ $star }} of 5">★</button>
                @endforeach
            </div>

            @if ($recipe->source_name || $recipe->source_url)
                <p class="text-sm text-stone-500">Source:
                    @if ($recipe->source_url)
                        <a href="{{ $recipe->source_url }}" target="_blank" rel="noopener noreferrer nofollow" class="text-amber-700 underline dark:text-amber-400">{{ $recipe->source_name ?: parse_url($recipe->source_url, PHP_URL_HOST) }}</a>
                    @else
                        {{ $recipe->source_name }}
                    @endif
                </p>
            @endif

            <div class="flex flex-wrap gap-2 pt-1 print:hidden">
                <a href="{{ route('recipes.edit', $recipe) }}" class="btn-secondary" wire:navigate>✏️ Edit</a>
                <button type="button" onclick="window.print()" class="btn-secondary">🖨️ Print</button>
                <button type="button" wire:click="duplicate" class="btn-secondary">⧉ Duplicate</button>
                <button type="button" wire:click="delete" wire:confirm="Delete this recipe? It can be restored from a backup but not from the app." class="btn-danger">Delete</button>
            </div>
        </div>

        @if ($cover)
            <img src="{{ $cover->mediumUrl() }}" alt="{{ $recipe->title }}" class="aspect-[4/3] w-full rounded-xl object-cover shadow md:col-span-2" />
        @endif
    </header>

    <section class="card space-y-3 p-4 print:hidden"
             x-data="{
                get url() { return this.$refs.link ? this.$refs.link.value : '' },
                copied: false,
                async copy() {
                    try { await navigator.clipboard.writeText(this.url) }
                    catch (e) { this.$refs.link.select(); document.execCommand('copy') }
                    this.copied = true; setTimeout(() => this.copied = false, 2000)
                },
                canShare: !!navigator.share,
                share() { navigator.share({ title: @js($recipe->title), url: this.url }).catch(() => {}) },
             }">
        <div class="flex flex-wrap items-center justify-between gap-3">
            <h2 class="font-semibold">Sharing</h2>
            <select wire:change="setVisibility($event.target.value)" class="field w-auto" aria-label="Who can see this recipe">
                <option value="private" @selected($recipe->visibility === 'private')>🔒 Private - only me</option>
                <option value="unlisted" @selected($recipe->visibility === 'unlisted')>🔗 Anyone with the link</option>
                <option value="public" @selected($recipe->visibility === 'public')>🌐 Public - listed on Browse</option>
            </select>
        </div>

        @if ($recipe->isShareable())
            <div class="flex flex-wrap gap-2">
                <input x-ref="link" type="text" readonly value="{{ $recipe->shareUrl() }}" class="field min-w-0 flex-1 font-mono text-xs" aria-label="Share link" x-on:focus="$el.select()" />
                <button type="button" class="btn-primary" x-on:click="copy()"><span x-show="!copied">Copy link</span><span x-show="copied" x-cloak>Copied ✓</span></button>
                <button type="button" class="btn-secondary" x-show="canShare" x-cloak x-on:click="share()">Share…</button>
            </div>
            <button type="button" wire:click="regenerateLink" wire:confirm="Make a new link? The old link will stop working." class="text-sm text-stone-500 underline">Make a new link (disables the old one)</button>
        @else
            <p class="text-sm text-stone-500">This recipe is private. Change the setting above to get a share link.</p>
        @endif
    </section>

    <livewire:recipes.scaler :recipe="$recipe" :key="'scaler-'.$recipe->id" />

    @if ($recipe->notes)
        <section class="card p-4">
            <h2 class="mb-2 text-lg font-semibold">Notes</h2>
            <p class="whitespace-pre-line">{{ $recipe->notes }}</p>
        </section>
    @endif

    <livewire:recipes.photo-gallery :recipe="$recipe" :editable="true" :key="'gallery-'.$recipe->id" />

    <livewire:recipes.cook-logs :recipe="$recipe" :key="'logs-'.$recipe->id" />
</article>
