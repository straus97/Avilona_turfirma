<?php

namespace Tests\Feature;

use App\Models\Destination_image;
use App\Models\News;
use App\Models\OurClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E1-RPD consolidated public-data robustness package (render-time findings):
 *
 *  - E1-RPD-01: the news LISTING excerpt must be treated as plain text.
 *    TextHelper::formatNewsDescription() returns DOM textContent (entities
 *    decoded), so raw Blade output could turn inert stored text such as
 *    "&lt;script&gt;..." back into live markup. The excerpt is now emitted
 *    with escaped `{{ }}`.
 *  - E1-RPD-03: destination detail must not emit a bare <img src=""> when
 *    Destination_image.image_large is null.
 *  - E1-RPD-04: OurClient/Specials listing and detail must not emit a bare
 *    <img src=""> when OurClient.image is null.
 *  - E1-RPD-06: destination listing/sidebar must not build detail URLs when
 *    the slug is missing.
 *
 * DOM assertions are scoped to the relevant content/card area so unrelated
 * legitimate layout assets (scripts, images) never influence the result.
 */
class PublicDataRobustnessRenderTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // DOM helpers
    // -----------------------------------------------------------------------

    private function dom(string $html): \DOMDocument
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $dom;
    }

    /** @return \DOMElement[] */
    private function nodesByClass(\DOMXPath $xpath, string $class, ?\DOMNode $context = null): array
    {
        $query = '//*[contains(concat(" ", normalize-space(@class), " "), " ' . $class . ' ")]';
        $result = $context ? $xpath->query('.' . $query, $context) : $xpath->query($query);

        return iterator_to_array($result);
    }

    private function assertNoBareImageSource(\DOMElement $scope): void
    {
        $xpath = new \DOMXPath($scope->ownerDocument);

        foreach ($xpath->query('.//img', $scope) as $img) {
            /** @var \DOMElement $img */
            $this->assertTrue(
                $img->hasAttribute('src'),
                'an <img> without a src attribute was rendered in the content area'
            );
            $this->assertNotSame(
                '',
                trim($img->getAttribute('src')),
                'a bare <img src=""> was rendered in the content area'
            );
        }
    }

    private function mainContentNode(\DOMDocument $dom): \DOMElement
    {
        $node = (new \DOMXPath($dom))->query('//main')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $node, '<main> must be present in the response');

        return $node;
    }

    // -----------------------------------------------------------------------
    // E1-RPD-01 — news listing excerpt XSS (decode-then-raw)
    // -----------------------------------------------------------------------

    public function test_news_listing_excerpt_does_not_reactivate_encoded_markup(): void
    {
        // The helper only keeps the FIRST readable paragraph, so each encoded
        // payload lives in its own record to actually reach an excerpt.
        News::create([
            'title' => 'Encoded Script Payload News Marker',
            'slug' => 'encoded-script-payload-news-marker',
            'link' => 'https://example.com/news/encoded-script-payload',
            'description' => '<p>&lt;script&gt;alert(99173)&lt;/script&gt;</p>',
            'image' => null,
            'pub_date' => '2024-01-02 10:00:00',
        ]);
        News::create([
            'title' => 'Encoded Image Payload News Marker',
            'slug' => 'encoded-image-payload-news-marker',
            'link' => 'https://example.com/news/encoded-image-payload',
            'description' => '<p>&lt;img src=x onerror=alert(99174)&gt;</p>',
            'image' => null,
            'pub_date' => '2024-01-01 10:00:00',
        ]);

        $response = $this->get(route('helpful_news.index'));
        $response->assertOk();

        $dom = $this->dom($response->getContent());
        $xpath = new \DOMXPath($dom);

        $container = $xpath->query('//*[@id="news-container"]')->item(0);
        $this->assertInstanceOf(\DOMElement::class, $container, 'news listing container must be present');

        $excerpts = $this->nodesByClass($xpath, 'card-text', $container);
        $this->assertNotEmpty($excerpts, 'at least one excerpt card must be rendered');

        $excerptText = '';
        foreach ($excerpts as $excerpt) {
            $excerptText .= $excerpt->textContent;

            // No live executable / attacker-controlled elements from the encoded text.
            $this->assertSame(0, $xpath->query('.//script', $excerpt)->length, 'no <script> in excerpt');
            $this->assertSame(0, $xpath->query('.//img', $excerpt)->length, 'no <img> in excerpt');

            foreach ($xpath->query('.//*[@*]', $excerpt) as $element) {
                foreach (iterator_to_array($element->attributes) as $attribute) {
                    $this->assertStringStartsNotWith(
                        'on',
                        strtolower($attribute->name),
                        "no on*-event-handler attribute must appear in the excerpt ({$attribute->name})"
                    );
                }
            }
        }

        // The readable text itself remains visible to the visitor (inert).
        $this->assertStringContainsString('<script>alert(99173)</script>', $excerptText);
        $this->assertStringContainsString('<img src=x onerror=alert(99174)>', $excerptText);

        // Defense in depth: the raw response serialises the payload escaped,
        // never as a live tag originating from these records.
        $content = $response->getContent();
        $this->assertStringNotContainsString('<script>alert(99173)', $content);
        $this->assertStringNotContainsString('<img src=x onerror=alert(99174)', $content);
        $this->assertStringContainsString('&lt;script&gt;alert(99173)', $content);
        $this->assertStringContainsString('&lt;img src=x onerror=alert(99174)&gt;', $content);
    }

    public function test_news_listing_excerpt_keeps_benign_text_readable(): void
    {
        News::create([
            'title' => 'Benign Excerpt News Marker',
            'slug' => 'benign-excerpt-news-marker',
            'link' => 'https://example.com/news/benign-excerpt',
            'description' => '<p>Обычный первый абзац новости для посетителя.</p><p>Второй абзац.</p>',
            'image' => null,
            'pub_date' => '2024-02-01 10:00:00',
        ]);

        $response = $this->get(route('helpful_news.index'));
        $response->assertOk();

        $dom = $this->dom($response->getContent());
        $xpath = new \DOMXPath($dom);
        $container = $xpath->query('//*[@id="news-container"]')->item(0);
        $excerpts = $this->nodesByClass($xpath, 'card-text', $container);

        $excerptText = '';
        foreach ($excerpts as $excerpt) {
            $excerptText .= $excerpt->textContent;
        }

        $this->assertStringContainsString('Обычный первый абзац новости для посетителя.', $excerptText);
        $this->assertStringNotContainsString('Второй абзац.', $excerptText);
    }

    // -----------------------------------------------------------------------
    // E1-RPD-03 — destination detail nullable image_large
    // -----------------------------------------------------------------------

    public function test_destination_detail_with_null_image_large_renders_without_bare_image(): void
    {
        Destination_image::create([
            'title' => 'Destination Null Image Marker',
            'slug' => 'destination-null-image-marker',
            'description' => 'Destination description body.',
            'image_small' => null,
            'image_large' => null,
        ]);

        $response = $this->get(route('destinations.show_destinations_image', ['slug' => 'destination-null-image-marker']));
        $response->assertOk();
        $response->assertSee('Destination Null Image Marker', false);

        $this->assertNoBareImageSource($this->mainContentNode($this->dom($response->getContent())));
    }

    public function test_destination_detail_with_image_large_still_renders_the_image(): void
    {
        Destination_image::create([
            'title' => 'Destination With Image Marker',
            'slug' => 'destination-with-image-marker',
            'description' => 'Destination description body.',
            'image_small' => null,
            'image_large' => 'https://example.com/destination-large.jpg',
        ]);

        $response = $this->get(route('destinations.show_destinations_image', ['slug' => 'destination-with-image-marker']));
        $response->assertOk();

        $main = $this->mainContentNode($this->dom($response->getContent()));
        $xpath = new \DOMXPath($main->ownerDocument);
        $this->assertSame(
            1,
            $xpath->query('.//img[@src="https://example.com/destination-large.jpg"]', $main)->length
        );
        $this->assertNoBareImageSource($main);
    }

    // -----------------------------------------------------------------------
    // E1-RPD-04 — OurClient / Specials nullable image
    // -----------------------------------------------------------------------

    public function test_specials_listing_with_null_image_renders_without_bare_image(): void
    {
        OurClient::create([
            'title' => 'Special Listing Null Image Marker',
            'slug' => 'special-listing-null-image-marker',
            'content' => 'Special listing content body.',
            'image' => null,
        ]);

        $response = $this->get(route('for_our_clients.index'));
        $response->assertOk();
        $response->assertSee('Special Listing Null Image Marker', false);

        $this->assertNoBareImageSource($this->mainContentNode($this->dom($response->getContent())));
    }

    public function test_specials_detail_with_null_image_renders_without_bare_image(): void
    {
        OurClient::create([
            'title' => 'Special Detail Null Image Marker',
            'slug' => 'special-detail-null-image-marker',
            'content' => 'Special detail content body.',
            'image' => null,
        ]);

        $response = $this->get(route('helpful_information.show_special', ['slug' => 'special-detail-null-image-marker']));
        $response->assertOk();
        $response->assertSee('Special Detail Null Image Marker', false);

        $this->assertNoBareImageSource($this->mainContentNode($this->dom($response->getContent())));
    }

    public function test_specials_detail_with_image_still_renders_the_image(): void
    {
        OurClient::create([
            'title' => 'Special Detail With Image Marker',
            'slug' => 'special-detail-with-image-marker',
            'content' => 'Special detail content body.',
            'image' => 'https://example.com/special-detail.jpg',
        ]);

        $response = $this->get(route('helpful_information.show_special', ['slug' => 'special-detail-with-image-marker']));
        $response->assertOk();

        $main = $this->mainContentNode($this->dom($response->getContent()));
        $xpath = new \DOMXPath($main->ownerDocument);
        $this->assertSame(
            1,
            $xpath->query('.//img[@src="https://example.com/special-detail.jpg"]', $main)->length
        );
        $this->assertNoBareImageSource($main);
    }

    // -----------------------------------------------------------------------
    // E1-RPD-06 — destination null-slug defense (listing + sidebar)
    // -----------------------------------------------------------------------

    public function test_destination_listing_with_missing_slug_renders_title_without_link(): void
    {
        Destination_image::create([
            'title' => 'Destination Slugless Listing Marker',
            'slug' => '',
            'description' => 'Slugless destination description.',
            'image_small' => null,
            'image_large' => null,
        ]);
        Destination_image::create([
            'title' => 'Destination Slugged Listing Marker',
            'slug' => 'destination-slugged-listing-marker',
            'description' => 'Slugged destination description.',
            'image_small' => null,
            'image_large' => null,
        ]);

        $response = $this->get(route('destination.index'));
        $response->assertOk();
        $response->assertSee('Destination Slugless Listing Marker', false);
        $response->assertSee('Destination Slugged Listing Marker', false);

        $dom = $this->dom($response->getContent());
        $xpath = new \DOMXPath($dom);

        // The slugged entry keeps its clickable title.
        $sluggedLinks = $xpath->query(
            '//main//a[contains(@href, "/destinations/destination-slugged-listing-marker")]'
        );
        $this->assertSame(1, $sluggedLinks->length);

        // The slugless entry is present as text but produces no anchor at all
        // (no route() call with an empty slug -> no "/destinations/" bare link).
        foreach ($xpath->query('//main//a[@href]') as $a) {
            /** @var \DOMElement $a */
            $href = $a->getAttribute('href');
            $this->assertNotSame(url('/destinations') . '/', $href);
            $this->assertStringNotContainsString('Destination Slugless Listing Marker', $a->textContent);
        }
    }

    public function test_destination_sidebar_with_missing_slug_does_not_throw_and_keeps_title(): void
    {
        Destination_image::create([
            'title' => 'Destination Slugless Sidebar Marker',
            'slug' => '',
            'description' => 'Slugless sidebar destination description.',
            'image_small' => null,
            'image_large' => null,
        ]);
        Destination_image::create([
            'title' => 'Destination Detail Anchor Marker',
            'slug' => 'destination-detail-anchor-marker',
            'description' => 'Detail anchor destination description.',
            'image_small' => null,
            'image_large' => 'https://example.com/anchor.jpg',
        ]);

        $response = $this->get(route('destinations.show_destinations_image', ['slug' => 'destination-detail-anchor-marker']));
        $response->assertOk();
        $response->assertSee('Destination Slugless Sidebar Marker', false);

        $dom = $this->dom($response->getContent());
        $xpath = new \DOMXPath($dom);

        // Sidebar nav must not contain a link whose text is the slugless title.
        foreach ($xpath->query('//a[@href]') as $a) {
            /** @var \DOMElement $a */
            if (str_contains($a->textContent, 'Destination Slugless Sidebar Marker')) {
                $this->fail('slugless destination must not be rendered as a nav link');
            }
        }
    }
}
