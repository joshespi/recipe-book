<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class Tag extends Model
{
    protected $fillable = ['name', 'slug'];

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }

    /**
     * Turns "Freezer-friendly, kid favorite,, FREEZER-FRIENDLY" into tag models,
     * creating the missing ones. Duplicates (by slug) are ignored.
     *
     * @return Collection<int, Tag>
     */
    public static function fromText(string $text): Collection
    {
        return collect(preg_split('/[,\n]+/u', $text))
            ->map(fn ($name) => trim(preg_replace('/^#+/u', '', trim($name))))
            ->filter(fn ($name) => $name !== '' && mb_strlen($name) <= 40)
            ->unique(fn ($name) => Str::slug($name))
            ->filter(fn ($name) => Str::slug($name) !== '')
            ->take(30)
            ->map(fn ($name) => static::firstOrCreate(['slug' => Str::slug($name)], ['name' => $name]))
            ->values();
    }
}
