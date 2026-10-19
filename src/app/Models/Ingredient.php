<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Ingredient extends Model
{
    public $timestamps = false;

    protected $fillable = [
        'recipe_id', 'ingredient_group_id', 'position',
        'quantity', 'quantity_max', 'unit', 'name', 'note', 'original_text',
    ];

    protected function casts(): array
    {
        return [
            'quantity' => 'float',
            'quantity_max' => 'float',
        ];
    }
}
