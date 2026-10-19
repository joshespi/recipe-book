<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\ImageManager;
use Throwable;

/**
 * Turns a freshly uploaded photo into a privacy-safe set of WebP files:
 * the upload is auto-oriented and re-encoded (which drops EXIF, including GPS),
 * then a medium and a thumbnail copy are written next to it.
 */
class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $photoId) {}

    public function handle(): void
    {
        $photo = Photo::find($this->photoId);
        if (! $photo || $photo->isProcessed()) {
            return;
        }

        $disk = Storage::disk(Photo::DISK);
        $source = $disk->get($photo->disk_path);
        $manager = ImageManager::gd();

        $full = $manager->read($source)->scaleDown(width: 2400, height: 2400);
        $medium = $manager->read($source)->scaleDown(width: 1200, height: 1200);
        $thumb = $manager->read($source)->scaleDown(width: 400, height: 400);

        $dir = 'photos/'.$photo->recipe_id;
        $name = (string) Str::uuid();
        $paths = [
            'disk_path' => "{$dir}/{$name}.webp",
            'medium_path' => "{$dir}/{$name}-medium.webp",
            'thumb_path' => "{$dir}/{$name}-thumb.webp",
        ];

        $disk->put($paths['disk_path'], (string) $full->toWebp(quality: 88));
        $disk->put($paths['medium_path'], (string) $medium->toWebp(quality: 82));
        $disk->put($paths['thumb_path'], (string) $thumb->toWebp(quality: 78));

        $original = $photo->disk_path;
        $photo->forceFill($paths + ['width' => $full->width(), 'height' => $full->height()])->save();

        // The raw upload still carries EXIF/GPS, so it must not stay on the public disk.
        if ($original !== $paths['disk_path']) {
            $disk->delete($original);
        }
    }

    /** A file we cannot decode is removed rather than left half-processed on the public disk. */
    public function failed(Throwable $e): void
    {
        Photo::find($this->photoId)?->delete();
    }
}
