<?php

namespace Tests\Feature;

use App\Livewire\Categories\Manage;
use App\Livewire\Recipes\Form;
use App\Livewire\Recipes\Index;
use App\Livewire\Recipes\Scaler;
use App\Livewire\Recipes\Show;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class RecipeCrudTest extends TestCase
{
    use RefreshDatabase;

    private User $owner;

    protected function setUp(): void
    {
        parent::setUp();
        $this->owner = User::factory()->create();
        $this->actingAs($this->owner);
    }

    public function test_owner_can_create_a_recipe_with_parsed_ingredients_groups_and_steps(): void
    {
        $category = Category::create(['name' => 'Dinner', 'color' => 'amber']);

        Livewire::test(Form::class)
            ->set('title', 'Pasta Night')
            ->set('servings', '4')
            ->set('prep_minutes', '10')
            ->set('cook_minutes', '20')
            ->set('categoryIds', [$category->id])
            ->set('ingredientsText', "1 1/2 cups flour, sifted\n3 eggs\n\n# For the sauce\n2 T butter\nsalt to taste")
            ->set('steps', [['body' => 'Mix it.', 'timer' => '5'], ['body' => '', 'timer' => null], ['body' => 'Cook it.', 'timer' => null]])
            ->call('save')
            ->assertHasNoErrors()
            ->assertRedirect(route('recipes.show', Recipe::first()));

        $recipe = Recipe::with(['ingredients', 'ingredientGroups', 'steps', 'categories'])->first();

        $this->assertSame('Pasta Night', $recipe->title);
        $this->assertSame($this->owner->id, $recipe->user_id);
        $this->assertSame(30, $recipe->total_minutes);
        $this->assertSame(Recipe::UNLISTED, $recipe->visibility);
        $this->assertSame(32, strlen($recipe->share_token));
        $this->assertCount(1, $recipe->categories);

        $this->assertSame(['flour', 'eggs', 'butter', 'salt'], $recipe->ingredients->pluck('name')->all());
        $flour = $recipe->ingredients->first();
        $this->assertEquals(1.5, $flour->quantity);
        $this->assertSame('cup', $flour->unit);
        $this->assertSame('sifted', $flour->note);

        $this->assertSame(['For the sauce'], $recipe->ingredientGroups->pluck('name')->all());
        $grouped = $recipe->ingredients->where('ingredient_group_id', $recipe->ingredientGroups->first()->id);
        $this->assertSame(['butter', 'salt'], $grouped->pluck('name')->values()->all());
        $this->assertNull($recipe->ingredients->firstWhere('name', 'salt')->quantity);

        $this->assertSame(['Mix it.', 'Cook it.'], $recipe->steps->pluck('body')->all());
        $this->assertSame(5, $recipe->steps->first()->timer_minutes);
    }

    public function test_title_is_required_and_source_url_must_be_http(): void
    {
        Livewire::test(Form::class)
            ->set('title', '')
            ->set('source_url', 'javascript:alert(1)')
            ->call('save')
            ->assertHasErrors(['title', 'source_url']);

        $this->assertSame(0, Recipe::count());
    }

    public function test_editing_round_trips_and_replaces_ingredients(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->create(['title' => 'Old']);
        $recipe->ingredients()->create(['name' => 'flour', 'quantity' => 2, 'unit' => 'cup', 'original_text' => '2 cups flour', 'position' => 0]);

        $component = Livewire::test(Form::class, ['recipe' => $recipe])
            ->assertSet('title', 'Old')
            ->assertSet('ingredientsText', '2 cups flour');

        $component->set('title', 'New')
            ->set('ingredientsText', '4 tbsp honey')
            ->call('save')
            ->assertHasNoErrors();

        $recipe->refresh();
        $this->assertSame('New', $recipe->title);
        $this->assertSame('old', $recipe->slug, 'slug is stable so bookmarks keep working');
        $this->assertSame(['honey'], $recipe->ingredients()->pluck('name')->all());
    }

    public function test_quick_adding_a_category_from_the_form(): void
    {
        Livewire::test(Form::class)
            ->set('newCategory', 'Desserts')
            ->call('addCategory')
            ->assertSet('newCategory', '');

        $category = Category::firstWhere('name', 'Desserts');
        $this->assertNotNull($category);
    }

    public function test_index_lists_only_my_recipes_and_filters(): void
    {
        $soups = Category::create(['name' => 'Soups', 'color' => 'amber']);
        $mine = Recipe::factory()->for($this->owner)->create(['title' => 'Tomato soup']);
        $mine->categories()->attach($soups);
        $mine->ingredients()->create(['name' => 'basil', 'position' => 0]);
        Recipe::factory()->for($this->owner)->create(['title' => 'Apple cake', 'is_favorite' => true]);
        Recipe::factory()->create(['title' => 'Someone elses']);

        Livewire::test(Index::class)
            ->assertSee('Tomato soup')->assertSee('Apple cake')->assertDontSee('Someone elses')
            ->call('toggleCategory', $soups->id)
            ->assertSee('Tomato soup')->assertDontSee('Apple cake')
            ->call('clearFilters')
            ->set('favoritesOnly', true)
            ->assertSee('Apple cake')->assertDontSee('Tomato soup')
            ->call('clearFilters')
            ->set('search', 'basil')
            ->assertSee('Tomato soup')->assertDontSee('Apple cake');
    }

    public function test_index_sorts(): void
    {
        Recipe::factory()->for($this->owner)->create(['title' => 'Banana', 'rating' => 2]);
        Recipe::factory()->for($this->owner)->create(['title' => 'Apple', 'rating' => 5]);
        $cooked = Recipe::factory()->for($this->owner)->create(['title' => 'Cherry']);
        $cooked->cookLogs()->create(['cooked_on' => now()->toDateString()]);
        $cooked->cookLogs()->create(['cooked_on' => now()->toDateString()]);

        Livewire::test(Index::class)->set('sort', 'az')->assertSeeInOrder(['Apple', 'Banana', 'Cherry']);
        Livewire::test(Index::class)->set('sort', 'rating')->assertSeeInOrder(['Apple', 'Banana', 'Cherry']);
        Livewire::test(Index::class)->set('sort', 'cooked')->assertSeeInOrder(['Cherry']);
    }

    public function test_owner_cannot_open_or_edit_someone_elses_recipe(): void
    {
        $other = Recipe::factory()->create();

        $this->get(route('recipes.show', $other))->assertForbidden();
        $this->get(route('recipes.edit', $other))->assertForbidden();
    }

    public function test_show_page_toggles_and_sharing_actions(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->create();
        $token = $recipe->share_token;

        Livewire::test(Show::class, ['recipe' => $recipe])
            ->call('toggleFavorite')
            ->call('setRating', 4)
            ->call('setVisibility', 'public')
            ->call('regenerateLink');

        $recipe->refresh();
        $this->assertTrue($recipe->is_favorite);
        $this->assertSame(4, $recipe->rating);
        $this->assertSame('public', $recipe->visibility);
        $this->assertNotSame($token, $recipe->share_token);

        Livewire::test(Show::class, ['recipe' => $recipe])->call('setRating', 4);
        $this->assertNull($recipe->fresh()->rating, 'clicking the same star clears it');
    }

    public function test_invalid_visibility_is_rejected(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->create();

        Livewire::test(Show::class, ['recipe' => $recipe])->call('setVisibility', 'everyone')->assertStatus(422);
        $this->assertSame('unlisted', $recipe->fresh()->visibility);
    }

    public function test_duplicate_copies_content_but_starts_private(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->public()->create(['title' => 'Original', 'rating' => 5]);
        $group = $recipe->ingredientGroups()->create(['name' => 'Sauce', 'position' => 0]);
        $recipe->ingredients()->create(['name' => 'tomato', 'quantity' => 2, 'ingredient_group_id' => $group->id, 'position' => 0]);
        $recipe->steps()->create(['body' => 'Cook.', 'position' => 0]);

        Livewire::test(Show::class, ['recipe' => $recipe])->call('duplicate');

        $copy = Recipe::where('title', 'Copy of Original')->firstOrFail();
        $this->assertSame('private', $copy->visibility);
        $this->assertNotSame($recipe->share_token, $copy->share_token);
        $this->assertNull($copy->rating);
        $this->assertSame(['tomato'], $copy->ingredients->pluck('name')->all());
        $this->assertSame('Sauce', $copy->ingredientGroups->first()->name);
        $this->assertSame($copy->ingredientGroups->first()->id, $copy->ingredients->first()->ingredient_group_id);
        $this->assertSame(['Cook.'], $copy->steps->pluck('body')->all());
    }

    public function test_delete_soft_deletes(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->create();

        Livewire::test(Show::class, ['recipe' => $recipe])->call('delete')->assertRedirect(route('recipes.index'));

        $this->assertSoftDeleted($recipe);
    }

    public function test_categories_can_be_managed(): void
    {
        $component = Livewire::test(Manage::class)
            ->set('name', 'Breakfast')->set('color', 'sky')->call('add')
            ->set('name', 'Lunch')->call('add');

        $breakfast = Category::firstWhere('name', 'Breakfast');
        $lunch = Category::firstWhere('name', 'Lunch');
        $this->assertSame('breakfast', $breakfast->slug);

        $component->call('move', $lunch->id, -1);
        $this->assertSame(['Lunch', 'Breakfast'], Category::orderBy('sort_order')->pluck('name')->all());

        $component->call('edit', $breakfast->id)->set('editName', 'Brunch')->call('update');
        $this->assertSame('Brunch', $breakfast->fresh()->name);

        $recipe = Recipe::factory()->for($this->owner)->create();
        $recipe->categories()->attach($breakfast);
        $component->call('delete', $breakfast->id);
        $this->assertNull(Category::find($breakfast->id));
        $this->assertNotNull($recipe->fresh(), 'recipes survive category deletion');
    }

    public function test_scaler_component_scales_and_converts(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->create(['servings' => 4]);
        $recipe->ingredients()->create(['name' => 'sugar', 'quantity' => 1, 'unit' => 'cup', 'position' => 0]);
        $recipe->ingredients()->create(['name' => 'eggs', 'quantity' => 3, 'position' => 1]);
        $recipe->ingredients()->create(['name' => 'salt', 'position' => 2, 'note' => 'to taste']);

        Livewire::test(Scaler::class, ['recipe' => $recipe])
            ->assertSee('sugar')
            ->call('setScale', '0.5')
            ->assertSet('scale', '0.5')
            ->assertSee('½')
            ->assertSee('1½')
            ->assertSee('Scaled ½×')
            ->call('setScale', '2')
            ->assertSeeInOrder(['2', 'cups', 'sugar'])
            ->call('setUnits', 'metric')
            ->assertSet('units', 'metric')
            ->assertSeeInOrder(['470', 'ml', 'sugar'])
            ->call('stepServings', 1)
            ->assertSet('scale', '2.25')
            ->call('setScale', 'nonsense')
            ->assertSet('scale', '1');
    }

    public function test_scaler_uses_query_string_state_and_rejects_bad_units(): void
    {
        $recipe = Recipe::factory()->for($this->owner)->create(['servings' => 2]);
        $recipe->ingredients()->create(['name' => 'flour', 'quantity' => 1, 'unit' => 'cup', 'position' => 0]);

        Livewire::withQueryParams(['scale' => '3', 'units' => 'metric'])
            ->test(Scaler::class, ['recipe' => $recipe])
            ->assertSet('scale', '3')
            ->assertSet('units', 'metric')
            ->assertSeeInOrder(['710', 'ml', 'flour']);

        Livewire::withQueryParams(['units' => 'bogus'])
            ->test(Scaler::class, ['recipe' => $recipe])
            ->assertSet('units', 'original');
    }
}
