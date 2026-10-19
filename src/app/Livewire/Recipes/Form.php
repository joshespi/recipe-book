<?php

namespace App\Livewire\Recipes;

use App\Models\Category;
use App\Models\Recipe;
use App\Support\IngredientParser;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;

#[Layout('components.layouts.app')]
class Form extends Component
{
    public ?Recipe $recipe = null;

    public string $title = '';

    public string $description = '';

    public ?string $servings = null;

    public string $yield_label = '';

    public ?string $prep_minutes = null;

    public ?string $cook_minutes = null;

    public string $source_name = '';

    public string $source_url = '';

    public string $notes = '';

    public string $visibility = Recipe::UNLISTED;

    /** @var list<int|string> */
    public array $categoryIds = [];

    public string $newCategory = '';

    /** One ingredient per line; "# Heading" starts a group. */
    public string $ingredientsText = '';

    /** @var list<array{body: string, timer: ?string}> */
    public array $steps = [];

    public function mount(?Recipe $recipe = null): void
    {
        if (! $recipe || ! $recipe->exists) {
            $this->authorize('create', Recipe::class);
            $this->steps = [['body' => '', 'timer' => null]];

            return;
        }

        $this->authorize('update', $recipe);
        $recipe->load(['categories', 'ingredientGroups', 'ingredients', 'steps']);
        $this->recipe = $recipe;

        $this->title = $recipe->title;
        $this->description = (string) $recipe->description;
        $this->servings = $recipe->servings !== null ? (string) (float) $recipe->servings : null;
        $this->yield_label = (string) $recipe->yield_label;
        $this->prep_minutes = $recipe->prep_minutes !== null ? (string) $recipe->prep_minutes : null;
        $this->cook_minutes = $recipe->cook_minutes !== null ? (string) $recipe->cook_minutes : null;
        $this->source_name = (string) $recipe->source_name;
        $this->source_url = (string) $recipe->source_url;
        $this->notes = (string) $recipe->notes;
        $this->visibility = $recipe->visibility;
        $this->categoryIds = $recipe->categories->pluck('id')->all();
        $this->ingredientsText = $this->ingredientsToText($recipe);
        $this->steps = $recipe->steps
            ->map(fn ($s) => ['body' => $s->body, 'timer' => $s->timer_minutes !== null ? (string) $s->timer_minutes : null])
            ->all() ?: [['body' => '', 'timer' => null]];
    }

    private function ingredientsToText(Recipe $recipe): string
    {
        $lines = [];
        $line = fn ($i) => $i->original_text ?: trim(($i->quantity ?? '').' '.($i->unit ?? '').' '.$i->name);

        foreach ($recipe->ingredients->whereNull('ingredient_group_id') as $i) {
            $lines[] = $line($i);
        }
        foreach ($recipe->ingredientGroups as $group) {
            $lines[] = '';
            $lines[] = '# '.$group->name;
            foreach ($recipe->ingredients->where('ingredient_group_id', $group->id) as $i) {
                $lines[] = $line($i);
            }
        }

        return trim(implode("\n", $lines));
    }

    /** How the ingredient lines will be saved, shown under the textarea. */
    #[Computed]
    public function preview(): array
    {
        return IngredientParser::parseBlock($this->ingredientsText);
    }

    #[Computed]
    public function allCategories()
    {
        return Category::orderBy('sort_order')->orderBy('name')->get();
    }

    public function addStep(): void
    {
        $this->steps[] = ['body' => '', 'timer' => null];
    }

    public function removeStep(int $index): void
    {
        unset($this->steps[$index]);
        $this->steps = array_values($this->steps) ?: [['body' => '', 'timer' => null]];
    }

    public function moveStep(int $index, int $direction): void
    {
        $target = $index + ($direction < 0 ? -1 : 1);
        if (! isset($this->steps[$index], $this->steps[$target])) {
            return;
        }
        [$this->steps[$index], $this->steps[$target]] = [$this->steps[$target], $this->steps[$index]];
    }

    public function addCategory(): void
    {
        $name = trim($this->newCategory);
        if ($name === '') {
            return;
        }
        $this->validateOnly('newCategory', ['newCategory' => 'string|max:60']);

        $category = Category::whereRaw('lower(name) = ?', [mb_strtolower($name)])->first()
            ?? Category::create([
                'name' => $name,
                'color' => Category::COLORS[Category::count() % count(Category::COLORS)],
                'sort_order' => (int) Category::max('sort_order') + 1,
            ]);

        $this->categoryIds = array_values(array_unique([...array_map('intval', $this->categoryIds), $category->id]));
        $this->newCategory = '';
        unset($this->allCategories);
    }

    protected function rules(): array
    {
        return [
            'title' => 'required|string|max:255',
            'description' => 'nullable|string|max:5000',
            'servings' => 'nullable|numeric|min:0.25|max:1000',
            'yield_label' => 'nullable|string|max:100',
            'prep_minutes' => 'nullable|integer|min:0|max:10000',
            'cook_minutes' => 'nullable|integer|min:0|max:10000',
            'source_name' => 'nullable|string|max:255',
            'source_url' => 'nullable|url:http,https|max:2048',
            'notes' => 'nullable|string|max:10000',
            'visibility' => ['required', Rule::in(Recipe::VISIBILITIES)],
            'categoryIds' => 'array',
            'categoryIds.*' => 'integer|exists:categories,id',
            'ingredientsText' => 'nullable|string|max:20000',
            'steps' => 'array|max:100',
            'steps.*.body' => 'nullable|string|max:5000',
            'steps.*.timer' => 'nullable|integer|min:1|max:1440',
        ];
    }

    public function save()
    {
        $data = $this->validate();

        $prep = $data['prep_minutes'] !== null && $data['prep_minutes'] !== '' ? (int) $data['prep_minutes'] : null;
        $cook = $data['cook_minutes'] !== null && $data['cook_minutes'] !== '' ? (int) $data['cook_minutes'] : null;

        $fields = [
            'title' => trim($data['title']),
            'description' => $data['description'] ?: null,
            'servings' => $data['servings'] !== null && $data['servings'] !== '' ? (float) $data['servings'] : null,
            'yield_label' => $data['yield_label'] ?: null,
            'prep_minutes' => $prep,
            'cook_minutes' => $cook,
            'total_minutes' => $prep !== null || $cook !== null ? ($prep ?? 0) + ($cook ?? 0) : null,
            'source_name' => $data['source_name'] ?: null,
            'source_url' => $data['source_url'] ?: null,
            'notes' => $data['notes'] ?: null,
            'visibility' => $data['visibility'],
        ];

        $recipe = DB::transaction(function () use ($fields, $data) {
            if ($this->recipe) {
                $recipe = $this->recipe;
                $recipe->update($fields);
            } else {
                $recipe = new Recipe($fields);
                $recipe->user_id = auth()->id();
                $recipe->save();
            }

            $recipe->categories()->sync(array_map('intval', $data['categoryIds'] ?? []));

            // Ingredients and steps are replaced wholesale; the text is the source of truth.
            $recipe->ingredients()->delete();
            $recipe->ingredientGroups()->delete();
            $recipe->steps()->delete();

            $groupIds = [];
            foreach (IngredientParser::parseBlock($data['ingredientsText'] ?? '') as $position => $row) {
                $groupId = null;
                if ($row['group'] !== null) {
                    $groupIds[$row['group']] ??= $recipe->ingredientGroups()->create([
                        'name' => $row['group'],
                        'position' => count($groupIds),
                    ])->id;
                    $groupId = $groupIds[$row['group']];
                }

                $recipe->ingredients()->create($row['parsed'] + [
                    'ingredient_group_id' => $groupId,
                    'position' => $position,
                ]);
            }

            $position = 0;
            foreach ($data['steps'] ?? [] as $step) {
                $body = trim((string) ($step['body'] ?? ''));
                if ($body === '') {
                    continue;
                }
                $recipe->steps()->create([
                    'body' => $body,
                    'timer_minutes' => ($step['timer'] ?? null) !== null && $step['timer'] !== '' ? (int) $step['timer'] : null,
                    'position' => $position++,
                ]);
            }

            return $recipe;
        });

        session()->flash('status', 'Recipe saved.');

        return $this->redirectRoute('recipes.show', $recipe, navigate: true);
    }

    public function render()
    {
        return view('livewire.recipes.form')->title($this->recipe ? 'Edit '.$this->recipe->title : 'New recipe');
    }
}
