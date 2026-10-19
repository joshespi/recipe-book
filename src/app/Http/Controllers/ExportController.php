<?php

namespace App\Http\Controllers;

use App\Models\Recipe;
use App\Support\Quantity;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\StreamedResponse;

/** Download everything in an open format, so the recipes are never locked into this app. */
class ExportController extends Controller
{
    private function recipes(Request $request)
    {
        return Recipe::query()
            ->where('user_id', $request->user()->id)
            ->with(['categories', 'tags', 'ingredientGroups', 'ingredients', 'steps', 'cookLogs', 'photos'])
            ->orderBy('title')
            ->lazy(100);
    }

    public function json(Request $request): StreamedResponse
    {
        $name = 'recipes-'.now()->format('Y-m-d').'.json';

        return response()->streamDownload(function () use ($request) {
            echo '{"exported_at":'.json_encode(now()->toIso8601String()).',"recipes":[';
            $first = true;
            foreach ($this->recipes($request) as $recipe) {
                echo ($first ? '' : ',').json_encode($this->toArray($recipe), JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_PRETTY_PRINT);
                $first = false;
            }
            echo ']}';
        }, $name, ['Content-Type' => 'application/json; charset=utf-8']);
    }

    public function markdown(Request $request): StreamedResponse
    {
        $name = 'recipes-'.now()->format('Y-m-d').'.md';

        return response()->streamDownload(function () use ($request) {
            foreach ($this->recipes($request) as $recipe) {
                echo $this->toMarkdown($recipe)."\n\n---\n\n";
            }
        }, $name, ['Content-Type' => 'text/markdown; charset=utf-8']);
    }

    private function toArray(Recipe $recipe): array
    {
        return [
            'title' => $recipe->title,
            'description' => $recipe->description,
            'servings' => $recipe->servings,
            'yield_label' => $recipe->yield_label,
            'prep_minutes' => $recipe->prep_minutes,
            'cook_minutes' => $recipe->cook_minutes,
            'total_minutes' => $recipe->total_minutes,
            'source_name' => $recipe->source_name,
            'source_url' => $recipe->source_url,
            'notes' => $recipe->notes,
            'visibility' => $recipe->visibility,
            'favorite' => $recipe->is_favorite,
            'rating' => $recipe->rating,
            'categories' => $recipe->categories->pluck('name')->all(),
            'tags' => $recipe->tags->pluck('name')->all(),
            'ingredients' => $recipe->ingredients->map(fn ($i) => [
                'group' => $recipe->ingredientGroups->firstWhere('id', $i->ingredient_group_id)?->name,
                'text' => $i->original_text,
                'quantity' => $i->quantity,
                'quantity_max' => $i->quantity_max,
                'unit' => $i->unit,
                'name' => $i->name,
                'note' => $i->note,
            ])->values()->all(),
            'steps' => $recipe->steps->map(fn ($s) => ['text' => $s->body, 'timer_minutes' => $s->timer_minutes])->values()->all(),
            'times_made' => $recipe->cookLogs->map(fn ($l) => [
                'date' => $l->cooked_on->toDateString(), 'rating' => $l->rating, 'notes' => $l->notes,
            ])->values()->all(),
            'photos' => $recipe->photos->map(fn ($p) => ['file' => $p->disk_path, 'caption' => $p->caption])->values()->all(),
            'created_at' => $recipe->created_at?->toIso8601String(),
        ];
    }

    private function toMarkdown(Recipe $recipe): string
    {
        $md = ['# '.$recipe->title];

        if ($recipe->description) {
            $md[] = $recipe->description;
        }

        $facts = array_filter([
            $recipe->servings ? 'Serves: '.Quantity::format($recipe->servings).($recipe->yield_label ? ' ('.$recipe->yield_label.')' : '') : null,
            $recipe->prep_minutes !== null ? "Prep: {$recipe->prep_minutes} min" : null,
            $recipe->cook_minutes !== null ? "Cook: {$recipe->cook_minutes} min" : null,
            $recipe->categories->isNotEmpty() ? 'Categories: '.$recipe->categories->pluck('name')->join(', ') : null,
            $recipe->tags->isNotEmpty() ? 'Tags: '.$recipe->tags->pluck('name')->join(', ') : null,
        ]);
        if ($facts) {
            $md[] = implode("  \n", $facts);
        }

        $md[] = '## Ingredients';
        $lines = [];
        $group = false;
        foreach ($recipe->ingredients as $ingredient) {
            $name = $recipe->ingredientGroups->firstWhere('id', $ingredient->ingredient_group_id)?->name;
            if ($name !== $group) {
                $lines[] = ($lines ? "\n" : '').($name ? "**{$name}**\n" : '');
                $group = $name;
            }
            $lines[] = '- '.($ingredient->original_text ?: $ingredient->name);
        }
        $md[] = trim(implode("\n", $lines));

        $md[] = '## Method';
        $md[] = $recipe->steps->map(fn ($s, $n) => ($n + 1).'. '.str_replace("\n", "\n   ", $s->body))->implode("\n");

        if ($recipe->notes) {
            $md[] = "## Notes\n\n".$recipe->notes;
        }
        if ($recipe->source_name || $recipe->source_url) {
            $md[] = 'Source: '.trim(($recipe->source_name ?? '').' '.($recipe->source_url ? '<'.$recipe->source_url.'>' : ''));
        }

        return implode("\n\n", $md);
    }
}
