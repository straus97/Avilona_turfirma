<?php

namespace Tests\Feature;

use Tests\TestCase;

class PublicSitemapConsistencyTest extends TestCase
{
    private function loadSitemap(): \SimpleXMLElement
    {
        $path = base_path('sitemap.xml');

        $this->assertFileExists($path);

        $xml = simplexml_load_file($path);

        $this->assertNotFalse($xml, 'sitemap.xml must parse as valid XML');

        return $xml;
    }

    public function test_sitemap_uses_standard_namespace(): void
    {
        $xml = $this->loadSitemap();
        $namespaces = $xml->getNamespaces();

        $this->assertSame('http://www.sitemaps.org/schemas/sitemap/0.9', $namespaces['']);
    }

    public function test_sitemap_url_count_and_uniqueness(): void
    {
        $xml = $this->loadSitemap();

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = trim((string) $url->loc);
        }

        $this->assertCount(273, $locs, 'sitemap must contain exactly 273 URLs');
        $this->assertCount(273, array_unique($locs), 'every <loc> must be unique');
    }

    public function test_all_urls_use_https_and_bare_host(): void
    {
        $xml = $this->loadSitemap();

        foreach ($xml->url as $url) {
            $loc = trim((string) $url->loc);
            $host = parse_url($loc, PHP_URL_HOST);
            $scheme = parse_url($loc, PHP_URL_SCHEME);

            $this->assertSame('https', $scheme, "URL must be HTTPS: {$loc}");
            $this->assertSame('avilona.ru', $host, "URL must use host avilona.ru: {$loc}");
        }
    }

    public function test_required_urls_present_exactly_once(): void
    {
        $xml = $this->loadSitemap();

        $locs = [];
        foreach ($xml->url as $url) {
            $locs[] = trim((string) $url->loc);
        }

        $required = [
            'https://avilona.ru/tours',
            'https://avilona.ru/company/employees',
            'https://avilona.ru/company/awards',
            'https://avilona.ru/helpful_information/travel_dictionary',
        ];

        foreach ($required as $requiredLoc) {
            $count = count(array_filter($locs, fn ($loc) => $loc === $requiredLoc));
            $this->assertSame(1, $count, "expected exactly one occurrence of {$requiredLoc}");
        }
    }

    public function test_no_lastmod_elements_remain(): void
    {
        $xml = $this->loadSitemap();

        foreach ($xml->url as $url) {
            $this->assertFalse(isset($url->lastmod), 'no <lastmod> elements should remain in the sitemap');
        }
    }

    public function test_no_private_route_prefixes_present(): void
    {
        $xml = $this->loadSitemap();

        $privatePrefixes = [
            '/admin',
            '/manager',
            '/cabinet',
            '/login',
            '/register',
            '/forgot-password',
        ];

        foreach ($xml->url as $url) {
            $loc = trim((string) $url->loc);
            $path = parse_url($loc, PHP_URL_PATH) ?? '';

            foreach ($privatePrefixes as $prefix) {
                $this->assertFalse(
                    str_starts_with($path, $prefix),
                    "sitemap must not expose private route prefix {$prefix}: {$loc}"
                );
            }
        }
    }
}
