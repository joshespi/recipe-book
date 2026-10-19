<?php

namespace App\Policies;

use App\Models\Recipe;
use App\Models\User;

/**
 * Single-owner app: the owner may do everything. Guests never reach a policy;
 * they only see recipes through the share/browse controller, which checks visibility itself.
 */
class RecipePolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function view(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->user_id;
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->user_id;
    }

    public function delete(User $user, Recipe $recipe): bool
    {
        return $user->id === $recipe->user_id;
    }
}
