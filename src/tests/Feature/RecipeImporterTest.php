<?php

namespace Tests\Feature;

use App\Livewire\Recipes\Form;
use App\Models\User;
use App\Support\ImportException;
use App\Support\RecipeImporter;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Livewire\Livewire;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class RecipeImporterTest extends TestCase
{
    use RefreshDatabase;

    /** Pretend every hostname resolves to the given map, defaulting to a public address. */
    private function dns(array $map = []): void
    {
        RecipeImporter::$resolver = fn (string $host) => [$map[$host] ?? '93.184.216.34'];
    }

    protected function tearDown(): void
    {
        RecipeImporter::$resolver = null;
        parent::tearDown();
    }

    private function page(array $recipe, string $wrapper = '%s'): string
    {
        $json = json_encode($recipe, JSON_UNESCAPED_SLASHES);

        return '<html><head><script type="application/ld+json">'.sprintf($wrapper, $json).'</script></head><body>x</body></html>';
    }

    private function sample(): array
    {
        return [
            '@context' => 'https://schema.org',
            '@type' => 'Recipe',
            'name' => 'Best &amp; Easiest Pancakes',
            'description' => '<p>Fluffy.</p>',
            'recipeYield' => ['8', '8 pancakes'],
            'prepTime' => 'PT10M',
            'cookTime' => 'PT1H15M',
            'recipeIngredient' => ['1 1/2 cups flour', '2 eggs', ' 1 tsp salt '],
            'recipeInstructions' => [
                ['@type' => 'HowToSection', 'name' => 'Batter', 'itemListElement' => [
                    ['@type' => 'HowToStep', 'text' => 'Mix dry.'],
                    ['@type' => 'HowToStep', 'text' => 'Add wet.'],
                ]],
                ['@type' => 'HowToStep', 'text' => 'Fry.'],
            ],
        ];
    }

    public function test_imports_a_recipe_from_json_ld(): void
    {
        $this->dns();
        Http::fake(['https://www.example.test/*' => Http::response($this->page($this->sample()))]);

        $r = RecipeImporter::import('https://www.example.test/pancakes');

        $this->assertSame('Best & Easiest Pancakes', $r['title']);
        $this->assertSame('Fluffy.', $r['description']);
        $this->assertSame('8', $r['servings']);
        $this->assertSame(10, $r['prep_minutes']);
        $this->assertSame(75, $r['cook_minutes']);
        $this->assertSame(['1 1/2 cups flour', '2 eggs', '1 tsp salt'], $r['ingredients']);
        $this->assertSame(['Mix dry.', 'Add wet.', 'Fry.'], $r['steps']);
        $this->assertSame('example.test', $r['source_name']);
        $this->assertSame('https://www.example.test/pancakes', $r['source_url']);
    }

    public function test_finds_recipe_inside_a_graph_and_reads_instruction_strings(): void
    {
        $this->dns();
        $recipe = ['@type' => ['Recipe', 'Thing'], 'name' => 'Soup', 'recipeInstructions' => "Chop.\nBoil.", 'recipeYield' => '4 servings'];
        $html = $this->page(['@context' => 'https://schema.org', '@graph' => [['@type' => 'WebSite'], $recipe]]);
        Http::fake(['*' => Http::response($html)]);

        $r = RecipeImporter::import('https://example.test/soup');

        $this->assertSame('Soup', $r['title']);
        $this->assertSame(['Chop.', 'Boil.'], $r['steps']);
        $this->assertSame('4', $r['servings']);
        $this->assertSame('', $r['yield_label'], 'a bare "servings" is not a label');
    }

    public function test_pages_without_a_recipe_give_a_friendly_error(): void
    {
        $this->dns();
        Http::fake(['*' => Http::response('<html><body>No data here</body></html>')]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('does not publish its recipe');
        RecipeImporter::import('https://example.test/none');
    }

    public function test_http_errors_are_reported(): void
    {
        $this->dns();
        Http::fake(['*' => Http::response('nope', 404)]);

        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('404');
        RecipeImporter::import('https://example.test/missing');
    }

    #[DataProvider('blockedUrls')]
    public function test_internal_and_odd_addresses_are_refused(string $url, array $dns = []): void
    {
        $this->dns($dns);
        Http::fake();

        try {
            RecipeImporter::import($url);
            $this->fail('Expected the import to be refused: '.$url);
        } catch (ImportException) {
            Http::assertNothingSent();
            $this->assertTrue(true);
        }
    }

    public static function blockedUrls(): array
    {
        return [
            'loopback ip' => ['http://127.0.0.1/admin'],
            'private 10' => ['http://10.0.0.5/'],
            'private 192' => ['https://192.168.1.1/'],
            'private 172' => ['http://172.16.0.1/'],
            'cloud metadata' => ['http://169.254.169.254/latest/meta-data/'],
            'ipv6 loopback' => ['http://[::1]/'],
            'ipv6 ula' => ['http://[fd00::1]/'],
            'carrier nat' => ['http://100.64.0.1/'],
            'unspecified' => ['http://0.0.0.0/'],
            'localhost by dns' => ['http://localhost/', ['localhost' => '127.0.0.1']],
            'name pointing at private' => ['https://evil.test/', ['evil.test' => '10.1.2.3']],
            'ipv4-mapped private' => ['https://evil.test/', ['evil.test' => '::ffff:127.0.0.1']],
            'file scheme' => ['file:///etc/passwd'],
            'ftp scheme' => ['ftp://example.test/x'],
            'javascript' => ['javascript:alert(1)'],
            'odd port' => ['http://example.test:8080/'],
            'credentials' => ['http://user:pass@example.test/'],
            'no host' => ['https:///x'],
            'not a url' => ['pancakes'],
        ];
    }

    public function test_a_name_with_one_private_address_among_public_ones_is_refused(): void
    {
        RecipeImporter::$resolver = fn () => ['93.184.216.34', '10.0.0.9'];

        $this->expectException(ImportException::class);
        RecipeImporter::validate('https://rebind.test/');
    }

    public function test_public_addresses_pass_validation_and_are_pinned(): void
    {
        $this->dns(['example.test' => '93.184.216.34']);

        $this->assertSame(['example.test', '93.184.216.34', 443], RecipeImporter::validate('https://example.test/a'));
        $this->assertSame(['93.184.216.34', '93.184.216.34', 80], RecipeImporter::validate('http://93.184.216.34/a'));
    }

    public function test_redirects_to_private_addresses_are_refused(): void
    {
        $this->dns(['sneaky.test' => '10.0.0.1']);
        Http::fake([
            'https://example.test/*' => Http::response('', 302, ['Location' => 'https://sneaky.test/inside']),
            'https://sneaky.test/*' => Http::response($this->page($this->sample())),
        ]);

        try {
            RecipeImporter::import('https://example.test/go');
            $this->fail('redirect to a private host was followed');
        } catch (ImportException) {
            Http::assertSentCount(1);
            $this->assertTrue(true);
        }
    }

    public function test_safe_redirects_are_followed_but_not_forever(): void
    {
        $this->dns();
        Http::fake([
            'https://example.test/old' => Http::response('', 301, ['Location' => '/new']),
            'https://example.test/new' => Http::response($this->page($this->sample())),
        ]);
        $this->assertSame('Best & Easiest Pancakes', RecipeImporter::import('https://example.test/old')['title']);

        Http::fake(['https://loop.test/*' => Http::response('', 302, ['Location' => 'https://loop.test/again'])]);
        $this->expectException(ImportException::class);
        $this->expectExceptionMessage('too many times');
        RecipeImporter::import('https://loop.test/start');
    }

    public function test_iso_durations(): void
    {
        $this->assertSame(90, RecipeImporter::minutes('PT1H30M'));
        $this->assertSame(45, RecipeImporter::minutes('PT45M'));
        $this->assertSame(120, RecipeImporter::minutes('PT2H'));
        $this->assertSame(1440, RecipeImporter::minutes('P1D'));
        $this->assertNull(RecipeImporter::minutes('PT0M'));
        $this->assertNull(RecipeImporter::minutes('soon'));
        $this->assertNull(RecipeImporter::minutes(null));
    }

    public function test_the_form_is_filled_but_nothing_is_saved(): void
    {
        $this->dns();
        Http::fake(['*' => Http::response($this->page($this->sample()))]);
        $this->actingAs(User::factory()->create());

        Livewire::test(Form::class)
            ->set('importUrl', 'https://example.test/pancakes')
            ->call('import')
            ->assertHasNoErrors()
            ->assertSet('title', 'Best & Easiest Pancakes')
            ->assertSet('servings', '8')
            ->assertSet('prep_minutes', '10')
            ->assertSet('ingredientsText', "1 1/2 cups flour\n2 eggs\n1 tsp salt")
            ->assertSet('steps.2.body', 'Fry.')
            ->assertSet('source_url', 'https://example.test/pancakes')
            ->assertSet('importUrl', '');

        $this->assertDatabaseCount('recipes', 0);
    }

    public function test_the_form_shows_the_reason_when_an_import_is_refused(): void
    {
        $this->actingAs(User::factory()->create());

        Livewire::test(Form::class)
            ->set('importUrl', 'http://169.254.169.254/latest/meta-data/')
            ->call('import')
            ->assertHasErrors('importUrl');

        Livewire::test(Form::class)->set('importUrl', '')->call('import')->assertHasErrors('importUrl');
    }

    public function test_imports_are_rate_limited(): void
    {
        $this->actingAs(User::factory()->create());
        $component = Livewire::test(Form::class);

        foreach (range(1, 10) as $i) {
            $component->set('importUrl', 'http://127.0.0.1/')->call('import');
        }
        $component->set('importUrl', 'http://127.0.0.1/')->call('import')->assertHasErrors('importUrl');
        $this->assertStringContainsString('wait', $component->errors()->first('importUrl'));
    }
}
