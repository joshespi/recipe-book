<section class="print:hidden" @if ($pending) wire:poll.3s @endif>
@php($sig = md5($photos->map(fn ($p) => $p->id.$p->caption.$p->thumb_path)->join("|")))
<div class="space-y-3" wire:key="gallery-{{ $sig }}"
         x-data="{
            open: false, i: 0,
            items: @js($photos->map(fn ($p) => ['src' => $p->mediumUrl(), 'caption' => $p->caption])->values()),
            show(n) { this.i = n; this.open = true },
            next() { this.i = (this.i + 1) % this.items.length },
            prev() { this.i = (this.i - 1 + this.items.length) % this.items.length },
         }"
         x-on:keydown.escape.window="open = false"
         x-on:keydown.arrow-right.window="open && next()"
         x-on:keydown.arrow-left.window="open && prev()">

    @if ($photos->isNotEmpty() || $editable)
        <h2 class="text-lg font-semibold">Photos</h2>
    @endif

    @if ($editable)
        <x-photo-picker model="uploads" />
    @endif

    @if ($photos->isNotEmpty())
        <div class="grid grid-cols-2 gap-3 sm:grid-cols-3 lg:grid-cols-4">
            @foreach ($photos as $n => $photo)
                <figure class="card overflow-hidden" wire:key="photo-{{ $photo->id }}">
                    <button type="button" x-on:click="show({{ $n }})" class="relative block w-full" aria-label="Open photo {{ $n + 1 }}">
                        <img src="{{ $photo->thumbUrl() }}" alt="{{ $photo->caption ?: $recipe->title.' photo' }}" loading="lazy" class="aspect-square w-full object-cover" />
                        @if ($recipe->cover_photo_id === $photo->id)
                            <span class="absolute top-1.5 left-1.5 rounded-full bg-red-600 px-2 py-0.5 text-xs font-medium text-white">Cover</span>
                        @endif
                        @unless ($photo->isProcessed())
                            <span class="absolute right-1.5 bottom-1.5 rounded-full bg-black/60 px-2 py-0.5 text-xs text-white">{{ $photo->isPending() ? 'Processing…' : 'Could not process' }}</span>
                        @endunless
                    </button>

                    @if ($photo->caption && ! $editable)
                        <figcaption class="p-2 text-sm text-stone-600 dark:text-stone-300">{{ $photo->caption }}</figcaption>
                    @endif

                    @if ($editable)
                        <div class="space-y-2 p-2">
                            <input type="text" value="{{ $photo->caption }}" placeholder="Add a caption"
                                   x-on:change="$wire.saveCaption({{ $photo->id }}, $event.target.value)"
                                   class="field py-1 text-sm" maxlength="250" aria-label="Caption" />
                            <div class="flex flex-wrap gap-1 text-xs">
                                <button type="button" wire:click="setCover({{ $photo->id }})" class="btn-secondary px-2 py-1">Make cover</button>
                                <button type="button" wire:click="move({{ $photo->id }}, -1)" class="btn-secondary px-2 py-1" aria-label="Move earlier">←</button>
                                <button type="button" wire:click="move({{ $photo->id }}, 1)" class="btn-secondary px-2 py-1" aria-label="Move later">→</button>
                                <button type="button" wire:click="deletePhoto({{ $photo->id }})" wire:confirm="Delete this photo?" class="btn-danger px-2 py-1">Delete</button>
                            </div>
                        </div>
                    @endif
                </figure>
            @endforeach
        </div>
    @endif

    <div x-show="open" x-cloak x-transition.opacity
         class="fixed inset-0 z-50 flex items-center justify-center bg-black/90 p-4"
         role="dialog" aria-modal="true" aria-label="Photo viewer"
         x-on:click.self="open = false">
        <button type="button" class="absolute top-3 right-4 text-3xl text-white" x-on:click="open = false" aria-label="Close">×</button>
        <button type="button" class="absolute left-3 text-4xl text-white" x-show="items.length > 1" x-on:click="prev()" aria-label="Previous photo">‹</button>
        <figure class="max-h-full max-w-full">
            <img :src="items[i]?.src" :alt="items[i]?.caption || 'Recipe photo'" class="max-h-[85vh] max-w-full rounded-lg object-contain" />
            <figcaption class="mt-2 text-center text-sm text-white" x-text="items[i]?.caption"></figcaption>
        </figure>
        <button type="button" class="absolute right-3 text-4xl text-white" x-show="items.length > 1" x-on:click="next()" aria-label="Next photo">›</button>
    </div>
</div>
</section>
