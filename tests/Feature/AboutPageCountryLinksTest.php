<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E1-FINAL-01: страница "О компании" строила ссылки блока "Основные направления"
 * из числового Countries_image->id, тогда как маршрут countries.show_countries_image
 * ожидает {slug} и резолвится Countries_image::where('slug', $slug)->firstOrFail().
 * Числовой id давал 404. Теперь ссылки строятся из ->slug, а при отсутствии slug
 * название выводится без ссылки (та же защита, что в публичных списках стран).
 */
class AboutPageCountryLinksTest extends TestCase
{
    use RefreshDatabase;

    private const CATEGORY_SLUGS = [
        'about-asia-country',
        'about-africa-country',
        'about-east-country',
        'about-europe-country',
        'about-caribbean-country',
    ];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    /** @return string[] href values of every anchor inside <main> list items */
    private function aboutCountryHrefs(string $html): array
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $hrefs = [];
        foreach ($xpath->query('//main//li//a[@href]') as $a) {
            $hrefs[] = $a->getAttribute('href');
        }

        return $hrefs;
    }

    private function seedAllCategories(): void
    {
        $rows = [
            ['title' => 'About Asia Country', 'slug' => 'about-asia-country', 'category' => 'Азия'],
            ['title' => 'About Africa Country', 'slug' => 'about-africa-country', 'category' => 'Африка'],
            ['title' => 'About East Country', 'slug' => 'about-east-country', 'category' => 'Ближний Восток'],
            ['title' => 'About Europe Country', 'slug' => 'about-europe-country', 'category' => 'Европа'],
            ['title' => 'About Caribbean Country', 'slug' => 'about-caribbean-country', 'category' => 'Карибский бассейн'],
        ];

        foreach ($rows as $row) {
            Countries_image::create($row + ['description' => 'Body']);
        }
    }

    private function countryHrefs(array $hrefs): array
    {
        return array_values(array_filter($hrefs, static fn (string $h): bool => str_contains($h, '/countries/')));
    }

    public function test_about_page_country_hrefs_use_slugs_not_numeric_ids(): void
    {
        $this->seedAllCategories();

        $response = $this->get(route('about_company.index'));
        $response->assertOk();

        $countryHrefs = $this->countryHrefs($this->aboutCountryHrefs($response->getContent()));
        $this->assertCount(5, $countryHrefs, 'all five category countries must render as links');

        foreach ($countryHrefs as $href) {
            $tail = substr($href, (int) strrpos($href, '/') + 1);
            $this->assertFalse(ctype_digit($tail), "country href must be a slug, not a numeric id: {$href}");
            $this->assertContains($tail, self::CATEGORY_SLUGS, "unexpected country slug in href: {$href}");
        }
    }

    public function test_about_page_country_links_resolve_with_200(): void
    {
        $this->seedAllCategories();

        $response = $this->get(route('about_company.index'));
        $response->assertOk();

        $countryHrefs = $this->countryHrefs($this->aboutCountryHrefs($response->getContent()));
        $this->assertNotEmpty($countryHrefs);

        foreach ($countryHrefs as $href) {
            Cache::flush();
            $this->get($href)->assertOk();
        }
    }

    public function test_about_page_country_without_slug_renders_title_without_a_broken_link(): void
    {
        $this->seedAllCategories();

        // Force an empty slug directly in the DB, bypassing model slug generation.
        Countries_image::where('slug', 'about-europe-country')->update(['slug' => '']);
        Cache::flush();

        $response = $this->get(route('about_company.index'));
        $response->assertOk();
        $response->assertSee('About Europe Country', false);

        foreach ($this->countryHrefs($this->aboutCountryHrefs($response->getContent())) as $href) {
            $tail = substr($href, (int) strrpos($href, '/') + 1);
            $this->assertNotSame('', $tail, "no empty-slug country link may be generated: {$href}");
        }
    }
}
