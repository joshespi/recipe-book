<?php

namespace Database\Factories;

use App\Models\Recipe;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/** @extends Factory<Recipe> */
class RecipeFactory extends Factory
{
    protected $model = Recipe::class;

    public function definition(): array
    {
        return [
            'user_id' => User::factory(),
            'title' => ucfirst(fake()->unique()->words(3, true)),
            'description' => fake()->sentence(),
            'servings' => 4,
            'visibility' => Recipe::UNLISTED,
        ];
    }

    public function private(): static
    {
        return $this->state(['visibility' => Recipe::PRIVATE]);
    }

    public function unlisted(): static
    {
        return $this->state(['visibility' => Recipe::UNLISTED]);
    }

    public function public(): static
    {
        return $this->state(['visibility' => Recipe::PUBLIC]);
    }
}
