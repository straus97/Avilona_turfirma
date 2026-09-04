<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\HomeFormMail;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * E2-A6-I1: миграция публичной страницы «Контакты» на систему E2 плюс
 * гигиена публичных форм обратной связи (throttle + детерминированная
 * тема письма).
 *
 * Бизнес-факты (адреса, реквизиты, e-mail) и получатель внутреннего письма
 * (straus97@mail.ru) закреплены в PublicCompanyDetailsConsistencyTest /
 * PublicContactMailFailureLoggingTest и здесь дублируются только точечно.
 */
class PublicContactsE2RedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Validator::extend('captcha', function () {
            return true;
        });
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Contact Redesign User',
            'email' => 'redesign@example.com',
            'subject' => 'Тестовая тема',
            'message' => str_repeat('A', 60),
            'captcha' => 'anything',
            'agree' => '1',
            'personal_data_consent' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // Structure
    // -----------------------------------------------------------------------

    public function test_contacts_page_has_exactly_one_h1(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_contacts_page_uses_e2_breadcrumb_and_hero(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertSee('class="e2-breadcrumb"', false);
        $response->assertSee('class="e2-page-hero"', false);
        $response->assertSeeInOrder(['Главная', 'Контакты'], false);
    }

    public function test_contacts_page_keeps_core_business_facts_and_email(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertSee('avilonatur@bk.ru');
        $response->assertSee('Генерала Симоняка, д. 10');
        $response->assertSee('Звенигородская, д. 22');
        $response->assertSee('Фактический адрес');
        $response->assertSee('Юридический адрес');
    }

    public function test_contacts_page_does_not_render_legacy_bootstrap_modal_scripts(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        $response->assertDontSee("modal('show')", false);
        $response->assertDontSee('id="successModal"', false);
        $response->assertDontSee('id="errorModal"', false);
    }

    // -----------------------------------------------------------------------
    // Opening-hours conflict is NOT silently resolved in this slice
    // -----------------------------------------------------------------------

    public function test_contacts_page_preserves_its_own_opening_hours_wording(): void
    {
        $response = $this->get(route('contact.index'));

        $response->assertOk();
        // Формулировка «Контактов» (пн–пт 11:00–20:00, по предварительной записи)
        // сохранена дословно; расхождение с главной остаётся открытым до E6.
        $response->assertSee('с 11:00 до 20:00');
        $response->assertSee('по предварительной записи', false);
    }

    // -----------------------------------------------------------------------
    // Throttling on the exact public contact POST routes
    // -----------------------------------------------------------------------

    public function test_public_contact_post_routes_are_rate_limited(): void
    {
        foreach (['contact.send_home', 'contact.send_contact'] as $name) {
            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains('POST', $route->methods());
            $this->assertContains('throttle:8,1', $route->gatherMiddleware(), "{$name} must be throttled");
        }
    }

    public function test_contacts_get_route_is_not_throttled_and_keeps_response_cache(): void
    {
        $route = Route::getRoutes()->getByName('contact.index');

        $this->assertNotNull($route);
        $middleware = $route->gatherMiddleware();
        $this->assertContains('cache.response', $middleware);
        foreach ($middleware as $entry) {
            $this->assertStringNotContainsString('throttle', $entry);
        }

        $this->get(route('contact.index'))->assertOk();
    }

    // -----------------------------------------------------------------------
    // Subject hardening: optional field, deterministic fallback, bounded
    // -----------------------------------------------------------------------

    public function test_contact_mail_subject_falls_back_deterministically_when_subject_is_blank(): void
    {
        Mail::fake();

        $this->post(route('contact.send_contact'), $this->validPayload(['subject' => '']))
            ->assertSessionHasNoErrors();

        Mail::assertSent(ContactFormMail::class, function (ContactFormMail $mail): bool {
            return $mail->envelope()->subject === ContactFormMail::DEFAULT_SUBJECT
                && ContactFormMail::DEFAULT_SUBJECT !== '';
        });
    }

    public function test_home_mail_subject_falls_back_deterministically_when_subject_is_omitted(): void
    {
        Mail::fake();

        $payload = $this->validPayload();
        unset($payload['subject']);

        $this->post(route('contact.send_home'), $payload)->assertSessionHasNoErrors();

        Mail::assertSent(HomeFormMail::class, function (HomeFormMail $mail): bool {
            return $mail->envelope()->subject === HomeFormMail::DEFAULT_SUBJECT
                && HomeFormMail::DEFAULT_SUBJECT !== '';
        });
    }

    public function test_contact_mail_subject_is_preserved_when_supplied(): void
    {
        Mail::fake();

        $this->post(route('contact.send_contact'), $this->validPayload(['subject' => 'Вопрос по туру в Турцию']))
            ->assertSessionHasNoErrors();

        Mail::assertSent(ContactFormMail::class, fn (ContactFormMail $mail): bool =>
            $mail->envelope()->subject === 'Вопрос по туру в Турцию');
    }

    public function test_contact_subject_is_length_bounded(): void
    {
        Mail::fake();

        $this->post(route('contact.send_contact'), $this->validPayload(['subject' => str_repeat('т', 151)]))
            ->assertSessionHasErrors('subject');

        Mail::assertNothingSent();
    }

    public function test_contact_get_request_is_unaffected_by_hardening(): void
    {
        $this->get(route('contact.index'))
            ->assertOk()
            ->assertSee('name="subject"', false);
    }
}
