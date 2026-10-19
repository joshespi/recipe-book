<?php

namespace App\Livewire\Recipes;

use App\Models\Photo;
use App\Models\Recipe;
use Livewire\Component;
use Livewire\WithFileUploads;

/** "I made this!" log with notes, rating and photos taken while cooking. Owner only. */
class CookLogs extends Component
{
    use WithFileUploads;

    public Recipe $recipe;

    public bool $showForm = false;

    public string $cooked_on = '';

    public string $notes = '';

    public ?string $rating = null;

    public string $scale_used = '1';

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $photos = [];

    public function mount(Recipe $recipe): void
    {
        $this->authorize('update', $recipe);
        $this->recipe = $recipe;
        $this->cooked_on = now()->toDateString();
    }

    public function cancel(): void
    {
        $this->reset('showForm', 'notes', 'rating', 'photos');
        $this->resetErrorBag();
        $this->cooked_on = now()->toDateString();
    }

    public function save(): void
    {
        $this->authorize('update', $this->recipe);

        $data = $this->validate([
            'cooked_on' => 'required|date|before_or_equal:today',
            'notes' => 'nullable|string|max:5000',
            'rating' => 'nullable|integer|min:1|max:5',
            'scale_used' => 'required|numeric|min:0.125|max:50',
            ...Photo::uploadRules('photos', 10),
        ], Photo::uploadMessages('photos'));

        $log = $this->recipe->cookLogs()->create([
            'cooked_on' => $data['cooked_on'],
            'notes' => $data['notes'] ?: null,
            'rating' => filled($data['rating']) ? (int) $data['rating'] : null,
            'scale_used' => (float) $data['scale_used'],
        ]);

        foreach ($this->photos as $file) {
            $this->recipe->addPhoto($file, ['cook_log_id' => $log->id, 'taken_at' => $log->cooked_on]);
        }

        $this->cancel();
        $this->dispatch('cook-logged');
    }

    public function delete(int $id): void
    {
        $this->authorize('update', $this->recipe);
        $log = $this->recipe->cookLogs()->findOrFail($id);
        // Photos taken during this cook go with it, including their files.
        $log->photos->each->delete();
        $log->delete();
        $this->dispatch('cook-logged');
    }

    public function render()
    {
        return view('livewire.recipes.cook-logs', [
            'logs' => $this->recipe->cookLogs()->with('photos')->get(),
        ]);
    }
}
