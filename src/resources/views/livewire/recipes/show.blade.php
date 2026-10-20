@php($cover = $recipe->cover())
<article class="space-y-6">
    <header class="grid gap-5 md:grid-cols-5">
        <div class="space-y-3 md:col-span-3">
            <div class="flex items-start gap-3">
                <h1 class="flex-1 text-3xl font-semibold tracking-tight">{{ $recipe->title }}</h1>
                <button type="button" wire:click="toggleFavorite" class="print:hidden text-2xl leading-none"
                        aria-label="{{ $recipe->is_favorite ? 'Remove from favorites' : 'Add to favorites' }}" aria-pressed="{{ $recipe->is_favorite ? 'true' : 'false' }}">
                    <span class="{{ $recipe->is_favorite ? 'text-red-500' : 'text-stone-400' }}">{{ $recipe->is_favorite ? '♥' : '♡' }}</span>
                </button>
            </div>

            <x-recipe-details :recipe="$recipe" />

            <div class="flex items-center gap-1 print:hidden" role="group" aria-label="Rating">
                @foreach (range(1, 5) as $star)
                    <button type="button" wire:click="setRating({{ $star }})" class="text-2xl leading-none {{ $recipe->rating && $star <= $recipe->rating ? 'text-red-500' : 'text-stone-300 dark:text-stone-600' }}" aria-label="Rate {{ $star }} of 5">★</button>
                @endforeach
            </div>

            <div class="flex flex-wrap gap-2 pt-1 print:hidden">
                <a href="{{ route('recipes.cook', $recipe) }}" class="btn-primary" x-data x-on:click="$el.search = location.search">👩‍🍳 Cook mode</a>
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

    <x-recipe-notes :recipe="$recipe" />

    <livewire:recipes.photo-gallery :recipe="$recipe" :editable="true" :key="'gallery-'.$recipe->id" />

    <livewire:recipes.cook-logs :recipe="$recipe" :key="'logs-'.$recipe->id" />
</article>
