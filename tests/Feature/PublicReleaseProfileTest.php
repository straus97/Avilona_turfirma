<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\UserFormMail;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * NOV-PUBLIC-01: профиль RELEASE_PUBLIC_ONLY=true.
 *
 * Приватные маршруты не регистрируются вовсе (а не прячутся ссылками): вход,
 * регистрация, кабинеты, чат, документы, внутренний API, webhook Tourvisor и
 * отправка отзыва отвечают 404 как несуществующие адреса. Список публичных
 * маршрутов закрытый: новый маршрут по умолчанию приватный, и этот тест
 * упадёт, пока его не добавят в routes/public.php осознанно.
 */
class PublicReleaseProfileTest extends TestCase
{
    use RefreshDatabase;

    /** Единственные именованные маршруты, существующие в публичном релизе. */
    private const PUBLIC_ROUTE_NAMES = [
        'home.index', 'tours.index',
        'about_company.index', 'employees.index', 'awards.index',
        'countries.index', 'countries.show_countries_image',
        'destination.index', 'destinations.show_destinations_image',
        'contact.index', 'contact.send_home', 'contact.send_contact',
        'review.index',
        'interesting_articles.index', 'helpful_information.show_interesting_news',
        'helpful_news.index', 'news.rss', 'helpful_news_id.index',
        'for_our_clients.index', 'helpful_information.show_special', 'travel_dictionary.index',
        'cookies.info', 'personal_data_consent.info',
        'review_personal_data_consent.info', 'review_publication_consent.info',
        'captcha_reload.index',
        'seo.robots', 'seo.sitemap',
    ];

    /** Маршруты сторонних пакетов, которые они регистрируют сами (без имени проекта). */
    private const PACKAGE_ROUTE_URIS = [
        'captcha/{config?}', 'captcha/api/{config?}', 'sanctum/csrf-cookie',
    ];

    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';

        // Профиль включается до регистрации провайдеров: RouteServiceProvider читает флаг при загрузке.
        $app->beforeBootstrapping(RegisterProviders::class, function ($app) {
            $app['config']->set('release.public_only', true);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_only_the_explicit_public_allowlist_is_registered(): void
    {
        foreach (Route::getRoutes() as $route) {
            $name = $route->getName();
            $uri = $route->uri();

            if (str_starts_with($uri, '_ignition')) {
                continue; // dev-пакет: в production ставится с --no-dev (проверяется в runbook)
            }

            $this->assertTrue(
                in_array($name, self::PUBLIC_ROUTE_NAMES, true) || in_array($uri, self::PACKAGE_ROUTE_URIS, true),
                'Unexpected route in public release profile: ' . ($name ?? '(unnamed)') . ' => ' . $uri
            );
        }

        foreach (self::PUBLIC_ROUTE_NAMES as $name) {
            $this->assertTrue(Route::has($name), "public route {$name} must exist");
        }
    }

    public function test_private_route_names_do_not_exist(): void
    {
        foreach (['login', 'register', 'logout', 'password.request', 'password.change', 'cabinet.dashboard',
            'cabinet.admin.dashboard', 'cabinet.manager.dashboard', 'admin.dashboard', 'manager.dashboard',
            'review_create.index', 'api.destination-cities', 'registration_personal_data_consent.info'] as $name) {
            $this->assertFalse(Route::has($name), "{$name} must not be registered in the public profile");
        }
    }

    public function test_private_and_internal_urls_are_not_found_for_anonymous_visitors(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/reset-password/abc', '/verify-email', '/password/change',
            '/cabinet', '/cabinet/bookings', '/cabinet/chat', '/cabinet/admin/dashboard', '/cabinet/manager/dashboard',
            '/admin/dashboard', '/admin/users', '/manager/dashboard', '/profile/dashboard', '/account',
            '/account/personal-data-consent', '/messages', '/messages/unread-count', '/bookings/create',
            '/api/destination-cities', '/api/webhooks/tourvisor/inquiries/' . str_repeat('a', 40),
            '/reviews/create', '/logout'] as $path) {
            $this->get($path)->assertNotFound();
        }

        foreach (['/login', '/register', '/forgot-password', '/logout', '/reviews/create', '/cabinet/bookings'] as $path) {
            $this->post($path, [])->assertNotFound();
        }
    }

    public function test_public_pages_render_without_account_links(): void
    {
        foreach (['/', '/tours', '/contacts', '/reviews', '/company/about_company', '/company/employees',
            '/company/awards', '/countries', '/destinations', '/helpful_information/news',
            '/helpful_information/interesting_articles', '/helpful_information/for_our_clients',
            '/helpful_information/travel_dictionary', '/cookies', '/personal-data-consent',
            '/reviews/personal-data-consent', '/reviews/publication-consent'] as $path) {
            $response = $this->get($path);
            $response->assertOk();

            $html = $response->getContent();
            $this->assertStringNotContainsString('href="' . url('/login') . '"', $html, "{$path} links to /login");
            $this->assertStringNotContainsString('href="' . url('/register') . '"', $html, "{$path} links to /register");
            $this->assertStringNotContainsString('/cabinet', $html, "{$path} links to the cabinet");
        }
    }

    public function test_reviews_page_lists_reviews_but_offers_no_submission_form(): void
    {
        $response = $this->get('/reviews');

        $response->assertOk();
        $html = $response->getContent();
        $this->assertStringNotContainsString('id="review-form"', $html);
        $this->assertStringNotContainsString('reviews/create', $html);
        $this->assertStringNotContainsString('href="#review-form"', $html);
    }

    public function test_contact_form_still_works_and_goes_to_the_configured_recipient(): void
    {
        Validator::extend('captcha', fn () => true);
        Mail::fake();

        $this->post(route('contact.send_contact'), [
            'name' => 'Test User',
            'email' => 'submitter@example.com',
            'subject' => 'Hello',
            'message' => str_repeat('A', 60),
            'captcha' => 'anything',
            'agree' => '1',
            'personal_data_consent' => '1',
        ])->assertRedirect()->assertSessionHas('success');

        Mail::assertSent(ContactFormMail::class, fn ($mail) => $mail->hasTo('straus97@mail.ru'));
        Mail::assertSent(UserFormMail::class, fn ($mail) => $mail->hasTo('submitter@example.com'));
    }

    public function test_robots_and_sitemap_are_served_in_the_public_profile(): void
    {
        $this->get('/robots.txt')->assertOk();
        $this->get('/sitemap.xml')->assertOk();
    }

    public function test_error_page_has_no_login_button(): void
    {
        $html = view('errors.419')->render();

        $this->assertStringNotContainsString('Войти в кабинет', $html);
    }
}
