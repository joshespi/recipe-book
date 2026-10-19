<div class="mx-auto max-w-2xl space-y-5">
    <h1 class="text-2xl font-semibold tracking-tight">Categories</h1>

    <form wire:submit="add" class="card flex flex-wrap items-end gap-3 p-4">
        <div class="min-w-0 flex-1">
            <label for="cat-name" class="label">New category</label>
            <input id="cat-name" type="text" wire:model="name" class="field" placeholder="Dinner, Desserts, Soups…" required />
            @error('name') <p class="mt-1 text-sm text-red-600">{{ $message }}</p> @enderror
        </div>
        <div>
            <label for="cat-color" class="label">Colour</label>
            <select id="cat-color" wire:model="color" class="field">
                @foreach ($colors as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
            </select>
        </div>
        <button type="submit" class="btn-primary">Add</button>
    </form>

    <ul class="card divide-y divide-stone-200 dark:divide-stone-800">
        @forelse ($categories as $category)
            <li class="flex flex-wrap items-center gap-3 p-3" wire:key="cat-{{ $category->id }}">
                @if ($editingId === $category->id)
                    <form wire:submit="update" class="flex min-w-0 flex-1 flex-wrap items-center gap-2">
                        <input type="text" wire:model="editName" class="field min-w-0 flex-1" aria-label="Category name" required />
                        <select wire:model="editColor" class="field w-auto" aria-label="Colour">
                            @foreach ($colors as $c)<option value="{{ $c }}">{{ ucfirst($c) }}</option>@endforeach
                        </select>
                        <button type="submit" class="btn-primary">Save</button>
                        <button type="button" wire:click="cancelEdit" class="btn-secondary">Cancel</button>
                    </form>
                    @error('editName') <p class="w-full text-sm text-red-600">{{ $message }}</p> @enderror
                @else
                    <x-category-chip :category="$category" />
                    <span class="text-sm text-stone-500">{{ $category->recipes_count }} {{ \Illuminate\Support\Str::plural('recipe', $category->recipes_count) }}</span>
                    <div class="ml-auto flex gap-1">
                        <button type="button" wire:click="move({{ $category->id }}, -1)" class="btn-secondary px-2 py-1" @disabled($loop->first) aria-label="Move up">↑</button>
                        <button type="button" wire:click="move({{ $category->id }}, 1)" class="btn-secondary px-2 py-1" @disabled($loop->last) aria-label="Move down">↓</button>
                        <button type="button" wire:click="edit({{ $category->id }})" class="btn-secondary px-2 py-1">Edit</button>
                        <button type="button" wire:click="delete({{ $category->id }})" wire:confirm="Delete this category? Recipes are kept." class="btn-danger px-2 py-1">Delete</button>
                    </div>
                @endif
            </li>
        @empty
            <li class="p-6 text-center text-stone-500">No categories yet.</li>
        @endforelse
    </ul>
</div>
