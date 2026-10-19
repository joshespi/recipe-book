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
        Storage::fake('local');
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
            Storage::disk('local')->assertExists($photo->disk_path);
            $this->assertStringStartsWith('photos-raw/'.$this->recipe->id.'/', $photo->disk_path);
            Storage::disk('public')->assertDirectoryEmpty('photos');
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
        $a = $this->recipe->photos()->create(['disk_path' => 'photos/1/a.webp', 'thumb_path' => 'photos/1/a-t.webp', 'medium_path' => 'photos/1/a-m.webp', 'position' => 1]);
        $b = $this->recipe->photos()->create(['disk_path' => 'photos/1/b.webp', 'thumb_path' => 'photos/1/b-t.webp', 'medium_path' => 'photos/1/b-m.webp', 'position' => 2]);

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
        $path = $file->storeAs('photos-raw/'.$this->recipe->id, 'raw.jpg', 'local');
        $photo = $this->recipe->photos()->create(['disk_path' => $path]);

        (new ProcessPhoto($photo->id))->handle();

        $photo->refresh();
        $this->assertTrue($photo->isProcessed());
        foreach ([$photo->disk_path, $photo->medium_path, $photo->thumb_path] as $p) {
            $this->assertStringEndsWith('.webp', $p);
            Storage::disk('public')->assertExists($p);
        }
        Storage::disk('local')->assertMissing($path);

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
        $path = $this->jpeg('small.jpg', 300, 200)->storeAs('photos-raw/'.$this->recipe->id, 'raw.jpg', 'local');
        $photo = $this->recipe->photos()->create(['disk_path' => $path]);

        (new ProcessPhoto($photo->id))->handle();

        [$w] = getimagesizefromstring(Storage::disk('public')->get($photo->fresh()->medium_path));
        $this->assertSame(300, $w);
    }

    public function test_a_file_that_is_not_an_image_is_discarded(): void
    {
        Storage::disk('local')->put('photos-raw/1/bad.jpg', 'this is not an image');
        $photo = $this->recipe->photos()->create(['disk_path' => 'photos-raw/1/bad.jpg']);

        (new ProcessPhoto($photo->id))->handle();

        $this->assertNull(Photo::find($photo->id));
        Storage::disk('local')->assertMissing('photos-raw/1/bad.jpg');
    }

    public function test_an_unreadable_upload_is_retried_not_discarded(): void
    {
        $photo = $this->recipe->photos()->create(['disk_path' => 'photos-raw/1/missing.jpg']);

        $this->expectException(\RuntimeException::class);
        try {
            (new ProcessPhoto($photo->id))->handle();
        } finally {
            $this->assertNotNull(Photo::find($photo->id), 'the photo row survives so the job can be retried');
        }
    }

    public function test_unprocessed_photos_show_a_placeholder_and_never_a_raw_url(): void
    {
        $raw = $this->recipe->photos()->create(['disk_path' => 'photos-raw/1/new.jpg']);
        $done = $this->recipe->photos()->create(['disk_path' => 'photos/1/d.webp', 'thumb_path' => 'photos/1/d-t.webp', 'medium_path' => 'photos/1/d-m.webp']);

        $this->assertSame(Photo::PLACEHOLDER, $raw->thumbUrl());
        $this->assertSame(Photo::PLACEHOLDER, $raw->mediumUrl());
        $this->assertStringContainsString('d-t.webp', $done->thumbUrl());

        $this->recipe->update(['title' => 'x']);
        $this->recipe->forceFill(['cover_photo_id' => $raw->id])->save();
        $this->assertTrue($this->recipe->fresh(['coverPhoto', 'photos'])->cover()->is($done), 'an unprocessed photo is never the cover');
    }

    public function test_old_unprocessed_photos_stop_counting_as_pending(): void
    {
        $fresh = $this->recipe->photos()->create(['disk_path' => 'photos-raw/1/a.jpg']);
        $stale = $this->recipe->photos()->create(['disk_path' => 'photos-raw/1/b.jpg']);
        $stale->forceFill(['created_at' => now()->subMinutes(30)])->save();

        $this->assertTrue($fresh->isPending());
        $this->assertFalse($stale->fresh()->isPending());
    }

    public function test_cook_log_with_photos(): void
    {
        Queue::fake();

        Livewire::actingAs($this->owner)
            ->test(CookLogs::class, ['recipe' => $this->recipe])
            ->set('showForm', true)
            ->set('scale_used', '2')
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

        Storage::disk('local')->put('photos-raw/1/c.jpg', 'c');
        $log = $this->recipe->cookLogs()->create(['cooked_on' => now()->toDateString()]);
        $log->photos()->create(['recipe_id' => $this->recipe->id, 'disk_path' => 'photos-raw/1/c.jpg']);

        Livewire::actingAs($this->owner)
            ->test(CookLogs::class, ['recipe' => $this->recipe])
            ->call('delete', $log->id);

        $this->assertSame(0, $this->recipe->cookLogs()->count());
        $this->assertSame(0, Photo::count());
        Storage::disk('local')->assertMissing('photos-raw/1/c.jpg');
    }

    public function test_cook_logs_belong_to_the_owner_only(): void
    {
        Livewire::actingAs(User::factory()->create())
            ->test(CookLogs::class, ['recipe' => $this->recipe])
            ->assertForbidden();
    }
}
