<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use App\Models\Destination_image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Tests\TestCase;

/**
 * E1-FINAL-06: hardcoded numeric marketing claims ("55 ... стран",
 * "12 ... направлений") were removed from Countries / Destinations listing and
 * detail meta descriptions in favour of durable wording, so the copy cannot
 * silently drift out of sync with the actual catalogue. No count queries added.
 */
class DurableMetaCopyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function metaDescription(string $html): string
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        $xpath = new \DOMXPath($dom);
        $node = $xpath->query('//meta[@name="description"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $node);

        return $node->getAttribute('content');
    }

    public function test_countries_listing_meta_has_no_hardcoded_count(): void
    {
        $description = $this->metaDescription($this->get(route('countries.index'))->assertOk()->getContent());

        $this->assertStringNotContainsString('55', $description);
        $this->assertStringContainsString('стран', $description);
    }

    public function test_country_detail_meta_has_no_hardcoded_count(): void
    {
        Countries_image::create([
            'title' => 'Durable Meta Country',
            'slug' => 'durable-meta-country',
            'category' => 'Европа',
            'description' => 'Body.',
        ]);

        $description = $this->metaDescription(
            $this->get(route('countries.show_countries_image', ['slug' => 'durable-meta-country']))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('55', $description);
        $this->assertStringContainsString('Durable Meta Country', $description);
    }

    public function test_destinations_listing_meta_has_no_hardcoded_count(): void
    {
        $description = $this->metaDescription($this->get(route('destination.index'))->assertOk()->getContent());

        $this->assertStringNotContainsString('12', $description);
        $this->assertStringContainsString('направлени', $description);
    }

    public function test_destination_detail_meta_has_no_hardcoded_count(): void
    {
        Destination_image::create([
            'title' => 'Durable Meta Destination',
            'slug' => 'durable-meta-destination',
            'description' => 'Body.',
            'image_small' => null,
            'image_large' => null,
        ]);

        $description = $this->metaDescription(
            $this->get(route('destinations.show_destinations_image', ['slug' => 'durable-meta-destination']))->assertOk()->getContent()
        );

        $this->assertStringNotContainsString('12', $description);
        $this->assertStringContainsString('Durable Meta Destination', $description);
    }
}
