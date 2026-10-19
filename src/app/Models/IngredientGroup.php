<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class IngredientGroup extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipe_id', 'name', 'position'];

    public function ingredients(): HasMany
    {
        return $this->hasMany(Ingredient::class)->orderBy('position');
    }
}
