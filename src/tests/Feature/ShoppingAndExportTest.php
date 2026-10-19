<?php

namespace Tests\Feature;

use App\Livewire\ShoppingList;
use App\Models\Category;
use App\Models\Recipe;
use App\Models\Tag;
use App\Models\User;
use App\Support\ShoppingListBuilder;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Livewire\Livewire;
use Tests\TestCase;

class ShoppingAndExportTest extends TestCase
{
    use RefreshDatabase;

    private function line(?float $qty, ?string $unit, string $name, ?float $max = null): array
    {
        return (object) ['quantity' => $qty, 'quantity_max' => $max, 'unit' => $unit, 'name' => $name, 'note' => null];
    }

    /** @return list<string> */
    private function build(array $rows, string $system = 'us'): array
    {
        $items = array_map(fn ($r) => ['ingredient' => $r[0], 'factor' => $r[1] ?? 1.0], $rows);

        return array_map(fn ($l) => $l->text(), ShoppingListBuilder::build($items, $system));
    }

    private function ing(?float $qty, ?string $unit, string $name, ?float $max = null): object
    {
        return (object) ['quantity' => $qty, 'quantity_max' => $max, 'unit' => $unit, 'name' => $name, 'note' => null];
    }

    public function test_volumes_of_the_same_ingredient_are_added_across_units(): void
    {
        $this->assertSame(['1½ cups flour'], $this->build([[$this->ing(1, 'cup', 'flour')], [$this->ing(8, 'tbsp', 'flour')]]));
        $this->assertSame(['350 ml flour'], $this->build([[$this->ing(1, 'cup', 'flour')], [$this->ing(8, 'tbsp', 'flour')]], 'metric'));
    }

    public function test_weights_are_added_and_volume_never_mixes_with_weight(): void
    {
        $this->assertSame(['400 g butter'], $this->build([[$this->ing(100, 'g', 'butter')], [$this->ing(300, 'g', 'butter')]], 'metric'));

        $lines = $this->build([[$this->ing(1, 'cup', 'butter')], [$this->ing(100, 'g', 'butter')]], 'metric');
        $this->assertCount(2, $lines);
        $this->assertContains('240 ml butter', $lines);
        $this->assertContains('100 g butter', $lines);
    }

    public function test_counts_are_added_and_singular_plural_names_match(): void
    {
        $this->assertSame(['4 eggs'], $this->build([[$this->ing(3, null, 'eggs')], [$this->ing(1, null, 'egg')]]));
        $this->assertSame(['4 eggs'], $this->build([[$this->ing(1, null, 'egg')], [$this->ing(3, null, 'eggs')]]));
        $this->assertSame(['1 egg'], $this->build([[$this->ing(1, null, 'eggs')]]));
        $this->assertSame(['5 cloves garlic'], $this->build([[$this->ing(2, 'cloves', 'garlic')], [$this->ing(3, 'clove', 'garlic')]]));
        $this->assertSame(['3 Tomatoes'], $this->build([[$this->ing(2, null, 'Tomatoes')], [$this->ing(1, null, 'tomato')]]));
    }

    public function test_scale_factor_is_applied_per_recipe(): void
    {
        $this->assertSame(['5 eggs'], $this->build([[$this->ing(2, null, 'eggs'), 1.0], [$this->ing(1.5, null, 'eggs'), 2.0]]));
    }

    public function test_ranges_use_the_top_of_the_range(): void
    {
        $this->assertSame(['3 cloves garlic'], $this->build([[$this->ing(2, 'clove', 'garlic', 3.0)]]));
    }

    public function test_amountless_lines_are_listed_once_and_dropped_when_an_amount_exists(): void
    {
        $this->assertSame(['pepper'], $this->build([[$this->ing(null, null, 'pepper')], [$this->ing(null, null, 'Pepper')]]));
        $this->assertSame(['1 tsp salt'], $this->build([[$this->ing(null, null, 'salt')], [$this->ing(1, 'tsp', 'salt')]]));
    }

    public function test_output_is_sorted_by_name(): void
    {
        $this->assertSame(['1 cup apple juice', '2 bananas', 'zucchini'], $this->build([
            [$this->ing(null, null, 'zucchini')], [$this->ing(2, null, 'bananas')], [$this->ing(1, 'cup', 'apple juice')],
        ]));
    }

    public function test_shopping_list_component_adds_up_selected_recipes(): void
    {
        $user = User::factory()->create();
        $this->actingAs($user);

        $a = Recipe::factory()->for($user)->create(['title' => 'Pancakes']);
        $a->ingredients()->create(['name' => 'eggs', 'quantity' => 2, 'position' => 0]);
        $a->ingredients()->create(['name' => 'flour', 'quantity' => 1, 'unit' => 'cup', 'position' => 1]);
        $b = Recipe::factory()->for($user)->create(['title' => 'Omelette']);
        $b->ingredients()->create(['name' => 'eggs', 'quantity' => 3, 'position' => 0]);
        $other = Recipe::factory()->create(['title' => 'Not mine']);
        $other->ingredients()->create(['name' => 'caviar', 'quantity' => 1, 'position' => 0]);

        Livewire::test(ShoppingList::class)
            ->assertSee('Pancakes')->assertSee('Omelette')->assertDontSee('Not mine')
            ->assertSee('Tick some recipes')
            ->set('selected', [(string) $a->id, (string) $b->id])
            ->assertSet('scales', [(string) $a->id => '1', (string) $b->id => '1'])
            ->assertSeeInOrder(['5', 'eggs'])
            ->assertSeeInOrder(['1', 'cup', 'flour'])
            ->set('scales', [(string) $a->id => '2'])
            ->assertSeeInOrder(['7', 'eggs'])
            ->set('units', 'metric')
            ->assertSeeInOrder(['470', 'ml', 'flour'])
            ->set('selected', [(string) $other->id])
            ->assertDontSee('caviar');
    }

    public function test_shopping_list_requires_login(): void
    {
        $this->get(route('shopping.index'))->assertRedirect(route('login'));
    }

    private function exportable(User $user): Recipe
    {
        $recipe = Recipe::factory()->for($user)->create(['title' => 'Chili', 'servings' => 6, 'notes' => 'Better next day']);
        $recipe->categories()->attach(Category::create(['name' => 'Dinner', 'color' => 'red']));
        $recipe->tags()->sync(Tag::fromText('spicy')->pluck('id'));
        $group = $recipe->ingredientGroups()->create(['name' => 'Base', 'position' => 0]);
        $recipe->ingredients()->create(['name' => 'beans', 'quantity' => 2, 'unit' => 'can', 'original_text' => '2 cans beans', 'ingredient_group_id' => $group->id, 'position' => 0]);
        $recipe->steps()->create(['body' => 'Simmer for an hour.', 'position' => 0, 'timer_minutes' => 60]);
        $recipe->cookLogs()->create(['cooked_on' => '2026-01-02', 'notes' => 'Too spicy', 'rating' => 3]);

        return $recipe;
    }

    public function test_json_export_contains_everything_and_only_my_recipes(): void
    {
        $user = User::factory()->create();
        $this->exportable($user);
        Recipe::factory()->create(['title' => 'Someone elses']);

        $response = $this->actingAs($user)->get(route('export.json'));
        $response->assertOk();
        $this->assertStringContainsString('attachment', $response->headers->get('content-disposition'));

        $data = json_decode($response->streamedContent(), true);
        $this->assertCount(1, $data['recipes']);
        $r = $data['recipes'][0];
        $this->assertSame('Chili', $r['title']);
        $this->assertSame(['Dinner'], $r['categories']);
        $this->assertSame(['spicy'], $r['tags']);
        $this->assertSame('Base', $r['ingredients'][0]['group']);
        $this->assertSame('2 cans beans', $r['ingredients'][0]['text']);
        $this->assertSame('Simmer for an hour.', $r['steps'][0]['text']);
        $this->assertSame(60, $r['steps'][0]['timer_minutes']);
        $this->assertSame('Too spicy', $r['times_made'][0]['notes']);
        $this->assertArrayNotHasKey('share_token', $r, 'share links are not exported');
    }

    public function test_markdown_export_is_readable(): void
    {
        $user = User::factory()->create();
        $this->exportable($user);

        $md = $this->actingAs($user)->get(route('export.markdown'))->streamedContent();

        $this->assertStringContainsString('# Chili', $md);
        $this->assertStringContainsString('## Ingredients', $md);
        $this->assertStringContainsString("**Base**\n\n- 2 cans beans", $md);
        $this->assertStringContainsString('1. Simmer for an hour.', $md);
        $this->assertStringContainsString('Better next day', $md);
        $this->assertStringContainsString('Serves: 6', $md);
    }

    public function test_exports_require_login(): void
    {
        $this->get(route('export.json'))->assertRedirect(route('login'));
        $this->get(route('export.markdown'))->assertRedirect(route('login'));
    }
}
