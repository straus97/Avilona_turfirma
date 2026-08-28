<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use App\Models\Destination_image;
use App\Models\OurClient;
use Illuminate\Cache\Events\KeyWritten;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

/**
 * E1-RPD-02: four public cache call sites passed the integer literal 60 while
 * their comment/intent said "60 minutes". A Laravel 12 integer Cache::remember
 * TTL is in SECONDS, so those entries expired after one minute instead of one
 * hour. They now pass 3600 (1 hour, in seconds), matching sibling controllers
 * that already use 3600.
 *
 * These tests lock the corrected TTL deterministically: the real array store
 * is used and every KeyWritten cache event is recorded, so the exact number
 * of seconds the store was asked to keep each controller key is asserted
 * directly. No sleeping, no production cache access.
 *
 * Covered call sites:
 *   - Countries\ImageController      -> countries_image_{slug}
 *   - Destination\ImageController    -> destination_image_{slug}, destination_title_menu
 *   - HelpfulInformation\ClientsController  -> clients_page_{page}
 *   - HelpfulInformation\SpecialController  -> special_{slug}
 */
class PublicCacheTtlConsistencyTest extends TestCase
{
    use RefreshDatabase;

    /** @var array<string,int|null> */
    private array $writtenTtls = [];

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    private function recordWrittenTtls(): void
    {
        $this->writtenTtls = [];

        Event::listen(KeyWritten::class, function (KeyWritten $event): void {
            $this->writtenTtls[$event->key] = $event->seconds;
        });
    }

    private function assertHourTtl(string $key): void
    {
        $this->assertArrayHasKey($key, $this->writtenTtls, "cache key {$key} was never written");
        $this->assertSame(
            3600,
            $this->writtenTtls[$key],
            "cache TTL for {$key} must be 3600 seconds (1 hour), not a bare 60"
        );
    }

    // -----------------------------------------------------------------------
    // Behavioural TTL assertions per call site
    // -----------------------------------------------------------------------

    public function test_countries_image_detail_caches_for_one_hour(): void
    {
        Countries_image::create([
            'title' => 'Ttl Marker Country',
            'slug' => 'ttl-marker-country',
            'category' => 'Европа',
            'description' => 'Country description body.',
        ]);

        $this->recordWrittenTtls();

        $this->get(route('countries.show_countries_image', ['slug' => 'ttl-marker-country']))->assertOk();

        $this->assertHourTtl('countries_image_ttl-marker-country');
    }

    public function test_destination_image_detail_and_title_menu_cache_for_one_hour(): void
    {
        Destination_image::create([
            'title' => 'Ttl Marker Destination',
            'slug' => 'ttl-marker-destination',
            'description' => 'Destination description body.',
            'image_small' => null,
            'image_large' => null,
        ]);

        $this->recordWrittenTtls();

        $this->get(route('destinations.show_destinations_image', ['slug' => 'ttl-marker-destination']))->assertOk();

        $this->assertHourTtl('destination_image_ttl-marker-destination');
        $this->assertHourTtl('destination_title_menu');
    }

    public function test_specials_listing_caches_for_one_hour(): void
    {
        OurClient::create([
            'title' => 'Ttl Marker Special Listing',
            'slug' => 'ttl-marker-special-listing',
            'content' => 'Special listing content body.',
            'image' => null,
        ]);

        $this->recordWrittenTtls();

        $this->get(route('for_our_clients.index'))->assertOk();

        $this->assertHourTtl('clients_page_1');
    }

    public function test_specials_detail_caches_for_one_hour(): void
    {
        OurClient::create([
            'title' => 'Ttl Marker Special Detail',
            'slug' => 'ttl-marker-special-detail',
            'content' => 'Special detail content body.',
            'image' => null,
        ]);

        $this->recordWrittenTtls();

        $this->get(route('helpful_information.show_special', ['slug' => 'ttl-marker-special-detail']))->assertOk();

        $this->assertHourTtl('special_ttl-marker-special-detail');
    }

    public function test_about_page_category_lookups_cache_for_one_hour(): void
    {
        Countries_image::create([
            'title' => 'Ttl About Asia',
            'slug' => 'ttl-about-asia',
            'category' => 'Азия',
            'description' => 'Body.',
        ]);
        Countries_image::create([
            'title' => 'Ttl About Europe',
            'slug' => 'ttl-about-europe',
            'category' => 'Европа',
            'description' => 'Body.',
        ]);

        $this->recordWrittenTtls();

        $this->get(route('about_company.index'))->assertOk();

        foreach ([
            'about_category_asia',
            'about_category_africa',
            'about_category_middle_east',
            'about_category_europe',
            'about_category_caribbean',
        ] as $key) {
            $this->assertHourTtl($key);
        }
    }

    // -----------------------------------------------------------------------
    // Static-source regression: no bare `= 60;` TTL literal survives
    // -----------------------------------------------------------------------

    public function test_affected_controllers_no_longer_use_a_bare_60_ttl(): void
    {
        $files = [
            'Http/Controllers/Countries/ImageController.php',
            'Http/Controllers/Destination/ImageController.php',
            'Http/Controllers/HelpfulInformation/ClientsController.php',
            'Http/Controllers/HelpfulInformation/SpecialController.php',
            'Http/Controllers/Company/AboutController.php',
        ];

        foreach ($files as $relative) {
            $source = file_get_contents(app_path($relative));

            $this->assertMatchesRegularExpression(
                '/\$cacheTime\s*=\s*3600\s*;/',
                $source,
                "{$relative} must set \$cacheTime = 3600"
            );
            $this->assertDoesNotMatchRegularExpression(
                '/\$cacheTime\s*=\s*60\s*;/',
                $source,
                "{$relative} must not keep the bare 60 TTL"
            );
            $this->assertStringNotContainsString(
                'кеширования в минутах',
                $source,
                "{$relative} must not keep the misleading 'minutes' comment"
            );
        }
    }
}
