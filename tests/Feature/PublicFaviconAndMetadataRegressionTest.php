<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOV-PUBLIC-03A: иконка сайта и метаданные NOV-PUBLIC-02 не регрессировали.
 *
 * Известное ограничение (не исправляется без утверждённого владельцем ресурса):
 * единственная иконка — ICO 16×16; Яндекс рекомендовал SVG или 120×120. Тест
 * фиксирует, что иконка существует, корректна и подключена один раз, и НЕ
 * объявляет рекомендацию Яндекса выполненной.
 */
class PublicFaviconAndMetadataRegressionTest extends TestCase
{
    use RefreshDatabase;

    public function test_the_declared_favicon_exists_and_is_a_valid_ico_served_at_the_root_too(): void
    {
        $html = $this->get('/')->assertOk()->getContent();

        $this->assertSame(1, preg_match_all('#<link[^>]+rel="(?:shortcut )?icon"#', $html), 'exactly one icon link');
        preg_match('#<link rel="shortcut icon" href="([^"]+)" type="image/x-icon">#', $html, $m);
        $this->assertNotEmpty($m, 'icon link present');

        $path = (string) parse_url($m[1], PHP_URL_PATH);
        $this->assertSame('/img/favicon.ico', $path);
        $this->assertFileExists(public_path(ltrim($path, '/')));

        // /favicon.ico — адрес по умолчанию для краулеров: файл есть и совпадает с объявленным
        $this->assertFileExists(public_path('favicon.ico'));
        $this->assertSame(md5_file(public_path('img/favicon.ico')), md5_file(public_path('favicon.ico')));

        $data = (string) file_get_contents(public_path('img/favicon.ico'));
        $header = unpack('vreserved/vtype/vcount', substr($data, 0, 6));
        $this->assertSame(0, $header['reserved']);
        $this->assertSame(1, $header['type'], 'ICO');
        $this->assertGreaterThanOrEqual(1, $header['count']);
    }

    public function test_og_image_default_and_logo_resolve_to_an_existing_brand_file(): void
    {
        $this->assertFileExists(public_path('img/logo.png'));
        $this->assertSame('image/png', getimagesize(public_path('img/logo.png'))['mime']);
    }

    public function test_json_ld_and_metadata_from_nov_public_02_stay_single_and_valid(): void
    {
        Countries_image::create(['title' => 'SYNTHETIC', 'slug' => 'egipet', 'category' => 'x', 'description' => 'Описание страны для проверки.']);

        foreach (['/', '/contacts'] as $path) {
            $html = $this->get($path)->assertOk()->getContent();

            $this->assertSame(1, preg_match_all('#<script type="application/ld\+json">#', $html), "one JSON-LD block on {$path}");
            preg_match('#<script type="application/ld\+json">(.*?)</script>#s', $html, $m);
            $data = json_decode($m[1], true, 512, JSON_THROW_ON_ERROR);
            $this->assertSame('TravelAgency', $data['@type']);
            $this->assertStringStartsWith('https://avilona.ru/', $data['url']);
        }

        $country = $this->get('/countries/egipet')->assertOk()->getContent();
        $this->assertSame(1, preg_match_all('#<link rel="canonical"#', $country));
        $this->assertSame(0, preg_match_all('#application/ld\+json#', $country), 'no JSON-LD outside / and /contacts');
        $this->assertStringContainsString('<title>SYNTHETIC | avilona.ru</title>', $country);
    }
}
