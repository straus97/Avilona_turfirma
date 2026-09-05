<?php

namespace Tests\Feature;

use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * E2-A6-I2: миграция пяти публичных правовых страниц на систему E2.
 *
 * Правило слайса: правовой текст ЗАМОРОЖЕН. Эти тесты проверяют только
 * структурный слой (единственный <h1>, хлебные крошки) — существующие
 * Public*Consent*Test / PublicCookieConsentTest уже закрепляют дословный
 * текст каждой страницы и здесь намеренно не дублируются.
 */
class PublicLegalPagesE2RedesignTest extends TestCase
{
    /**
     * @return array<string, array{0: string, 1: string}>
     */
    public static function legalRoutesProvider(): array
    {
        return [
            'cookies' => ['cookies.info', 'Использование cookie'],
            'personal_data_consent' => ['personal_data_consent.info', 'Согласие на обработку персональных данных'],
            'review_personal_data_consent' => [
                'review_personal_data_consent.info',
                'Согласие на обработку персональных данных при направлении отзыва',
            ],
            'review_publication_consent' => [
                'review_publication_consent.info',
                'Согласие на публикацию отзыва и имени',
            ],
            'registration_personal_data_consent' => [
                'registration_personal_data_consent.info',
                'Согласие на обработку персональных данных при регистрации',
            ],
        ];
    }

    #[DataProvider('legalRoutesProvider')]
    public function test_legal_page_has_exactly_one_h1(string $routeName, string $heading): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee($heading);
    }

    #[DataProvider('legalRoutesProvider')]
    public function test_legal_page_uses_e2_breadcrumb_to_home(string $routeName, string $heading): void
    {
        $response = $this->get(route($routeName));

        $response->assertOk();
        $response->assertSee('class="e2-breadcrumb"', false);
        $response->assertSeeInOrder(['Главная', $heading], false);
    }
}
