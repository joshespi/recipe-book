<?php

namespace App\Jobs;

use App\Models\Photo;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Intervention\Image\Exceptions\DecoderException;
use Intervention\Image\Exceptions\NotSupportedException;
use Intervention\Image\ImageManager;

/**
 * Turns a freshly uploaded photo into a privacy-safe set of WebP files.
 *
 * The raw upload waits on a private disk (it still has EXIF data, including GPS). This job
 * auto-orients and re-encodes it, which drops that data, writes full, medium and thumbnail copies to
 * the public disk, and deletes the raw file. Only a file that cannot be decoded is deleted; other
 * failures (disk full, restart) are retried and otherwise leave the photo for a later look.
 */
class ProcessPhoto implements ShouldQueue
{
    use Queueable;

    /** Refuse anything bigger than this many pixels: GD needs about 4 bytes per pixel to decode. */
    public const MAX_PIXELS = 50_000_000;

    public int $tries = 2;

    public int $timeout = 120;

    public function __construct(public int $photoId) {}

    public function handle(): void
    {
        $photo = Photo::find($this->photoId);
        if (! $photo || $photo->isProcessed()) {
            return;
        }

        $raw = Storage::disk(Photo::RAW_DISK);
        // An unreadable file is a storage problem (retry later), not proof that the upload was bad.
        $source = $raw->get($photo->disk_path) ?? throw new \RuntimeException("Cannot read the upload for photo {$photo->id}");

        $size = @getimagesizefromstring($source);
        if ($size === false || $size[0] * $size[1] > self::MAX_PIXELS) {
            $this->discard($photo, 'not an image, or too many pixels');

            return;
        }

        try {
            // Decode once, then encode each size before shrinking the same image further.
            $image = ImageManager::gd()->read($source)->scaleDown(width: 2400, height: 2400);
        } catch (DecoderException|NotSupportedException $e) {
            $this->discard($photo, $e->getMessage());

            return;
        }

        $fullWebp = (string) $image->toWebp(quality: 88);
        $width = $image->width();
        $height = $image->height();
        $mediumWebp = (string) $image->scaleDown(width: 1200, height: 1200)->toWebp(quality: 82);
        $thumbWebp = (string) $image->scaleDown(width: 400, height: 400)->toWebp(quality: 78);

        $public = Storage::disk(Photo::DISK);
        $dir = 'photos/'.$photo->recipe_id;
        $name = (string) Str::uuid();
        $paths = [
            'disk_path' => "{$dir}/{$name}.webp",
            'medium_path' => "{$dir}/{$name}-medium.webp",
            'thumb_path' => "{$dir}/{$name}-thumb.webp",
        ];

        $public->put($paths['disk_path'], $fullWebp);
        $public->put($paths['medium_path'], $mediumWebp);
        $public->put($paths['thumb_path'], $thumbWebp);

        $rawPath = $photo->disk_path;
        $photo->forceFill($paths + ['width' => $width, 'height' => $height])->save();
        $raw->delete($rawPath);
    }

    private function discard(Photo $photo, string $reason): void
    {
        Log::warning("Photo {$photo->id} discarded: {$reason}");
        $photo->delete();
    }
}
