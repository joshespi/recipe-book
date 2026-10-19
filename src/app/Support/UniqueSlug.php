<?php

namespace App\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Str;

final class UniqueSlug
{
    /** "Soup" -> "soup", or "soup-2", "soup-3" ... when taken in $query. */
    public static function make(Builder $query, string $text, string $fallback, ?int $ignoreId = null): string
    {
        $base = Str::slug($text) ?: $fallback;
        $taken = (clone $query)
            ->where(fn ($q) => $q->where('slug', $base)->orWhere('slug', 'like', $base.'-%'))
            ->when($ignoreId, fn ($q) => $q->whereKeyNot($ignoreId))
            ->pluck('slug')
            ->flip();

        $slug = $base;
        for ($i = 2; isset($taken[$slug]); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
