<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Http\Request;

class SharedRecipeController extends Controller
{
    /** Read-only recipe page reached through the share link. Private recipes are a 404. */
    public function show(string $token)
    {
        $recipe = Recipe::findShared($token);
        $recipe->load(['categories', 'tags', 'photos', 'coverPhoto', 'ingredientGroups', 'ingredients', 'steps'])
            ->loadCount('cookLogs');

        return view('shared.show', ['recipe' => $recipe]);
    }

    /** Every recipe marked public, newest first. */
    public function browse(Request $request)
    {
        $category = $request->string('category')->toString();
        $tag = $request->string('tag')->toString();

        $recipes = Recipe::query()
            ->public()
            ->search($request->string('q')->toString())
            ->when($category !== '', fn ($q) => $q->whereHas('categories', fn ($c) => $c->where('slug', $category)))
            ->when($tag !== '', fn ($q) => $q->whereHas('tags', fn ($t) => $t->where('slug', $tag)))
            ->with(['categories', 'coverPhoto', 'photos'])
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $categories = Category::whereHas('recipes', fn ($q) => $q->public())->ordered()->get();

        return view('shared.browse', compact('recipes', 'categories', 'category', 'tag'));
    }
}
