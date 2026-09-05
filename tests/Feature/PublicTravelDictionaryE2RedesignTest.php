<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * E2-A6-I2: миграция публичной страницы «Туристический словарь» на систему
 * E2. Проверяет только структурный слой редизайна: единственный <h1>,
 * хлебные крошки, hero, отсутствие легаси includes.sidebar, нативный
 * <details>/<summary> accordion вместо Bootstrap-коллапса, и что справочный
 * текст (аббревиатуры/термины) не изменился по существу.
 */
class PublicTravelDictionaryE2RedesignTest extends TestCase
{
    public function test_page_has_exactly_one_h1(): void
    {
        $response = $this->get(route('travel_dictionary.index'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_page_uses_e2_breadcrumb_and_hero(): void
    {
        $response = $this->get(route('travel_dictionary.index'));

        $response->assertOk();
        $response->assertSee('class="e2-breadcrumb"', false);
        $response->assertSee('class="e2-page-hero"', false);
        $response->assertSeeInOrder(['Главная', 'Туристический словарь'], false);
    }

    public function test_view_no_longer_includes_legacy_sidebar(): void
    {
        $source = file_get_contents(resource_path('views/helpful_information/travel_dictionary.blade.php'));

        $this->assertStringNotContainsString("@include('includes.sidebar')", $source);
    }

    public function test_abbreviation_groups_use_native_details_disclosure(): void
    {
        $response = $this->get(route('travel_dictionary.index'));
        $html = $response->getContent();

        $response->assertOk();
        // Native accordion: no Bootstrap accordion collapse/JS markup
        // reintroduced (the shared layout's own navbar toggler legitimately
        // uses data-bs-toggle="collapse" elsewhere on the page, so this
        // checks the accordion-specific classes/attributes instead).
        $this->assertStringNotContainsString('accordion-button', $html);
        $this->assertStringNotContainsString('accordion-collapse', $html);
        $this->assertStringNotContainsString('data-bs-target="#collapseOne"', $html);

        $this->assertSame(4, substr_count($html, 'class="e2-glossary__item"'));
        $response->assertSeeInOrder([
            '<summary class="e2-glossary__summary">На авиабилетах</summary>',
            '<summary class="e2-glossary__summary">В отеле</summary>',
            '<summary class="e2-glossary__summary">Размещение</summary>',
            '<summary class="e2-glossary__summary">Пансион</summary>',
        ], false);
    }

    public function test_reference_content_is_preserved_verbatim(): void
    {
        $response = $this->get(route('travel_dictionary.index'));

        $response->assertOk();
        // Spot-check one entry from each abbreviation group and the terms list.
        $response->assertSee('MR/MRS</strong> - mister/mistress', false);
        $response->assertSee('WiFi</strong> - Wireless Fidelity', false);
        $response->assertSee('DUS</strong> - double use single', false);
        $response->assertSee('HCAI</strong> - High class all Inclusive', false);
        $response->assertSee('АТОР</strong> – Ассоциация туроператоров России', false);
        $response->assertSee('Шоп-тур</strong> – туристическая поездка', false);
    }

    public function test_page_remains_cached_and_free_of_manual_cache_calls(): void
    {
        // Regression guard: E2-A6-I2 must not reintroduce the manual
        // Cache::-based double-cache that CacheResponseConsentIsolationTest
        // already forbids in the controller.
        $controller = file_get_contents(
            app_path('Http/Controllers/HelpfulInformation/DictionaryController.php')
        );

        $this->assertStringNotContainsString('Cache::', $controller);
    }
}
