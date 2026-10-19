<?php

namespace Tests\Feature;

use App\Models\Category;
use App\Models\Recipe;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SharingVisibilityTest extends TestCase
{
    use RefreshDatabase;

    public function test_every_recipe_gets_a_share_token_and_slug_automatically(): void
    {
        $recipe = Recipe::factory()->create(['title' => 'Grandma Pie']);

        $this->assertSame(32, strlen($recipe->share_token));
        $this->assertSame('grandma-pie', $recipe->slug);
        $this->assertSame(route('shared.show', $recipe->share_token), $recipe->shareUrl());
    }

    public function test_slugs_stay_unique(): void
    {
        $a = Recipe::factory()->create(['title' => 'Soup']);
        $b = Recipe::factory()->create(['title' => 'Soup']);

        $this->assertSame('soup', $a->slug);
        $this->assertSame('soup-2', $b->slug);
    }

    public function test_private_recipe_share_link_is_a_404(): void
    {
        $recipe = Recipe::factory()->private()->create();

        $this->get(route('shared.show', $recipe->share_token))->assertNotFound();
    }

    public function test_unlisted_recipe_opens_by_link_but_is_not_browsable(): void
    {
        $recipe = Recipe::factory()->unlisted()->create(['title' => 'Secret Stew']);

        $this->get(route('shared.show', $recipe->share_token))
            ->assertOk()
            ->assertSee('Secret Stew')
            ->assertSee('noindex', false);

        $this->get(route('browse'))->assertOk()->assertDontSee('Secret Stew');
    }

    public function test_public_recipe_is_listed_and_has_structured_data(): void
    {
        $recipe = Recipe::factory()->public()->create(['title' => 'Open Omelette']);
        $recipe->ingredients()->create(['name' => 'eggs', 'quantity' => 3, 'original_text' => '3 eggs', 'position' => 0]);
        $recipe->steps()->create(['body' => 'Whisk and fry.', 'position' => 0]);

        $this->get(route('browse'))->assertOk()->assertSee('Open Omelette');

        $this->get(route('shared.show', $recipe->share_token))
            ->assertOk()
            ->assertSee('application/ld+json', false)
            ->assertSee('"@type":"Recipe"', false)
            ->assertSee('3 eggs')
            ->assertDontSee('noindex', false);
    }

    public function test_share_page_has_open_graph_tags(): void
    {
        $recipe = Recipe::factory()->unlisted()->create(['title' => 'Pancakes', 'description' => 'Fluffy.']);

        $this->get(route('shared.show', $recipe->share_token))
            ->assertSee('property="og:title" content="Pancakes"', false)
            ->assertSee('property="og:description" content="Fluffy."', false)
            ->assertSee('og:url', false);
    }

    public function test_regenerating_the_token_kills_the_old_link(): void
    {
        $recipe = Recipe::factory()->unlisted()->create();
        $old = $recipe->share_token;

        $recipe->regenerateShareToken();

        $this->get(route('shared.show', $old))->assertNotFound();
        $this->get(route('shared.show', $recipe->fresh()->share_token))->assertOk();
    }

    public function test_deleted_recipe_link_is_a_404(): void
    {
        $recipe = Recipe::factory()->public()->create();
        $recipe->delete();

        $this->get(route('shared.show', $recipe->share_token))->assertNotFound();
    }

    public function test_malformed_tokens_are_a_404(): void
    {
        $this->get('/s/short')->assertNotFound();
        $this->get('/s/'.str_repeat('!', 32))->assertNotFound();
    }

    public function test_browse_filters_by_category_and_search(): void
    {
        $soup = Category::create(['name' => 'Soups', 'color' => 'amber']);
        $a = Recipe::factory()->public()->create(['title' => 'Tomato soup']);
        $a->categories()->attach($soup);
        Recipe::factory()->public()->create(['title' => 'Apple cake']);

        $this->get(route('browse', ['category' => 'soups']))->assertSee('Tomato soup')->assertDontSee('Apple cake');
        $this->get(route('browse', ['q' => 'apple']))->assertSee('Apple cake')->assertDontSee('Tomato soup');
    }

    public function test_guests_cannot_reach_owner_pages(): void
    {
        $recipe = Recipe::factory()->public()->create();

        $this->get(route('recipes.index'))->assertRedirect(route('login'));
        $this->get(route('recipes.create'))->assertRedirect(route('login'));
        $this->get(route('recipes.show', $recipe))->assertRedirect(route('login'));
        $this->get(route('categories.index'))->assertRedirect(route('login'));
    }

    public function test_registration_is_disabled(): void
    {
        $this->get('/register')->assertNotFound();
        $this->post('/register', ['name' => 'x', 'email' => 'x@example.test', 'password' => 'password', 'password_confirmation' => 'password'])->assertStatus(404);
    }
}
