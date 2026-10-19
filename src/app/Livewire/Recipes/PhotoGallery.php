<?php

namespace App\Livewire\Recipes;

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

    /** Re-renders (render() always reads the photos fresh) when a cook log adds or removes photos. */
    #[On('cook-logged')]
    public function refresh(): void {}

    public function updatedUploads(): void
    {
        $this->guard();

        $this->validate(Photo::uploadRules('uploads', 10), Photo::uploadMessages('uploads'));

        foreach ($this->uploads as $file) {
            $this->recipe->addPhoto($file);
        }

        $this->reset('uploads');
    }

    public function setCover(int $id): void
    {
        $this->guard();
        $this->recipe->setCover($this->photo($id));
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
    }

    public function deletePhoto(int $id): void
    {
        $this->guard();
        $this->photo($id)->delete();
        $this->recipe->refresh();
    }

    public function render()
    {
        $photos = $this->recipe->photos()->get();

        return view('livewire.recipes.photo-gallery', [
            'photos' => $photos,
            'pending' => $photos->contains(fn ($p) => $p->isPending()),
        ]);
    }
}
