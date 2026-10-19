<?php

use App\Http\Controllers\CookModeController;
use App\Http\Controllers\ExportController;
use App\Http\Controllers\SharedRecipeController;
use App\Livewire\Categories\Manage as ManageCategories;
use App\Livewire\Recipes\Form as RecipeForm;
use App\Livewire\Recipes\Index as RecipeIndex;
use App\Livewire\Recipes\Show as RecipeShow;
use App\Livewire\ShoppingList;
use Illuminate\Support\Facades\Route;
use Livewire\Volt\Volt;

Route::get('/', fn () => auth()->check()
    ? redirect()->route('recipes.index')
    : redirect()->route('browse'))->name('home');

// Public: the share link and the list of recipes marked public.
Route::get('browse', [SharedRecipeController::class, 'browse'])->name('browse');
Route::get('s/{token}', [SharedRecipeController::class, 'show'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->name('shared.show');
Route::get('s/{token}/cook', [CookModeController::class, 'shared'])
    ->where('token', '[A-Za-z0-9]{32}')
    ->name('shared.cook');

Route::middleware(['auth'])->group(function () {
    Route::redirect('dashboard', 'recipes')->name('dashboard');

    Route::get('recipes', RecipeIndex::class)->name('recipes.index');
    Route::get('recipes/create', RecipeForm::class)->name('recipes.create');
    Route::get('recipes/{recipe}', RecipeShow::class)->name('recipes.show');
    Route::get('recipes/{recipe}/edit', RecipeForm::class)->name('recipes.edit');
    Route::get('recipes/{recipe}/cook', [CookModeController::class, 'owner'])->name('recipes.cook');
    Route::get('categories', ManageCategories::class)->name('categories.index');
    Route::get('shopping-list', ShoppingList::class)->name('shopping.index');

    Route::get('export/recipes.json', [ExportController::class, 'json'])->name('export.json');
    Route::get('export/recipes.md', [ExportController::class, 'markdown'])->name('export.markdown');

    Route::redirect('settings', 'settings/profile');

    Volt::route('settings/profile', 'settings.profile')->name('settings.profile');
    Volt::route('settings/password', 'settings.password')->name('settings.password');
    Volt::route('settings/appearance', 'settings.appearance')->name('settings.appearance');
});

require __DIR__.'/auth.php';
