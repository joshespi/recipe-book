<?php

namespace App\Livewire\Recipes;

use App\Models\Category;
use App\Models\Photo;
use App\Models\Recipe;
use App\Models\Tag;
use App\Support\ImportException;
use App\Support\IngredientParser;
use App\Support\RecipeImporter;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Validation\Rule;
use Livewire\Attributes\Computed;
use Livewire\Attributes\Layout;
use Livewire\Component;
use Livewire\WithFileUploads;

#[Layout('components.layouts.app')]
class Form extends Component
{
    use WithFileUploads;

    public ?Recipe $recipe = null;

    /** @var list<\Livewire\Features\SupportFileUploads\TemporaryUploadedFile> Photos picked while creating; attached once the recipe exists. */
    public array $photos = [];

    public array $incoming = [];

    public function updatedIncoming(): void
    {
        $this->validate(Photo::uploadRules('incoming', 10), Photo::uploadMessages('incoming'));

        $room = 10 - count($this->photos);
        if (count($this->incoming) > $room) {
            $this->addError('incoming', "You can add up to 10 photos; {$room} more fit.");
            $this->reset('incoming');

            return;
        }

        $this->photos = [...$this->photos, ...$this->incoming];
        $this->reset('incoming');
    }

    public function removePhoto(int $index): void
    {
        unset($this->photos[$index]);
        $this->photos = array_values($this->photos);
    }

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

    /** Free-form tags, comma separated: "freezer-friendly, kid favorite". */
    public string $tagsText = '';

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
        $recipe->load(['categories', 'tags', 'ingredientGroups', 'ingredients', 'steps']);
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
        $this->tagsText = $recipe->tags->pluck('name')->join(', ');
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
    public function allTags()
    {
        return Tag::orderBy('name')->pluck('name');
    }

    #[Computed]
    public function allCategories()
    {
        return Category::ordered()->get();
    }

    public string $importUrl = '';

    public ?string $importNotice = null;

    /** Fills the form from a recipe web page. Nothing is saved until the owner presses Save. */
    public function import(): void
    {
        $this->importNotice = null;
        $this->validateOnly('importUrl', ['importUrl' => 'required|string|max:2048'], [], ['importUrl' => 'web address']);

        $key = 'recipe-import:'.auth()->id();
        if (RateLimiter::tooManyAttempts($key, 10)) {
            $this->addError('importUrl', 'Please wait a minute before importing again.');

            return;
        }
        RateLimiter::hit($key, 60);

        try {
            $data = RecipeImporter::import($this->importUrl);
        } catch (ImportException $e) {
            $this->addError('importUrl', $e->getMessage());

            return;
        }

        $this->title = $data['title'] !== '' ? $data['title'] : $this->title;
        $this->description = $data['description'];
        $this->servings = $data['servings'];
        $this->yield_label = $data['yield_label'];
        $this->prep_minutes = $data['prep_minutes'] !== null ? (string) $data['prep_minutes'] : null;
        $this->cook_minutes = $data['cook_minutes'] !== null ? (string) $data['cook_minutes'] : null;
        $this->ingredientsText = implode("\n", $data['ingredients']);
        $this->steps = array_map(fn ($s) => ['body' => $s, 'timer' => null], $data['steps']) ?: [['body' => '', 'timer' => null]];
        $this->source_name = $data['source_name'];
        $this->source_url = $data['source_url'];

        $this->importNotice = 'Imported. Check the details below, then press Save.';
        $this->importUrl = '';
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
            ?? Category::createLast($name, Category::COLORS[Category::count() % count(Category::COLORS)]);

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
            'tagsText' => 'nullable|string|max:1000',
            'ingredientsText' => 'nullable|string|max:20000',
            'steps' => 'array|max:100',
            'steps.*.body' => 'nullable|string|max:5000',
            'steps.*.timer' => 'nullable|integer|min:1|max:1440',
        ];
    }

    public function save()
    {
        $this->recipe ? $this->authorize('update', $this->recipe) : $this->authorize('create', Recipe::class);
        $data = $this->validate();

        $prep = filled($data['prep_minutes']) ? (int) $data['prep_minutes'] : null;
        $cook = filled($data['cook_minutes']) ? (int) $data['cook_minutes'] : null;

        $fields = [
            'title' => trim($data['title']),
            'description' => $data['description'] ?: null,
            'servings' => filled($data['servings']) ? (float) $data['servings'] : null,
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
            $recipe->tags()->sync(Tag::fromText($data['tagsText'] ?? '')->pluck('id')->all());

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
                    'timer_minutes' => filled($step['timer'] ?? null) ? (int) $step['timer'] : null,
                    'position' => $position++,
                ]);
            }

            return $recipe;
        });

        $photoFailed = false;
        if (! $this->recipe) {
            foreach ($this->photos as $file) {
                try {
                    $recipe->addPhoto($file);
                } catch (\Throwable $e) {
                    report($e);
                    $photoFailed = true;
                }
            }
        }

        session()->flash('status', $photoFailed ? 'Recipe saved, but some photos could not be attached.' : 'Recipe saved.');

        return $this->redirectRoute('recipes.show', $recipe, navigate: true);
    }

    public function render()
    {
        return view('livewire.recipes.form')->title($this->recipe ? 'Edit '.$this->recipe->title : 'New recipe');
    }
}
