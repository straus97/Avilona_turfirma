<?php

namespace Tests\Feature;

use Tests\TestCase;

/**
 * E2-A6-I2: миграция публичной страницы 404 на систему E2.
 *
 * Проверяет только то, что реально в объёме этого слайса: код ответа,
 * единственный <h1>, отсутствие легаси инлайновых стилей/классов
 * Bootstrap-4 btn, и что страница не зависит от БД/аутентификации (простой
 * анонимный запрос к несуществующему маршруту).
 */
class Public404E2RedesignTest extends TestCase
{
    public function test_unknown_route_returns_404_with_expected_shell(): void
    {
        $response = $this->get('/this-route-does-not-exist-e2-a6-i2');

        $response->assertStatus(404);
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
        $response->assertSee('Ой! Похоже, вы заблудились', false);
    }

    public function test_404_page_links_back_home_and_to_contacts(): void
    {
        $response = $this->get('/this-route-does-not-exist-e2-a6-i2');

        $response->assertStatus(404);
        $response->assertSee('href="' . route('home.index') . '"', false);
        $response->assertSee('href="' . route('contact.index') . '"', false);
    }

    public function test_404_page_no_longer_uses_legacy_bootstrap4_button_classes(): void
    {
        $response = $this->get('/this-route-does-not-exist-e2-a6-i2');

        $response->assertStatus(404);
        $response->assertDontSee('class="btn btn-primary"', false);
    }

    public function test_404_page_does_not_require_authentication(): void
    {
        // No session/auth setup at all — anonymous request must still
        // resolve cleanly to the 404 shell.
        $response = $this->get('/another-missing-route-e2-a6-i2');

        $response->assertStatus(404);
    }
}
