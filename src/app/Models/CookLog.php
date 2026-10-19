<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CookLog extends Model
{
    protected $fillable = ['recipe_id', 'cooked_on', 'notes', 'rating', 'scale_used'];

    protected function casts(): array
    {
        return [
            'cooked_on' => 'date',
            'scale_used' => 'float',
        ];
    }

    public function recipe(): BelongsTo
    {
        return $this->belongsTo(Recipe::class);
    }

    public function photos(): HasMany
    {
        return $this->hasMany(Photo::class)->orderBy('position')->orderBy('id');
    }
}
