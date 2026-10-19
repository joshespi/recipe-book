<?php

namespace App\Livewire\Recipes;

use App\Jobs\ProcessPhoto;
use App\Models\Photo;
use App\Models\Recipe;
use Illuminate\Support\Str;
use Livewire\Attributes\Locked;
use Livewire\Attributes\On;
use Livewire\Component;
use Livewire\WithFileUploads;

/** Photo grid with lightbox. When editable (owner only) it also uploads, captions, reorders and deletes. */
class PhotoGallery extends Component
{
    use WithFileUploads;

    public Recipe $recipe;

    #[Locked]
    public bool $editable = false;

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> */
    public array $uploads = [];

    public function mount(Recipe $recipe, bool $editable = false): void
    {
        $this->recipe = $recipe;
        $this->editable = $editable && $this->ownerIsViewing();
    }

    private function ownerIsViewing(): bool
    {
        return auth()->check() && auth()->user()->can('update', $this->recipe);
    }

    /** Every mutating action re-checks the user; the editable flag only hides buttons. */
    private function guard(): void
    {
        abort_unless($this->ownerIsViewing(), 403);
    }

    private function photo(int $id): Photo
    {
        return $this->recipe->photos()->findOrFail($id);
    }

    #[On('cook-logged')]
    public function refresh(): void
    {
        $this->recipe->unsetRelation('photos');
    }

    public function updatedUploads(): void
    {
        $this->guard();

        $this->validate([
            'uploads' => 'array|max:20',
            'uploads.*' => 'image|mimes:jpeg,jpg,png,webp|max:20480',
        ], [
            'uploads.*.image' => 'That file is not a picture we can use. HEIC photos need to be shared as JPEG.',
            'uploads.*.mimes' => 'Please choose JPEG, PNG or WebP photos (HEIC is not supported).',
            'uploads.*.max' => 'Each photo must be 20 MB or smaller.',
        ]);

        $position = (int) $this->recipe->photos()->max('position');
        foreach ($this->uploads as $file) {
            $path = $file->storeAs(
                'photos/'.$this->recipe->id,
                Str::uuid().'.'.($file->extension() ?: 'jpg'),
                Photo::DISK,
            );
            $photo = $this->recipe->photos()->create(['disk_path' => $path, 'position' => ++$position]);
            ProcessPhoto::dispatch($photo->id);
        }

        $this->reset('uploads');
        $this->recipe->unsetRelation('photos');
    }

    public function setCover(int $id): void
    {
        $this->guard();
        $this->recipe->forceFill(['cover_photo_id' => $this->photo($id)->id])->save();
    }

    public function saveCaption(int $id, string $caption): void
    {
        $this->guard();
        $this->photo($id)->update(['caption' => Str::limit(trim($caption), 250, '') ?: null]);
    }

    public function move(int $id, int $direction): void
    {
        $this->guard();
        $photos = $this->recipe->photos()->get()->values();
        $index = $photos->search(fn ($p) => $p->id === $id);
        $target = $index === false ? false : $index + ($direction < 0 ? -1 : 1);
        if ($index === false || ! isset($photos[$target])) {
            return;
        }

        $order = $photos->pluck('id')->all();
        [$order[$index], $order[$target]] = [$order[$target], $order[$index]];
        foreach ($order as $position => $photoId) {
            Photo::whereKey($photoId)->update(['position' => $position + 1]);
        }
        $this->recipe->unsetRelation('photos');
    }

    public function deletePhoto(int $id): void
    {
        $this->guard();
        $photo = $this->photo($id);
        if ($this->recipe->cover_photo_id === $photo->id) {
            $this->recipe->forceFill(['cover_photo_id' => null])->save();
        }
        $photo->delete();
        $this->recipe->unsetRelation('photos');
    }

    public function render()
    {
        $photos = $this->recipe->photos()->get();

        return view('livewire.recipes.photo-gallery', [
            'photos' => $photos,
            'pending' => $photos->contains(fn ($p) => ! $p->isProcessed()),
        ]);
    }
}
