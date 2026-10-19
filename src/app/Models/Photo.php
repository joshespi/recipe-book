<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Photo extends Model
{
    public const DISK = 'public';

    public const UPLOAD_RULES = 'image|mimes:jpeg,jpg,png,webp|max:20480';

    public const UPLOAD_MESSAGES = [
        '*.image' => 'That file is not a picture we can use. HEIC photos need to be shared as JPEG.',
        '*.mimes' => 'Please choose JPEG, PNG or WebP photos (HEIC is not supported).',
        '*.max' => 'Each photo must be 20 MB or smaller.',
    ];

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
            Storage::disk(self::DISK)->delete(array_filter([
                $photo->disk_path, $photo->thumb_path, $photo->medium_path,
            ]));
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

    public function url(): string
    {
        return Storage::disk(self::DISK)->url($this->disk_path);
    }

    /** Falls back to the original while the queued job hasn't produced the resized copies. */
    public function thumbUrl(): string
    {
        return Storage::disk(self::DISK)->url($this->thumb_path ?? $this->disk_path);
    }

    public function mediumUrl(): string
    {
        return Storage::disk(self::DISK)->url($this->medium_path ?? $this->disk_path);
    }
}
