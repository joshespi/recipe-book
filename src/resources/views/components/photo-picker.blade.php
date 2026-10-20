@props(['model'])

<div x-data="{ busy: false, progress: 0 }"
     x-on:livewire-upload-start="busy = true"
     x-on:livewire-upload-finish="busy = false"
     x-on:livewire-upload-error="busy = false"
     x-on:livewire-upload-progress="progress = $event.detail.progress"
     class="space-y-2">
    <label class="btn-secondary cursor-pointer">
        📷 Add photos
        <input type="file" wire:model="{{ $model }}" multiple accept="image/jpeg,image/png,image/webp,image/*" class="sr-only" />
    </label>
    <span x-show="busy" x-cloak class="ml-2 text-sm text-stone-500">Uploading… <span x-text="progress"></span>%</span>
    @error($model) <p class="text-sm text-red-600">{{ $message }}</p> @enderror
    @error($model.'.*') <p class="text-sm text-red-600">{{ $message }}</p> @enderror
</div>
