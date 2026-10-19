<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    public const DISK = 'public';

    /** Uploads land here (not web-served) because they still carry EXIF/GPS data; ProcessPhoto moves the clean copies to DISK. */
    public const RAW_DISK = 'local';

    public const PLACEHOLDER = 'data:image/svg+xml,%3Csvg xmlns=%27http://www.w3.org/2000/svg%27 viewBox=%270 0 4 3%27%3E%3Crect width=%274%27 height=%273%27 fill=%27%23d6d3d1%27/%3E%3C/svg%3E';

    /** Largest photo accepted, in KB. Livewire's temporary upload limit (config/livewire.php) uses it too. */
    public const MAX_KB = 20480;

    /** Rules for a multi-file upload field such as "uploads". */
    public static function uploadRules(string $field, int $maxFiles): array
    {
        return [
            $field => "array|max:{$maxFiles}",
            "{$field}.*" => 'image|mimes:jpeg,jpg,png,webp|max:'.self::MAX_KB,
        ];
    }

    public static function uploadMessages(string $field): array
    {
        return [
            "{$field}.*.image" => 'That file is not a picture we can use. HEIC photos need to be shared as JPEG.',
            "{$field}.*.mimes" => 'Please choose JPEG, PNG or WebP photos (HEIC is not supported).',
            "{$field}.*.max" => 'Each photo must be '.(self::MAX_KB / 1024).' MB or smaller.',
        ];
    }

    protected $fillable = [
        'recipe_id', 'cook_log_id', 'disk_path', 'thumb_path', 'medium_path',
        'width', 'height', 'caption', 'position', 'taken_at',
    ];

    protected function casts(): array
    {
        return ['taken_at' => 'datetime'];
    }

    protected static function booted(): void
    {
        static::deleting(function (Photo $photo) {
            // Covers every delete path (gallery, cook log, failed processing).
            Recipe::whereKey($photo->recipe_id)->where('cover_photo_id', $photo->id)->update(['cover_photo_id' => null]);

            if ($photo->isProcessed()) {
                Storage::disk(self::DISK)->delete([$photo->disk_path, $photo->thumb_path, $photo->medium_path]);
            } else {
                Storage::disk(self::RAW_DISK)->delete($photo->disk_path);
            }
        });
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function cookLog(): BelongsTo
    {
        return $this->belongsTo(CookLog::class);
    }

    public function isProcessed(): bool
    {
        return $this->thumb_path !== null && $this->medium_path !== null;
    }

    /** A photo still waiting for (or past) processing, as opposed to one that has failed for good. */
    public function isPending(): bool
    {
        return ! $this->isProcessed() && $this->created_at?->gt(now()->subMinutes(10));
    }

    /** The raw upload sits on a private disk until processing, so show a placeholder meanwhile. */
    public function thumbUrl(): string
    {
        return $this->thumb_path ? Storage::disk(self::DISK)->url($this->thumb_path) : self::PLACEHOLDER;
    }

    public function mediumUrl(): string
    {
        return $this->medium_path ? Storage::disk(self::DISK)->url($this->medium_path) : self::PLACEHOLDER;
    }
}
