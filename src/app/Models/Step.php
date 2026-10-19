<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class Step extends Model
{
    public $timestamps = false;

    protected $fillable = ['recipe_id', 'ingredient_group_id', 'position', 'body', 'timer_minutes'];
}
