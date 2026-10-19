<?php

namespace Tests\Feature;

use App\Livewire\Recipes\Form;
use App\Livewire\Recipes\Index;
use App\Livewire\Recipes\Show;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class TagsAndCookModeTest extends TestCase
{
    use RefreshDatabase;

    public function test_tags_are_parsed_deduplicated_and_reused(): void
    {
        $tags = Tag::fromText("Freezer-friendly, kid favorite,, FREEZER-FRIENDLY , #quick,  ");

        $this->assertSame(['Freezer-friendly', 'kid favorite', 'quick'], $tags->pluck('name')->all());
        $this->assertSame('kid-favorite', $tags[1]->slug);

        Tag::fromText('freezer friendly, quick');
        $this->assertSame(3, Tag::count(), 'a different spelling of the same slug reuses the tag');
    }

    public function test_tags_ignore_empty_and_overlong_names(): void
    {
        $this->assertCount(0, Tag::fromText(''));
        $this->assertCount(0, Tag::fromText(str_repeat('x', 41)));
        $this->assertCount(0, Tag::fromText('!!!'));
    }

    public function test_form_saves_edits_and_duplicates_tags(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        Livewire::test(Form::class)
            ->set('title', 'Chili')
            ->set('tagsText', 'freezer-friendly, spicy')
            ->call('save');

        $recipe = Recipe::firstWhere('title', 'Chili');
        $this->assertSame(['freezer-friendly', 'spicy'], $recipe->tags->pluck('name')->all());

        Livewire::test(Form::class, ['recipe' => $recipe])
            ->assertSet('tagsText', 'freezer-friendly, spicy')
            ->set('tagsText', 'spicy')
            ->call('save');
        $this->assertSame(['spicy'], $recipe->fresh()->tags->pluck('name')->all());

        Livewire::test(Show::class, ['recipe' => $recipe->fresh()])->call('duplicate');
        $copy = Recipe::firstWhere('title', 'Copy of Chili');
        $this->assertSame(['spicy'], $copy->tags->pluck('name')->all());
    }

    public function test_index_filters_by_tag(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);
        $a = Recipe::factory()->for($user)->create(['title' => 'Chili']);
        $a->tags()->sync(Tag::fromText('spicy')->pluck('id'));
        Recipe::factory()->for($user)->create(['title' => 'Cake']);

        Livewire::test(Index::class)
            ->assertSee('#spicy')
            ->call('toggleTag', 'spicy')
            ->assertSee('Chili')->assertDontSee('Cake')
            ->call('toggleTag', 'spicy')
            ->assertSee('Cake');
    }

    public function test_browse_filters_by_tag(): void
    {
        $a = Recipe::factory()->public()->create(['title' => 'Chili']);
        $a->tags()->sync(Tag::fromText('spicy')->pluck('id'));
        Recipe::factory()->public()->create(['title' => 'Cake']);

        $this->get(route('browse', ['tag' => 'spicy']))->assertSee('Chili')->assertDontSee('Cake');
    }

    private function recipeWithSteps(string $visibility = 'unlisted'): Recipe
    {
        $recipe = Recipe::factory()->create(['visibility' => $visibility, 'servings' => 4, 'title' => 'Stew']);
        $recipe->ingredients()->create(['name' => 'flour', 'quantity' => 1, 'unit' => 'cup', 'position' => 0]);
        $recipe->steps()->create(['body' => 'Brown the beef.', 'position' => 0, 'timer_minutes' => 7]);
        $recipe->steps()->create(['body' => 'Simmer slowly.', 'position' => 1]);

        return $recipe;
    }

    public function test_owner_cook_mode_shows_steps_ingredients_and_the_chosen_scale(): void
    {
        $recipe = $this->recipeWithSteps();
        $this->actingAs($recipe->user);

        $this->get(route('recipes.cook', ['recipe' => $recipe, 'scale' => '2', 'units' => 'metric']))
            ->assertOk()
            ->assertSee('Brown the beef.')
            ->assertSee('Simmer slowly.')
            ->assertSee('Scaled 2×')
            ->assertSeeInOrder(['470', 'ml', 'flour'])
            ->assertSee(chr(92).'u0022timer'.chr(92).'u0022:7', false)
            ->assertSee(route('recipes.show', $recipe).'?scale=2&amp;units=metric', false);
    }

    public function test_bad_scale_and_units_fall_back_safely(): void
    {
        $recipe = $this->recipeWithSteps();
        $this->actingAs($recipe->user);

        $this->get(route('recipes.cook', ['recipe' => $recipe, 'scale' => '<script>', 'units' => 'bogus']))
            ->assertOk()
            ->assertDontSee('Scaled')
            ->assertDontSee('<script>alert', false);
    }

    public function test_cook_mode_for_the_owner_requires_login_and_ownership(): void
    {
        $recipe = $this->recipeWithSteps();

        $this->get(route('recipes.cook', $recipe))->assertRedirect(route('login'));

        $this->actingAs(User::factory()->create())
            ->get(route('recipes.cook', $recipe))
            ->assertForbidden();
    }

    public function test_shared_cook_mode_follows_visibility_rules(): void
    {
        $unlisted = $this->recipeWithSteps('unlisted');
        $this->get(route('shared.cook', $unlisted->share_token))->assertOk()->assertSee('Brown the beef.')->assertSee('noindex', false);

        $public = $this->recipeWithSteps('public');
        $this->get(route('shared.cook', $public->share_token))->assertOk()->assertDontSee('noindex', false);

        $private = $this->recipeWithSteps('private');
        $this->get(route('shared.cook', $private->share_token))->assertNotFound();
    }
}
