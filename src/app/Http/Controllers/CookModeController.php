<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Support\RecipeScaler;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

/** Full-screen, one-step-at-a-time view for cooking. Honors ?scale= and ?units= from the recipe page. */
class CookModeController extends Controller
{
    public function owner(Request $request, Recipe $recipe)
    {
        Gate::authorize('view', $recipe);

        return $this->render($request, $recipe, route('recipes.show', $recipe));
    }

    public function shared(Request $request, string $token)
    {
        return $this->render($request, Recipe::findShared($token), route('shared.show', $token));
    }

    private function render(Request $request, Recipe $recipe, string $backUrl)
    {
        $factor = RecipeScaler::factorFromInput($request->query('scale'));
        $query = array_filter($request->only('scale', 'units'));

        $recipe->load(['ingredientGroups', 'ingredients', 'steps']);

        return view('cook', [
            'recipe' => $recipe,
            'factor' => $factor,
            // RecipeScaler falls back to the original units for anything it does not know.
            'sections' => RecipeScaler::sections($recipe, $factor, (string) $request->query('units')),
            'steps' => $recipe->steps->map(fn ($s) => ['body' => $s->body, 'timer' => $s->timer_minutes])->values(),
            'backUrl' => $backUrl.($query ? '?'.http_build_query($query) : ''),
            'noindex' => ! $recipe->isIndexable(),
        ]);
    }
}
