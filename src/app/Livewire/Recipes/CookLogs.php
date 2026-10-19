<?php

namespace App\Livewire\Recipes;

use App\Jobs\ProcessPhoto;
use App\Models\CookLog;
use App\Models\Photo;
use App\Models\Recipe;
use Illuminate\Support\Str;
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

    public function mount(Recipe $recipe, string $scale = '1'): void
    {
        $this->authorize('update', $recipe);
        $this->recipe = $recipe;
        $this->scale_used = $scale;
        $this->cooked_on = now()->toDateString();
    }

    public function open(): void
    {
        $this->showForm = true;
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
            'photos' => 'array|max:10',
            'photos.*' => 'image|mimes:jpeg,jpg,png,webp|max:20480',
        ], [
            'photos.*.image' => 'That file is not a picture we can use. HEIC photos need to be shared as JPEG.',
            'photos.*.mimes' => 'Please choose JPEG, PNG or WebP photos (HEIC is not supported).',
            'photos.*.max' => 'Each photo must be 20 MB or smaller.',
        ]);

        $log = $this->recipe->cookLogs()->create([
            'cooked_on' => $data['cooked_on'],
            'notes' => $data['notes'] ?: null,
            'rating' => $data['rating'] !== null && $data['rating'] !== '' ? (int) $data['rating'] : null,
            'scale_used' => (float) $data['scale_used'],
        ]);

        $position = (int) $this->recipe->photos()->max('position');
        foreach ($this->photos as $file) {
            $path = $file->storeAs(
                'photos/'.$this->recipe->id,
                Str::uuid().'.'.($file->extension() ?: 'jpg'),
                Photo::DISK,
            );
            $photo = $this->recipe->photos()->create([
                'cook_log_id' => $log->id,
                'disk_path' => $path,
                'position' => ++$position,
                'taken_at' => $log->cooked_on,
            ]);
            ProcessPhoto::dispatch($photo->id);
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
