<?php

namespace Tests\Feature;

use App\Jobs\ProcessPhoto;
use App\Livewire\Recipes\CookLogs;
use App\Livewire\Recipes\PhotoGallery;
use App\Models\Photo;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Livewire\Livewire;
use Tests\TestCase;

class PhotoUploadTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    private Recipe $recipe;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
        $this->owner = User::factory()->create();
        $this->recipe = Recipe::factory()->for($this->owner)->create();
    }

    /** A real JPEG, big enough to be resized. */
    private function jpeg(string $name = 'dish.jpg', int $w = 3000, int $h = 2000): UploadedFile
    {
        $image = imagecreatetruecolor($w, $h);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 120, 40));
        ob_start();
        imagejpeg($image);
        $data = ob_get_clean();

        return UploadedFile::fake()->createWithContent($name, $data);
    }

    public function test_owner_uploads_photos_which_are_stored_and_queued(): void
    {
        Queue::fake();

        Livewire::actingAs($this->owner)
            ->test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true])
            ->set('uploads', [$this->jpeg('a.jpg', 100, 80), $this->jpeg('b.jpg', 100, 80)])
            ->assertHasNoErrors();

        $photos = $this->recipe->photos()->get();
        $this->assertCount(2, $photos);
        foreach ($photos as $photo) {
            Storage::disk('public')->assertExists($photo->disk_path);
            $this->assertStringStartsWith('photos/'.$this->recipe->id.'/', $photo->disk_path);
        }
        Queue::assertPushed(ProcessPhoto::class, 2);
    }

    public function test_non_images_and_oversize_files_are_rejected(): void
    {
        Queue::fake();

        Livewire::actingAs($this->owner)
            ->test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true])
            ->set('uploads', [UploadedFile::fake()->create('notes.pdf', 10, 'application/pdf')])
            ->assertHasErrors(['uploads.0']);

        Livewire::actingAs($this->owner)
            ->test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true])
            ->set('uploads', [UploadedFile::fake()->image('huge.jpg')->size(25000)])
            ->assertHasErrors(['uploads.0']);

        $this->assertSame(0, Photo::count());
        Queue::assertNothingPushed();
    }

    public function test_guests_and_other_users_cannot_upload_or_manage(): void
    {
        Queue::fake();
        $photo = $this->recipe->photos()->create(['disk_path' => 'photos/x.webp']);

        // A guest viewing the share page gets a read-only gallery; forging the call still fails.
        Livewire::test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true])
            ->assertSet('editable', false)
            ->call('deletePhoto', $photo->id)
            ->assertForbidden();

        Livewire::actingAs(User::factory()->create())
            ->test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true])
            ->assertSet('editable', false)
            ->set('uploads', [$this->jpeg('a.jpg', 50, 50)])
            ->assertForbidden();

        $this->assertSame(1, Photo::count());
        Queue::assertNothingPushed();
    }

    public function test_cover_caption_reorder_and_delete(): void
    {
        Storage::disk('public')->put('photos/1/a.webp', 'a');
        Storage::disk('public')->put('photos/1/b.webp', 'b');
        $a = $this->recipe->photos()->create(['disk_path' => 'photos/1/a.webp', 'position' => 1]);
        $b = $this->recipe->photos()->create(['disk_path' => 'photos/1/b.webp', 'position' => 2]);

        $component = Livewire::actingAs($this->owner)->test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true]);

        $component->call('setCover', $b->id);
        $this->assertSame($b->id, $this->recipe->fresh()->cover_photo_id);

        $component->call('saveCaption', $a->id, '  Fresh out of the oven  ');
        $this->assertSame('Fresh out of the oven', $a->fresh()->caption);

        $component->call('move', $b->id, -1);
        $this->assertSame([$b->id, $a->id], $this->recipe->photos()->pluck('id')->all());

        $component->call('deletePhoto', $b->id);
        $this->assertNull($this->recipe->fresh()->cover_photo_id);
        Storage::disk('public')->assertMissing('photos/1/b.webp');
        Storage::disk('public')->assertExists('photos/1/a.webp');
    }

    public function test_photo_belonging_to_another_recipe_cannot_be_touched(): void
    {
        $other = Recipe::factory()->for($this->owner)->create();
        $foreign = $other->photos()->create(['disk_path' => 'photos/9/z.webp']);

        Livewire::actingAs($this->owner)
            ->test(PhotoGallery::class, ['recipe' => $this->recipe, 'editable' => true])
            ->call('deletePhoto', $foreign->id)
            ->assertNotFound();

        $this->assertNotNull($foreign->fresh());
    }

    public function test_process_photo_job_resizes_and_strips_the_original(): void
    {
        $file = $this->jpeg('big.jpg', 3000, 2000);
        $path = $file->storeAs('photos/'.$this->recipe->id, 'raw.jpg', 'public');
        $photo = $this->recipe->photos()->create(['disk_path' => $path]);

        (new ProcessPhoto($photo->id))->handle();

        $photo->refresh();
        $this->assertTrue($photo->isProcessed());
        foreach ([$photo->disk_path, $photo->medium_path, $photo->thumb_path] as $p) {
            $this->assertStringEndsWith('.webp', $p);
            Storage::disk('public')->assertExists($p);
        }
        Storage::disk('public')->assertMissing($path);

        [$w, $h] = getimagesizefromstring(Storage::disk('public')->get($photo->medium_path));
        $this->assertSame(1200, $w);
        [$tw] = getimagesizefromstring(Storage::disk('public')->get($photo->thumb_path));
        $this->assertSame(400, $tw);
        [$fw] = getimagesizefromstring(Storage::disk('public')->get($photo->disk_path));
        $this->assertSame(2400, $fw);
        $this->assertSame(2400, $photo->width);
        $this->assertSame(1600, $photo->height);
    }

    public function test_process_photo_job_does_not_upscale_small_images(): void
    {
        $path = $this->jpeg('small.jpg', 300, 200)->storeAs('photos/'.$this->recipe->id, 'raw.jpg', 'public');
        $photo = $this->recipe->photos()->create(['disk_path' => $path]);

        (new ProcessPhoto($photo->id))->handle();

        [$w] = getimagesizefromstring(Storage::disk('public')->get($photo->fresh()->medium_path));
        $this->assertSame(300, $w);
    }

    public function test_undecodable_upload_is_removed_when_the_job_fails(): void
    {
        Storage::disk('public')->put('photos/1/bad.jpg', 'this is not an image');
        $photo = $this->recipe->photos()->create(['disk_path' => 'photos/1/bad.jpg']);

        (new ProcessPhoto($photo->id))->failed(new \RuntimeException('decode'));

        $this->assertNull(Photo::find($photo->id));
        Storage::disk('public')->assertMissing('photos/1/bad.jpg');
    }

    public function test_cook_log_with_photos(): void
    {
        Queue::fake();

        Livewire::actingAs($this->owner)
            ->test(CookLogs::class, ['recipe' => $this->recipe, 'scale' => '2'])
            ->call('open')
            ->set('notes', 'Used less sugar')
            ->set('rating', '5')
            ->set('photos', [$this->jpeg('me.jpg', 100, 80)])
            ->call('save')
            ->assertHasNoErrors()
            ->assertSet('showForm', false)
            ->assertSee('Used less sugar');

        $log = $this->recipe->cookLogs()->first();
        $this->assertSame(5, $log->rating);
        $this->assertEquals(2.0, $log->scale_used);
        $this->assertCount(1, $log->photos);
        Queue::assertPushed(ProcessPhoto::class, 1);
    }

    public function test_cook_log_rejects_future_dates_and_can_be_deleted_with_photos(): void
    {
        Livewire::actingAs($this->owner)
            ->test(CookLogs::class, ['recipe' => $this->recipe])
            ->set('cooked_on', now()->addDays(3)->toDateString())
            ->call('save')
            ->assertHasErrors(['cooked_on']);

        Storage::disk('public')->put('photos/1/c.webp', 'c');
        $log = $this->recipe->cookLogs()->create(['cooked_on' => now()->toDateString()]);
        $log->photos()->create(['recipe_id' => $this->recipe->id, 'disk_path' => 'photos/1/c.webp']);

        Livewire::actingAs($this->owner)
            ->test(CookLogs::class, ['recipe' => $this->recipe])
            ->call('delete', $log->id);

        $this->assertSame(0, $this->recipe->cookLogs()->count());
        $this->assertSame(0, Photo::count());
        Storage::disk('public')->assertMissing('photos/1/c.webp');
    }

    public function test_cook_logs_belong_to_the_owner_only(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CookLogs::class, ['recipe' => $this->recipe])
            ->assertForbidden();
    }
}
