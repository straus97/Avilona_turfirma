<?php

namespace Tests\Feature;

use App\Models\Countries_image;
use App\Models\Destination_image;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOV-PUBLIC-04B: если у записи есть только image_small (миниатюра), detail-страница не
 * растягивает её в «геро»-рамку; большое изображение выводится как прежде; без изображений —
 * заглушка без пустого <img src="">. Данные синтетические.
 */
class PublicDetailMediaFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @return array<string, array{0: string, 1: string}> */
    public static function pages(): array
    {
        return [
            'country' => ['country', 'countries.show_countries_image'],
            'destination' => ['destination', 'destinations.show_destinations_image'],
        ];
    }

    private function make(string $kind, array $images): string
    {
        $slug = "synthetic-{$kind}-media";
        $base = ['title' => "SYNTHETIC {$kind}", 'slug' => $slug, 'description' => '<p>SYNTHETIC</p>'] + $images;

        $kind === 'country'
            ? Countries_image::create($base + ['category' => 'Азия'])
            : Destination_image::create($base);

        return $slug;
    }

    private function main(string $html): \DOMXPath
    {
        $previous = libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        $dom->loadHTML('<?xml encoding="UTF-8">' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new \DOMXPath($dom);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_large_image_keeps_the_hero_frame(string $kind, string $route): void
    {
        $slug = $this->make($kind, ['image_small' => '/img/s.png', 'image_large' => '/img/l.png']);
        $x = $this->main($this->get(route($route, ['slug' => $slug]))->assertOk()->getContent());

        $this->assertSame(1, $x->query('//div[contains(concat(" ", @class, " "), " e2-detail-media ")]/img[@src="/img/l.png"]')->length);
        $this->assertSame(0, $x->query('//*[contains(@class, "e2-detail-thumb")]')->length);
        $this->assertSame(0, $x->query('//img[@src="/img/s.png"]')->length);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_small_only_image_is_not_stretched_into_the_hero_frame(string $kind, string $route): void
    {
        $slug = $this->make($kind, ['image_small' => '/img/s.png', 'image_large' => null]);
        $x = $this->main($this->get(route($route, ['slug' => $slug]))->assertOk()->getContent());

        $this->assertSame(1, $x->query('//div[contains(concat(" ", @class, " "), " e2-detail-thumb ")]/img[@src="/img/s.png"]')->length);
        $this->assertSame(0, $x->query('//div[contains(concat(" ", @class, " "), " e2-detail-media ")]/img')->length);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('pages')]
    public function test_no_image_renders_the_placeholder_and_no_bare_img(string $kind, string $route): void
    {
        $slug = $this->make($kind, ['image_small' => null, 'image_large' => null]);
        $x = $this->main($this->get(route($route, ['slug' => $slug]))->assertOk()->getContent());

        $this->assertSame(1, $x->query('//div[contains(@class, "e2-detail-media") and contains(@class, "e2-media-placeholder")]')->length);
        $this->assertSame(0, $x->query('//main//img[@src=""]')->length);
    }
}
