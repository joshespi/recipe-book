<?php

namespace App\Models;

use App\Support\UniqueSlug;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

class Category extends Model
{
    public const COLORS = ['amber', 'orange', 'red', 'rose', 'lime', 'emerald', 'teal', 'sky', 'indigo', 'violet', 'stone'];

    protected $fillable = ['name', 'color', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            if ($c->isDirty('name') || ! $c->slug) {
                $c->slug = UniqueSlug::make(static::query(), $c->name, 'category', $c->id);
            }
        });
    }

    /** Sarah's chosen order, then alphabetical. */
    public function scopeOrdered(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('name');
    }

    /** Adds a category at the end of the list. */
    public static function createLast(string $name, string $color): self
    {
        return static::create(['name' => $name, 'color' => $color, 'sort_order' => (int) static::max('sort_order') + 1]);
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }
}
