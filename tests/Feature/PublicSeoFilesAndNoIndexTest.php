<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOV-PUBLIC-01: robots.txt / sitemap.xml реально отдаются приложением (корень
 * проекта вне document root), без сессионных cookie; приватные и служебные
 * поверхности получают X-Robots-Tag: noindex, публичные — нет.
 */
class PublicSeoFilesAndNoIndexTest extends TestCase
{
    use RefreshDatabase;

    public function test_robots_txt_is_served_as_plain_text_from_the_repository_file(): void
    {
        $response = $this->get('/robots.txt');

        $response->assertOk();
        $this->assertStringStartsWith('text/plain', (string) $response->headers->get('Content-Type'));
        // Правила — из файла репозитория; строка Sitemap формируется из config('seo.public_url').
        $expected = array_values(array_filter(
            preg_split('/\R/', rtrim(file_get_contents(base_path('robots.txt')))),
            fn ($line) => stripos($line, 'sitemap:') !== 0
        ));
        $this->assertSame(
            rtrim(implode("\n", $expected)) . "\n\nSitemap: https://avilona.ru/sitemap.xml\n",
            $response->getContent()
        );
        $this->assertCount(0, $response->headers->getCookies(), 'robots.txt must not set cookies');
    }

    public function test_sitemap_xml_is_served_as_generated_xml(): void
    {
        $response = $this->get('/sitemap.xml');

        $response->assertOk();
        $this->assertStringContainsString('xml', (string) $response->headers->get('Content-Type'));
        $this->assertNotFalse(simplexml_load_string($response->getContent()));
        $this->assertCount(0, $response->headers->getCookies(), 'sitemap.xml must not set cookies');
    }

    public function test_auth_and_cabinet_entry_points_are_marked_noindex(): void
    {
        foreach (['/login', '/register', '/forgot-password', '/cabinet', '/admin/dashboard', '/manager/dashboard'] as $path) {
            $response = $this->get($path);

            $this->assertSame(
                'noindex, nofollow',
                $response->headers->get('X-Robots-Tag'),
                "{$path} must carry X-Robots-Tag"
            );
        }
    }

    public function test_public_pages_do_not_carry_noindex(): void
    {
        foreach (['/', '/tours', '/contacts', '/company/about_company', '/cookies', '/robots.txt', '/sitemap.xml'] as $path) {
            $this->get($path)->assertHeaderMissing('X-Robots-Tag');
        }
    }

    public function test_any_response_to_a_signed_in_user_is_marked_noindex(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)->get('/')->assertHeader('X-Robots-Tag', 'noindex, nofollow');
    }
}
