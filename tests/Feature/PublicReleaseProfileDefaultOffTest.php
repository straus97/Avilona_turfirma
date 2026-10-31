<?php

namespace Tests\Feature;

use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * NOV-PUBLIC-01: без RELEASE_PUBLIC_ONLY приложение ведёт себя как прежде —
 * публичные и приватные маршруты на месте, отправка отзыва доступна.
 */
class PublicReleaseProfileDefaultOffTest extends TestCase
{
    public function test_public_profile_is_off_by_default(): void
    {
        $this->assertFalse(config('release.public_only'));
    }

    public function test_full_application_routes_are_registered_when_profile_is_off(): void
    {
        foreach (['home.index', 'contact.send_contact', 'review.index', 'review_create.index', 'login', 'register',
            'cabinet.dashboard', 'admin.dashboard', 'api.destination-cities', 'seo.robots', 'seo.sitemap'] as $name) {
            $this->assertTrue(Route::has($name), "{$name} must exist in the full application");
        }
    }

    public function test_public_routes_file_is_part_of_the_full_application(): void
    {
        $this->assertFileExists(base_path('routes/public.php'));
        $this->assertStringContainsString("require __DIR__ . '/public.php';", file_get_contents(base_path('routes/web.php')));
    }
}
