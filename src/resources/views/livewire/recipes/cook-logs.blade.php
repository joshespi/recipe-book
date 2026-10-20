<section class="space-y-3 print:hidden">
    <div class="flex flex-wrap items-center justify-between gap-3">
        <div>
            <h2 class="text-lg font-semibold">Times made</h2>
            <p class="text-sm text-stone-500 dark:text-stone-400">
                @if ($logs->isEmpty())
                    Not made yet.
                @else
                    Made {{ $logs->count() }} {{ \Illuminate\Support\Str::plural('time', $logs->count()) }} · last on {{ $logs->first()->cooked_on->format('M j, Y') }}
                @endif
            </p>
        </div>
        @unless ($showForm)
            <button type="button" wire:click="$set('showForm', true)" class="btn-primary">🍳 I made this!</button>
        @endunless
    </div>

    @if ($showForm)
        <form wire:submit="save" class="card space-y-3 p-4">
            <div class="grid gap-3 sm:grid-cols-3">
                <div>
                    <label for="cooked_on" class="label">Date</label>
                    <input id="cooked_on" type="date" wire:model="cooked_on" max="{{ now()->toDateString() }}" class="field" required />
                    @error('cooked_on') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
                <div>
                    <label for="rating" class="label">Rating</label>
                    <select id="rating" wire:model="rating" class="field">
                        <option value="">No rating</option>
                        @foreach (range(5, 1) as $stars)
                            <option value="{{ $stars }}">{{ str_repeat('★', $stars) }}</option>
                        @endforeach
                    </select>
                </div>
                <div>
                    <label for="scale_used" class="label">Scale used</label>
                    <input id="scale_used" type="number" step="any" min="0.125" wire:model="scale_used" class="field" />
                    @error('scale_used') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                </div>
            </div>

            <div>
                <label for="log_notes" class="label">Notes</label>
                <textarea id="log_notes" wire:model="notes" rows="3" class="field" placeholder="What worked, what to change next time…"></textarea>
                @error('notes') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div>
                <label class="label" for="log_photos">Photos</label>
                <input id="log_photos" type="file" wire:model="photos" multiple accept="image/*" class="block w-full text-sm" />
                <div wire:loading wire:target="photos" class="mt-1 text-sm text-stone-500">Uploading…</div>
                @error('photos') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
                @error('photos.*') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
            </div>

            <div class="flex justify-end gap-2">
                <button type="button" wire:click="cancel" class="btn-secondary">Cancel</button>
                <button type="submit" class="btn-primary" wire:loading.attr="disabled" wire:target="save,photos">Save</button>
            </div>
        </form>
    @endif

    @if ($logs->isNotEmpty())
        <ol class="space-y-3 border-l-2 border-red-200 pl-4 dark:border-stone-700">
            @foreach ($logs as $log)
                <li class="relative" wire:key="log-{{ $log->id }}">
                    <span class="absolute -left-[1.4rem] top-1.5 size-2.5 rounded-full bg-red-500"></span>
                    <div class="flex flex-wrap items-center gap-x-3 text-sm">
                        <strong>{{ $log->cooked_on->format('M j, Y') }}</strong>
                        @if ($log->rating)<span class="text-red-600">{{ str_repeat('★', $log->rating) }}</span>@endif
                        @if (abs($log->scale_used - 1) > 0.0005)<span class="text-stone-500">at {{ \App\Support\Quantity::format($log->scale_used) }}×</span>@endif
                        <button type="button" wire:click="delete({{ $log->id }})" wire:confirm="Delete this entry and its photos?" class="ml-auto text-xs text-red-600 underline">Delete</button>
                    </div>
                    @if ($log->notes)<p class="mt-1 whitespace-pre-line text-sm">{{ $log->notes }}</p>@endif
                    @if ($log->photos->isNotEmpty())
                        <div class="mt-2 flex flex-wrap gap-2">
                            @foreach ($log->photos as $photo)
                                <img src="{{ $photo->thumbUrl() }}" alt="Photo from {{ $log->cooked_on->format('M j, Y') }}" loading="lazy" class="size-20 rounded-lg object-cover" />
                            @endforeach
                        </div>
                    @endif
                </li>
            @endforeach
        </ol>
    @endif
</section>
