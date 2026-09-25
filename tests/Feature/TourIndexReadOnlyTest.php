<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\TestCase;

/**
 * E5-A1: серверная оболочка /tours с официальным модулем Tourvisor.
 *
 * Удалённый JavaScript Tourvisor здесь намеренно не тестируется — проверяется
 * только то, что отдаёт Laravel: структура страницы, контейнер и загрузчик
 * модуля, отсутствие прежней временной формы и секретов, текст «заявка — не
 * бронирование» и запасной путь связи.
 */
class TourIndexReadOnlyTest extends TestCase
{
    use RefreshDatabase;

    private const MODULE_ID = '9981450';
    private const LOADER_URL = 'https://tourvisor.ru/module/init.js';

    private function toursHtml(): string
    {
        return $this->get(route('tours.index'))->assertOk()->getContent();
    }

    public function test_tours_route_renders_the_tours_view(): void
    {
        $this->get(route('tours.index'))
            ->assertOk()
            ->assertViewIs('tours.index');
    }

    public function test_page_has_one_main_landmark_and_one_h1(): void
    {
        $html = $this->toursHtml();

        $this->assertSame(1, preg_match_all('/<main[\s>]/i', $html));
        $this->assertSame(1, preg_match_all('/<h1[\s>]/i', $html));
        $this->assertStringContainsString('>Поиск туров</h1>', $html);
        // id="main-content" проставляет e2-public.js в рантайме; в разметке дублей быть не должно.
        $this->assertSame(0, preg_match_all('/id="main-content"/', $html));
        $this->assertStringContainsString('href="#main-content"', $html);
    }

    public function test_page_has_no_heading_level_skips(): void
    {
        $html = $this->toursHtml();

        preg_match_all('/<h([1-6])[\s>]/i', $html, $matches);
        $levels = array_map('intval', $matches[1]);

        $previous = 0;
        foreach ($levels as $level) {
            $this->assertLessThanOrEqual($previous + 1, $level, 'Пропуск уровня заголовка: h' . $level);
            $previous = max($previous, $level);
        }
    }

    public function test_tourvisor_module_container_is_present_exactly_once(): void
    {
        $html = $this->toursHtml();

        $this->assertSame(1, preg_match_all('/class="[^"]*\btv-search-form\b[^"]*"/', $html));
        $this->assertSame(1, preg_match_all('/\btv-moduleid-' . self::MODULE_ID . '\b/', $html));
        $this->assertSame(1, preg_match_all('/\btv-moduleid-\d+\b/', $html));
    }

    public function test_tourvisor_loader_script_is_included_exactly_once_and_over_https(): void
    {
        $html = $this->toursHtml();

        $this->assertSame(1, substr_count($html, 'tourvisor.ru/module/init.js'));
        $this->assertSame(1, substr_count($html, 'src="' . self::LOADER_URL . '"'));
        $this->assertStringNotContainsString('src="//tourvisor.ru', $html);
    }

    public function test_tourvisor_loader_is_not_injected_into_other_public_pages(): void
    {
        foreach (['home.index', 'contact.index', 'countries.index', 'destination.index'] as $routeName) {
            $this->assertStringNotContainsString(
                'tourvisor.ru',
                $this->get(route($routeName))->assertOk()->getContent(),
                $routeName . ' не должна загружать Tourvisor'
            );
        }
    }

    public function test_old_temporary_search_form_is_no_longer_rendered(): void
    {
        $html = $this->toursHtml();

        foreach ([
            'id="tourSearchForm"',
            'name="departure_city"',
            'name="destination_country"',
            'name="tour_operators[]"',
            'name="date_range"',
            'id="resortsContainer"',
            'class="price-chart-widget',
            'Найдено туров',
            'Туры не найдены',
            'daterangepicker',
            'code.jquery.com',
            'api/tours/resorts',
        ] as $legacyMarker) {
            $this->assertStringNotContainsString($legacyMarker, $html, 'Остаток старой формы: ' . $legacyMarker);
        }
    }

    public function test_legacy_search_query_parameters_are_ignored_and_page_stays_single_module(): void
    {
        $html = $this->get(route('tours.index', [
            'departure_city' => 'Москва',
            'destination_country' => 'Египет',
            'tour_operators' => ['Coral Travel'],
            'sort_by' => 'price_asc',
        ]))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'tourvisor.ru/module/init.js'));
        $this->assertSame(1, preg_match_all('/\btv-moduleid-' . self::MODULE_ID . '\b/', $html));
        $this->assertStringNotContainsString('id="tourSearchForm"', $html);
    }

    public function test_no_secret_or_credential_is_rendered(): void
    {
        $html = $this->toursHtml();

        $this->assertDoesNotMatchRegularExpression('/api[_-]?key|auth[_-]?key|authkey|access[_-]?token|password\s*[=:]/i', $html);
        $this->assertStringNotContainsString('TOURVISOR', $html);
    }

    public function test_page_explains_that_inquiry_is_not_booking_and_manager_verifies(): void
    {
        $html = $this->toursHtml();

        $this->assertStringContainsString('Цены и наличие мест могут меняться', $html);
        $this->assertStringContainsString('не бронирование и', $html);
        $this->assertStringContainsString('менеджер Авилоны проверит', $html);
        $this->assertStringContainsString('Заявка сама по себе тур не бронирует и ничего не оплачивает', $html);
    }

    public function test_page_makes_no_instant_booking_or_guarantee_claims(): void
    {
        $text = mb_strtolower(strip_tags($this->toursHtml()));

        foreach (['мгновенн', 'гарантированн', 'гарантия цены', 'подтверждено наличие', 'онлайн-оплат'] as $forbidden) {
            $this->assertStringNotContainsString($forbidden, $text, 'Недопустимое обещание: ' . $forbidden);
        }
    }

    public function test_fallback_contact_path_exists_and_is_not_an_immediate_error(): void
    {
        $html = $this->toursHtml();

        // Постоянный запасной путь: общая модалка менеджеров и страница контактов.
        $this->assertMatchesRegularExpression(
            '/data-bs-target="#managerContactModal"\s+data-manager-mode="all"/',
            $html
        );
        $this->assertStringContainsString('href="' . route('contact.index') . '"', $html);
        $this->assertStringContainsString('id="managerContactModal"', $html);

        // Подсказка о недогруженном модуле скрыта по умолчанию (не показываем ошибку сразу).
        $this->assertMatchesRegularExpression('/<div[^>]*id="tourvisor-delay-notice"[^>]*\shidden[\s>]/', $html);
        $this->assertStringContainsString('<noscript>', $html);
    }

    public function test_authenticated_tourist_sees_the_same_single_module(): void
    {
        $role = Role::query()->firstOrCreate(
            ['name' => Role::TOURIST],
            ['description' => Role::availableRoles()[Role::TOURIST] ?? Role::TOURIST]
        );
        $tourist = User::factory()->create();
        $tourist->roles()->attach($role->id);

        $html = $this->actingAs($tourist)->get(route('tours.index'))->assertOk()->getContent();

        $this->assertSame(1, substr_count($html, 'tourvisor.ru/module/init.js'));
        $this->assertSame(1, preg_match_all('/\btv-moduleid-' . self::MODULE_ID . '\b/', $html));
        $this->assertSame(1, preg_match_all('/<main[\s>]/i', $html));
    }

    public function test_index_request_makes_no_external_http_call_and_no_tour_query(): void
    {
        Http::preventStrayRequests();
        DB::enableQueryLog();

        $this->get(route('tours.index'))->assertOk();

        foreach (DB::getQueryLog() as $entry) {
            $this->assertDoesNotMatchRegularExpression('/\btours\b/', $entry['query'], 'Запрос к таблице tours: ' . $entry['query']);
        }
    }
}
