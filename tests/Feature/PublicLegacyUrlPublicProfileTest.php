<?php

namespace Tests\Feature;

use App\Models\News;
use App\Services\Seo\LegacyUrlClassifier;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Foundation\Bootstrap\RegisterProviders;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * NOV-PUBLIC-03A: профиль RELEASE_PUBLIC_ONLY=true (то, что реально развернут на новом хостинге).
 *
 * Редиректы ?page=1 и 404 вне диапазона работают без приватных маршрутов, а
 * приватные адреса не существуют и не редиректятся: классификатор видит их как
 * отсутствующие, а не как «служебные».
 */
class PublicLegacyUrlPublicProfileTest extends TestCase
{
    use RefreshDatabase;

    public function createApplication()
    {
        $app = require __DIR__ . '/../../bootstrap/app.php';

        $app->beforeBootstrapping(RegisterProviders::class, function ($app) {
            $app['config']->set('release.public_only', true);
        });

        $app->make(Kernel::class)->bootstrap();

        return $app;
    }

    public function test_first_page_redirect_and_out_of_range_404_work_in_the_public_only_profile(): void
    {
        for ($i = 1; $i <= 7; $i++) {
            News::create(['title' => "SYNTHETIC $i", 'slug' => "s-$i", 'link' => "https://example.com/$i", 'description' => 'd', 'image' => null, 'pub_date' => now()->subDays($i)]);
        }

        $this->get('/helpful_information/news?page=1')->assertStatus(301)->assertHeader('Location', '/helpful_information/news');
        $this->get('/reviews?page=0')->assertStatus(301)->assertHeader('Location', '/reviews');
        $this->get('/helpful_information/news?page=2')->assertOk();
        $this->get('/helpful_information/news?page=3')->assertNotFound();
        $this->get('/countries/17')->assertNotFound();
    }

    public function test_private_urls_do_not_exist_and_are_never_redirected_in_the_public_only_profile(): void
    {
        foreach (['/login', '/register', '/cabinet', '/cabinet/bookings', '/manager/clients', '/admin', '/api/destination-cities', '/password/change'] as $path) {
            foreach ([$path, $path . '?page=1'] as $url) {
                $response = $this->get($url);
                $this->assertSame(404, $response->getStatusCode(), $url);
                $this->assertNull($response->headers->get('Location'), $url);
            }
        }

        $classifier = $this->app->make(LegacyUrlClassifier::class);
        $this->assertSame('no_route', $classifier->classify('/login')['class']);
        $this->assertSame('no_route', $classifier->classify('/cabinet')['class']);
    }

    public function test_the_whole_historical_inventory_classifies_the_same_in_the_public_only_profile(): void
    {
        $classifier = $this->app->make(LegacyUrlClassifier::class);
        $classes = [];

        foreach (preg_split('/\R/', trim((string) file_get_contents(resource_path('seo/legacy-sitemap-paths.txt')))) as $path) {
            $c = $classifier->classify($path);
            $classes[$c['class']] = ($classes[$c['class']] ?? 0) + 1;
        }

        ksort($classes);
        $this->assertSame([
            'document_file' => 5, 'redirect_first_page' => 3, 'supported_list' => 6,
            'supported_page' => 6, 'supported_pagination' => 34, 'supported_slug' => 218,
        ], $classes);
    }
}
