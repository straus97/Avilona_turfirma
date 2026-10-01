<?php

namespace Tests\Feature;

use App\Models\IncomingInquiry;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E5-A4: финальная полировка поиска туров.
 *
 * - главная больше не имитирует форму поиска (параметры не переносятся в модуль Tourvisor);
 * - cookie-страница честно сообщает о внешнем модуле;
 * - удалённые legacy-маршруты Sletat и локального поиска действительно отсутствуют,
 *   а маршруты Tourvisor/IncomingInquiry остались.
 */
class TourSearchProductPolishTest extends TestCase
{
    use RefreshDatabase;

    private function homeHtml(): string
    {
        return $this->get(route('home.index'))->assertOk()->getContent();
    }

    public function test_home_has_no_fake_search_form_or_old_filter_parameters(): void
    {
        $html = $this->homeHtml();

        foreach ([
            'id="tourSearchForm"',
            'name="departure_city"',
            'name="destination_country"',
            'name="date_range"',
            'name="nights_min"',
            'name="nights_max"',
            'name="adults"',
            'id="touristSummary"',
            'toggleTouristDropdown',
            'validateTourSearchForm',
            'daterangepicker',
            'moment.min.js',
            'code.jquery.com',
            'Найти туры',
        ] as $legacyMarker) {
            $this->assertStringNotContainsString($legacyMarker, $html, 'Остаток старой формы на главной: ' . $legacyMarker);
        }
    }

    public function test_home_search_entry_leads_to_tours_without_pretending_to_carry_filters(): void
    {
        $html = $this->homeHtml();
        $toursUrl = route('tours.index');

        $this->assertSame(1, preg_match_all('/id="tour-search"/', $html));
        $this->assertMatchesRegularExpression(
            '/<section class="e2-search-entry" id="tour-search"[^>]*>.*?<a class="e2-btn e2-search-entry__btn" href="' . preg_quote($toursUrl, '/') . '">/s',
            $html
        );
        // Ни одна ссылка на /tours с главной не несёт параметров поиска.
        preg_match_all('/href="' . preg_quote($toursUrl, '/') . '([^"]*)"/', $html, $matches);
        $this->assertNotEmpty($matches[1]);
        foreach ($matches[1] as $suffix) {
            $this->assertSame('', $suffix);
        }
        // Старые якорные ссылки на форму больше не нужны.
        $this->assertStringNotContainsString('href="#tour-search"', $html);
        // Tourvisor на главной не подключается.
        $this->assertStringNotContainsString('tourvisor.ru', $html);
    }

    public function test_discovery_pages_link_straight_to_tours_not_to_home_form_anchor(): void
    {
        foreach (['countries.index', 'destination.index'] as $routeName) {
            $html = $this->get(route($routeName))->assertOk()->getContent();
            $this->assertStringNotContainsString(route('home.index') . '#tour-search', $html, $routeName);
            $this->assertStringContainsString('href="' . route('tours.index') . '"', $html, $routeName);
        }
    }

    public function test_cookie_page_discloses_the_external_tour_search_module(): void
    {
        $text = preg_replace('/\s+/u', ' ', strip_tags($this->get(route('cookies.info'))->assertOk()->getContent()));

        $this->assertStringContainsString('Модуль поиска туров', $text);
        $this->assertStringContainsString('внешний модуль Tourvisor', $text);
        $this->assertStringContainsString('не управляет данными, которые использует этот внешний модуль', $text);
    }

    public function test_removed_legacy_search_and_sletat_api_routes_are_gone(): void
    {
        $this->getJson('/api/tours/search')->assertNotFound();
        $this->getJson('/api/tours/departure-cities')->assertNotFound();
        $this->getJson('/api/tours/destination-countries')->assertNotFound();
        $this->getJson('/api/tours/resorts?country=Турция')->assertNotFound();
        $this->getJson('/api/sletat/countries')->assertNotFound();
        $this->postJson('/api/sletat/search', [])->assertNotFound();
        $this->getJson('/api/sletat/search/results')->assertNotFound();

        foreach (['tours.search', 'tours.departure-cities', 'tours.destination-countries', 'tours.resorts', 'sletat.search', 'sletat.countries'] as $name) {
            $this->assertFalse(Route::has($name), 'Маршрут должен быть удалён: ' . $name);
        }
    }

    public function test_legacy_sletat_code_and_config_are_removed(): void
    {
        // Файлы, а не class_exists(): оптимизированный classmap может помнить удалённый класс.
        foreach ([
            'app/Services/Sletat/SletatApiService.php',
            'app/Http/Controllers/Api/SletatController.php',
            'app/Http/Controllers/Api/TourSearchController.php',
            'app/Console/Commands/TestSletatConnectionCommand.php',
        ] as $removed) {
            $this->assertFileDoesNotExist(base_path($removed), $removed);
        }
        $this->assertNull(config('services.sletat'));
        $this->assertArrayNotHasKey('sletat:test', Artisan::all());
    }

    public function test_current_tourvisor_routes_and_dependent_routes_remain(): void
    {
        $this->assertTrue(Route::has('tours.index'));
        $this->assertTrue(Route::has('webhooks.tourvisor.inquiries'));
        $this->assertTrue(Route::has('api.destination-cities'));
        $this->assertTrue(Route::has('bookings.create'));
        $this->assertNotNull(config('services.tourvisor'));
        $this->assertTrue(class_exists(IncomingInquiry::class));
    }
}
