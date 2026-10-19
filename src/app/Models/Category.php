<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Support\Str;

class Category extends Model
{
    public const COLORS = ['amber', 'orange', 'red', 'rose', 'lime', 'emerald', 'teal', 'sky', 'indigo', 'violet', 'stone'];

    protected $fillable = ['name', 'color', 'sort_order'];

    protected static function booted(): void
    {
        static::saving(function (Category $c) {
            if ($c->isDirty('name') || ! $c->slug) {
                $base = Str::slug($c->name) ?: 'category';
                $slug = $base;
                $i = 2;
                while (static::where('slug', $slug)->where('id', '!=', $c->id ?? 0)->exists()) {
                    $slug = $base.'-'.$i++;
                }
                $c->slug = $slug;
            }
        });
    }

    public function recipes(): BelongsToMany
    {
        return $this->belongsToMany(Recipe::class);
    }
}
