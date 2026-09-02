<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\News;
use App\Models\OurClient;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * E1-A4-F1: dynamic public detail pages (country / destination / interesting
 * article / news / for-our-clients) must expose their own page-specific
 * title and description as Open Graph and Twitter metadata, instead of
 * silently falling back to the shared layout's generic homepage-brand
 * defaults (resources/views/layouts/main.blade.php).
 *
 * Each record uses a distinctive fixture title so a generic fallback could
 * never accidentally satisfy the assertions.
 */
class PublicDynamicSocialMetadataConsistencyTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Country detail
    // -----------------------------------------------------------------------

    public function test_country_detail_page_exposes_page_specific_social_metadata(): void
    {
        Countries_image::create([
            'title' => 'Marker Country Zentoria 7421',
            'slug' => 'marker-country-zentoria-7421',
            'category' => 'Европа',
            'description' => 'Country description body',
        ]);

        $response = $this->get(route('countries.show_countries_image', ['slug' => 'marker-country-zentoria-7421']));
        $response->assertOk();

        $this->assertSocialMetadataMatchesPageMetadata($response->getContent(), 'Marker Country Zentoria 7421');
    }

    // -----------------------------------------------------------------------
    // 2. Destination detail
    // -----------------------------------------------------------------------

    public function test_destination_detail_page_exposes_page_specific_social_metadata(): void
    {
        Destination_image::create([
            'title' => 'Marker Destination Voltrania 8532',
            'slug' => 'marker-destination-voltrania-8532',
            'description' => 'Destination description body',
        ]);

        $response = $this->get(route('destinations.show_destinations_image', ['slug' => 'marker-destination-voltrania-8532']));
        $response->assertOk();

        $this->assertSocialMetadataMatchesPageMetadata($response->getContent(), 'Marker Destination Voltrania 8532');
    }

    // -----------------------------------------------------------------------
    // 3. Interesting article detail
    // -----------------------------------------------------------------------

    public function test_article_detail_page_exposes_page_specific_social_metadata(): void
    {
        Article::create([
            'title' => 'Marker Article Windgale 3319',
            'slug' => 'marker-article-windgale-3319',
            'content' => 'Article content body',
            'image' => null,
        ]);

        $response = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'marker-article-windgale-3319']));
        $response->assertOk();

        $this->assertSocialMetadataMatchesPageMetadata($response->getContent(), 'Marker Article Windgale 3319');
    }

    // -----------------------------------------------------------------------
    // 4. News detail (in-view fallback / truncated-description path)
    // -----------------------------------------------------------------------

    public function test_news_detail_page_exposes_page_specific_social_metadata(): void
    {
        News::create([
            'title' => 'Marker News Ferrowick 6604',
            'slug' => 'marker-news-ferrowick-6604',
            'link' => 'https://example.com/news/marker-news-ferrowick-6604',
            'description' => 'News description body',
            'image' => null,
            'pub_date' => now(),
        ]);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'marker-news-ferrowick-6604']));
        $response->assertOk();

        $this->assertSocialMetadataMatchesPageMetadata($response->getContent(), 'Marker News Ferrowick 6604');
    }

    // -----------------------------------------------------------------------
    // 5. For-our-clients / special-offer detail
    // -----------------------------------------------------------------------

    public function test_special_offer_detail_page_exposes_page_specific_social_metadata(): void
    {
        OurClient::create([
            'title' => 'Marker Special Offer Brackenfall 9127',
            'slug' => 'marker-special-offer-brackenfall-9127',
            'content' => 'Special offer content body',
            'image' => null,
        ]);

        $response = $this->get(route('helpful_information.show_special', ['slug' => 'marker-special-offer-brackenfall-9127']));
        $response->assertOk();

        $this->assertSocialMetadataMatchesPageMetadata($response->getContent(), 'Marker Special Offer Brackenfall 9127');
    }

    // -----------------------------------------------------------------------
    // 6. E2-A5-I1: editorial detail pages declare og:type="article"
    //    (list pages keep the shared default "website" — not asserted here).
    // -----------------------------------------------------------------------

    public function test_news_detail_declares_article_open_graph_type(): void
    {
        News::create([
            'title' => 'OG Type Marker News 7781',
            'slug' => 'og-type-marker-news-7781',
            'link' => 'https://example.com/news/og-type-marker-news-7781',
            'description' => 'News body for og:type check',
            'image' => null,
            'pub_date' => now(),
        ]);

        $response = $this->get(route('helpful_news_id.index', ['slug' => 'og-type-marker-news-7781']));
        $response->assertOk();

        $xpath = new \DOMXPath($this->parseHtml($response->getContent()));
        $ogType = $xpath->query('//meta[@property="og:type"]')->item(0);
        $this->assertNotNull($ogType);
        $this->assertSame('article', $ogType->getAttribute('content'));
    }

    public function test_article_detail_declares_article_open_graph_type(): void
    {
        Article::create([
            'title' => 'OG Type Marker Article 7782',
            'slug' => 'og-type-marker-article-7782',
            'content' => 'Article body for og:type check',
            'image' => null,
        ]);

        $response = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'og-type-marker-article-7782']));
        $response->assertOk();

        $xpath = new \DOMXPath($this->parseHtml($response->getContent()));
        $ogType = $xpath->query('//meta[@property="og:type"]')->item(0);
        $this->assertNotNull($ogType);
        $this->assertSame('article', $ogType->getAttribute('content'));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Assert that the rendered page's <title>, meta description, og:title,
     * og:description, twitter:title and twitter:description are all
     * page-specific (contain the fixture marker), non-empty, and that the
     * social tags carry the exact same semantic value as their HTML
     * counterparts rather than the shared layout's generic fallback copy.
     */
    private function assertSocialMetadataMatchesPageMetadata(string $html, string $expectedMarker): void
    {
        $doc = $this->parseHtml($html);
        $xpath = new \DOMXPath($doc);

        $genericFallbackTitle = 'Туристическая фирма Авилона - Путешествуйте с нами | avilona.ru';

        $titleNode = $xpath->query('//title')->item(0);
        $this->assertNotNull($titleNode);
        $renderedTitle = trim($titleNode->textContent);
        $this->assertStringContainsString($expectedMarker, $renderedTitle);
        $this->assertNotSame($genericFallbackTitle, $renderedTitle);

        $metaDescriptionNode = $xpath->query('//meta[@name="description"]')->item(0);
        $this->assertNotNull($metaDescriptionNode);
        $renderedDescription = $metaDescriptionNode->getAttribute('content');
        $this->assertStringContainsString($expectedMarker, $renderedDescription);

        $ogTitleNode = $xpath->query('//meta[@property="og:title"]')->item(0);
        $this->assertNotNull($ogTitleNode);
        $ogTitle = $ogTitleNode->getAttribute('content');
        $this->assertStringContainsString($expectedMarker, $ogTitle);
        $this->assertNotSame($genericFallbackTitle, $ogTitle);
        $this->assertSame($renderedTitle, $ogTitle);

        $ogDescriptionNode = $xpath->query('//meta[@property="og:description"]')->item(0);
        $this->assertNotNull($ogDescriptionNode);
        $ogDescription = $ogDescriptionNode->getAttribute('content');
        $this->assertStringContainsString($expectedMarker, $ogDescription);
        $this->assertSame($renderedDescription, $ogDescription);

        $twitterTitleNode = $xpath->query('//meta[@name="twitter:title"]')->item(0);
        $this->assertNotNull($twitterTitleNode);
        $twitterTitle = $twitterTitleNode->getAttribute('content');
        $this->assertStringContainsString($expectedMarker, $twitterTitle);
        $this->assertNotSame($genericFallbackTitle, $twitterTitle);
        $this->assertSame($renderedTitle, $twitterTitle);

        $twitterDescriptionNode = $xpath->query('//meta[@name="twitter:description"]')->item(0);
        $this->assertNotNull($twitterDescriptionNode);
        $twitterDescription = $twitterDescriptionNode->getAttribute('content');
        $this->assertStringContainsString($expectedMarker, $twitterDescription);
        $this->assertSame($renderedDescription, $twitterDescription);
    }

    private function parseHtml(string $html): \DOMDocument
    {
        $doc = new \DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML($html, LIBXML_NOERROR | LIBXML_NOWARNING);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return $doc;
    }
}
