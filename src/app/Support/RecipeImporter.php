<?php

namespace App\Support;

use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use Illuminate\Support\Facades\Http;

/**
 * Reads a recipe from a web page using its schema.org "Recipe" data (JSON-LD),
 * which most recipe sites publish. The page is fetched by the server, so this
 * is hardened against being pointed at internal addresses (SSRF):
 *   - only http/https on ports 80/443
 *   - every hostname is resolved first and refused if ANY address is private, loopback,
 *     link-local or otherwise reserved; the connection is then pinned to the checked address
 *   - redirects are followed by hand (max 3) and each hop is checked again
 *   - short timeouts and a hard cap on the size of the page
 */
class RecipeImporter
{
    public const MAX_BYTES = 2_000_000;

    public const MAX_REDIRECTS = 3;

    /** Overridable in tests: fn (string $host): list<string> of IP addresses. */
    public static ?\Closure $resolver = null;

    /**
     * @return array{title: string, description: string, servings: ?string, yield_label: string, prep_minutes: ?int, cook_minutes: ?int, ingredients: list<string>, steps: list<string>, source_name: string, source_url: string, notes: string}
     *
     * @throws ImportException
     */
    public static function import(string $url): array
    {
        $html = self::fetch(trim($url));
        $data = self::extractRecipe($html);

        if ($data === null) {
            throw new ImportException('That page does not publish its recipe in a format we can read. You can still type it in by hand.');
        }

        return self::normalize($data, trim($url));
    }

    /** @throws ImportException */
    public static function fetch(string $url): string
    {
        for ($hop = 0; $hop <= self::MAX_REDIRECTS; $hop++) {
            [$host, $ip, $port] = self::validate($url);

            try {
                $response = Http::timeout(10)
                    ->connectTimeout(5)
                    ->withHeaders([
                        'User-Agent' => 'Mozilla/5.0 (compatible; RecipeBoxImporter/1.0)',
                        'Accept' => 'text/html,application/xhtml+xml',
                    ])
                    ->withOptions([
                        'allow_redirects' => false,
                        'verify' => true,
                        // Connect to the address we checked, not whatever DNS says next time.
                        'curl' => [CURLOPT_RESOLVE => ["{$host}:{$port}:{$ip}"]],
                        'on_headers' => function ($response) {
                            if ((int) $response->getHeaderLine('Content-Length') > self::MAX_BYTES) {
                                throw new \RuntimeException('too large');
                            }
                        },
                        // Stops a server that streams forever (no Content-Length) from filling memory.
                        'progress' => function ($downloadTotal, $downloaded) {
                            if ($downloaded > self::MAX_BYTES) {
                                throw new \RuntimeException('too large');
                            }
                        },
                    ])
                    ->get($url);
            } catch (\Throwable) {
                throw new ImportException('We could not reach that page.');
            }

            if ($response->redirect()) {
                $location = $response->header('Location');
                if (! $location) {
                    throw new ImportException('That page redirected somewhere we could not follow.');
                }
                $url = self::absoluteUrl($url, $location);

                continue;
            }

            if (! $response->successful()) {
                throw new ImportException('That page answered with an error ('.$response->status().').');
            }

            $body = $response->body();
            if (strlen($body) > self::MAX_BYTES) {
                throw new ImportException('That page is too large to import.');
            }

            return $body;
        }

        throw new ImportException('That link redirects too many times.');
    }

    /**
     * @return array{0: string, 1: string, 2: int} host, a safe IP for it, port
     *
     * @throws ImportException
     */
    public static function validate(string $url): array
    {
        $parts = parse_url($url);
        $scheme = strtolower($parts['scheme'] ?? '');
        $host = $parts['host'] ?? '';

        if (! in_array($scheme, ['http', 'https'], true) || $host === '' || isset($parts['user']) || isset($parts['pass'])) {
            throw new ImportException('Please enter a normal web address starting with http:// or https://.');
        }

        $port = $parts['port'] ?? ($scheme === 'https' ? 443 : 80);
        if (! in_array($port, [80, 443], true)) {
            throw new ImportException('Only standard web ports are allowed.');
        }

        $host = trim($host, '[]');
        $ips = filter_var($host, FILTER_VALIDATE_IP) ? [$host] : self::resolve($host);

        if ($ips === []) {
            throw new ImportException('We could not find that website.');
        }
        foreach ($ips as $ip) {
            if (! self::isPublicIp($ip)) {
                throw new ImportException('That address is not allowed.');
            }
        }

        return [$host, $ips[0], $port];
    }

    public static function isPublicIp(string $ip): bool
    {
        return filter_var($ip, FILTER_VALIDATE_IP, FILTER_FLAG_NO_PRIV_RANGE | FILTER_FLAG_NO_RES_RANGE) !== false
            && ! str_starts_with($ip, '100.64.')      // carrier-grade NAT is not covered by the PHP flags
            && ! preg_match('/^(fe80|fc|fd|::1|::ffff:(127|10|192\.168|169\.254))/i', $ip);
    }

    /** @return list<string> */
    private static function resolve(string $host): array
    {
        if (self::$resolver) {
            return (self::$resolver)($host);
        }

        $ips = [];
        foreach (@dns_get_record($host, DNS_A | DNS_AAAA) ?: [] as $record) {
            $ips[] = $record['ip'] ?? $record['ipv6'] ?? null;
        }

        return array_values(array_filter($ips));
    }

    /** Resolves a redirect Location against the current URL (RFC 3986). */
    private static function absoluteUrl(string $base, string $location): string
    {
        return (string) UriResolver::resolve(new Uri($base), new Uri($location));
    }

    /** Finds the first schema.org Recipe object in the page's JSON-LD blocks. */
    public static function extractRecipe(string $html): ?array
    {
        if (! preg_match_all('#<script[^>]+type=["\']application/ld\+json["\'][^>]*>(.*?)</script>#is', $html, $matches)) {
            return null;
        }

        foreach ($matches[1] as $json) {
            $decoded = json_decode(html_entity_decode(trim($json), ENT_QUOTES | ENT_HTML5), true);
            if (! is_array($decoded)) {
                continue;
            }
            if ($found = self::findRecipe($decoded)) {
                return $found;
            }
        }

        return null;
    }

    private static function findRecipe(array $node): ?array
    {
        $type = $node['@type'] ?? null;
        if ($type !== null && in_array('Recipe', (array) $type, true)) {
            return $node;
        }
        foreach ($node['@graph'] ?? [] as $child) {
            if (is_array($child) && ($found = self::findRecipe($child))) {
                return $found;
            }
        }
        if (array_is_list($node)) {
            foreach ($node as $child) {
                if (is_array($child) && ($found = self::findRecipe($child))) {
                    return $found;
                }
            }
        }

        return null;
    }

    /** Maps schema.org fields onto what the recipe form needs. */
    public static function normalize(array $data, string $url): array
    {
        $clean = fn ($v) => trim(html_entity_decode(strip_tags((string) $v), ENT_QUOTES | ENT_HTML5));

        $yield = $data['recipeYield'] ?? null;
        $yield = is_array($yield) ? ($yield[0] ?? null) : $yield;
        $servings = null;
        $yieldLabel = '';
        if ($yield !== null && preg_match('/(\d+(?:[.,]\d+)?)\s*(.*)/u', (string) $yield, $m)) {
            $servings = str_replace(',', '.', $m[1]);
            $yieldLabel = Quantity::parse($m[2]) === null && ! preg_match('/^servings?$/i', trim($m[2])) ? $clean($m[2]) : '';
        }

        $ingredients = collect((array) ($data['recipeIngredient'] ?? $data['ingredients'] ?? []))
            ->map(fn ($i) => $clean(is_array($i) ? ($i['name'] ?? '') : $i))
            ->filter()->values()->all();

        $host = preg_replace('/^www\./', '', (string) parse_url($url, PHP_URL_HOST));

        return [
            'title' => $clean($data['name'] ?? ''),
            'description' => $clean($data['description'] ?? ''),
            'servings' => $servings,
            'yield_label' => mb_substr($yieldLabel, 0, 100),
            'prep_minutes' => self::minutes($data['prepTime'] ?? null),
            'cook_minutes' => self::minutes($data['cookTime'] ?? null),
            'ingredients' => $ingredients,
            'steps' => self::steps($data['recipeInstructions'] ?? [], $clean),
            'source_name' => $host,
            'source_url' => $url,
            'notes' => '',
        ];
    }

    /** "PT1H30M" -> 90 */
    public static function minutes(mixed $iso): ?int
    {
        if (! is_string($iso) || ! preg_match('/^P(?:(\d+)D)?T?(?:(\d+)H)?(?:(\d+)M)?(?:\d+S)?$/i', trim($iso), $m)) {
            return null;
        }
        $total = ((int) ($m[1] ?? 0)) * 1440 + ((int) ($m[2] ?? 0)) * 60 + (int) ($m[3] ?? 0);

        return $total > 0 ? min($total, 10000) : null;
    }

    /** @return list<string> */
    private static function steps(mixed $instructions, callable $clean): array
    {
        if (is_string($instructions)) {
            return collect(preg_split('/\R+/u', $clean($instructions)))->map('trim')->filter()->values()->all();
        }

        $steps = [];
        foreach ((array) $instructions as $item) {
            if (is_string($item)) {
                $steps[] = $clean($item);
            } elseif (is_array($item)) {
                if (isset($item['itemListElement'])) {          // HowToSection
                    array_push($steps, ...self::steps($item['itemListElement'], $clean));
                } else {
                    $steps[] = $clean($item['text'] ?? $item['name'] ?? '');
                }
            }
        }

        return array_values(array_filter($steps));
    }
}
