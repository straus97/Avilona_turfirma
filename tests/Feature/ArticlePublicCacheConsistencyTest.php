<?php

namespace Tests\Feature;

use App\Models\Article;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Правило: публичный список статей (interesting_articles.index) и публичная
 * страница статьи (helpful_information.show_interesting_news) больше не
 * используют ни middleware cache.response, ни контроллерный Cache::remember.
 * Обе точки читают Article напрямую, поэтому изменения из admin/manager CMS
 * (создание, обновление, удаление статьи) должны быть видны немедленно, даже
 * если соответствующая публичная страница уже была ранее запрошена.
 */
class ArticlePublicCacheConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Cache::flush();
    }

    // -----------------------------------------------------------------------
    // 1. Middleware regression
    // -----------------------------------------------------------------------

    public function test_public_article_routes_do_not_use_response_cache_middleware(): void
    {
        $indexRoute = Route::getRoutes()->getByName('interesting_articles.index');
        $detailRoute = Route::getRoutes()->getByName('helpful_information.show_interesting_news');

        $this->assertNotNull($indexRoute);
        $this->assertNotNull($detailRoute);

        $this->assertNotContains('cache.response', $indexRoute->gatherMiddleware());
        $this->assertNotContains('cache.response', $detailRoute->gatherMiddleware());

        $this->assertSame('helpful_information/interesting_articles', $indexRoute->uri());
        $this->assertContains('GET', $indexRoute->methods());
        $this->assertContains('HEAD', $indexRoute->methods());

        $this->assertSame('helpful_information/interesting_articles/{slug}', $detailRoute->uri());
        $this->assertContains('GET', $detailRoute->methods());
        $this->assertContains('HEAD', $detailRoute->methods());
    }

    // -----------------------------------------------------------------------
    // 2. Static source regression
    // -----------------------------------------------------------------------

    public function test_public_article_controllers_do_not_use_controller_cache(): void
    {
        $articlesController = file_get_contents(app_path('Http/Controllers/HelpfulInformation/ArticlesController.php'));
        $newsController = file_get_contents(app_path('Http/Controllers/HelpfulInformation/InterestingNewsController.php'));

        $this->assertStringNotContainsString('Cache::remember', $articlesController);
        $this->assertStringNotContainsString('Cache::remember', $newsController);
        $this->assertStringNotContainsString('Illuminate\Support\Facades\Cache', $articlesController);
        $this->assertStringNotContainsString('Illuminate\Support\Facades\Cache', $newsController);

        $routesContent = file_get_contents(base_path('routes/web.php'));

        foreach (['interesting_articles.index', 'helpful_information.show_interesting_news'] as $name) {
            preg_match('/Route::get\([^;]*?"' . preg_quote($name, '/') . '"[^;]*?\);/s', $routesContent, $matches);
            $this->assertNotEmpty($matches, "Route definition for {$name} was not found");
            $this->assertStringNotContainsString('cache.response', $matches[0]);
        }
    }

    // -----------------------------------------------------------------------
    // 3 & 4. Public endpoints remain available
    // -----------------------------------------------------------------------

    public function test_public_article_index_remains_available(): void
    {
        $this->createArticle([
            'title' => 'Index Marker Title 111',
            'content' => 'Index marker content 111',
            'slug' => 'index-marker-slug-111',
        ]);

        $response = $this->get(route('interesting_articles.index'));

        $response->assertOk();
        $response->assertSee('Index Marker Title 111');
    }

    public function test_public_article_detail_remains_available(): void
    {
        $this->createArticle([
            'title' => 'Detail Marker Title 222',
            'content' => 'Detail marker content 222',
            'slug' => 'detail-marker-slug-222',
        ]);

        $response = $this->get(route('helpful_information.show_interesting_news', ['slug' => 'detail-marker-slug-222']));

        $response->assertOk();
        $response->assertSee('Detail Marker Title 222');
    }

    // -----------------------------------------------------------------------
    // 5 & 6. Immediate visibility after create
    // -----------------------------------------------------------------------

    public function test_admin_created_article_is_visible_immediately_after_index_was_previously_requested(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);

        $before = $this->get(route('interesting_articles.index'));
        $before->assertOk();
        $before->assertDontSee('Fresh Admin Article 333');

        $response = $this->actingAs($admin)->post(route('cabinet.admin.articles.store'), [
            'title' => 'Fresh Admin Article 333',
            'content' => 'Fresh admin content 333',
            'image' => 'https://example.com/image-333.jpg',
            'slug' => 'fresh-admin-article-333',
        ]);
        $response->assertRedirect(route('cabinet.admin.articles'));

        $after = $this->get(route('interesting_articles.index'));
        $after->assertOk();
        $after->assertSee('Fresh Admin Article 333');
    }

    public function test_manager_created_article_is_visible_immediately_after_index_was_previously_requested(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);

        $before = $this->get(route('interesting_articles.index'));
        $before->assertOk();
        $before->assertDontSee('Fresh Manager Article 444');

        $response = $this->actingAs($manager)->post(route('cabinet.manager.articles.store'), [
            'title' => 'Fresh Manager Article 444',
            'content' => 'Fresh manager content 444',
            'image' => 'https://example.com/image-444.jpg',
            'slug' => 'fresh-manager-article-444',
        ]);
        $response->assertRedirect(route('cabinet.manager.articles'));

        $after = $this->get(route('interesting_articles.index'));
        $after->assertOk();
        $after->assertSee('Fresh Manager Article 444');
    }

    // -----------------------------------------------------------------------
    // 7 & 8. Immediate visibility after update, same URL
    // -----------------------------------------------------------------------

    public function test_admin_updated_article_is_visible_immediately_at_the_same_previously_requested_detail_url(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $article = $this->createArticle([
            'title' => 'Old Admin Marker 555',
            'content' => 'Old admin content 555',
            'slug' => 'stable-admin-slug-555',
        ]);

        $detailUrl = route('helpful_information.show_interesting_news', ['slug' => 'stable-admin-slug-555']);

        $before = $this->get($detailUrl);
        $before->assertOk();
        $before->assertSee('Old Admin Marker 555');

        $response = $this->actingAs($admin)->put(route('cabinet.admin.articles.update', $article), [
            'title' => 'New Admin Marker 555',
            'content' => 'New admin content 555',
            'image' => 'https://example.com/image-555.jpg',
            'slug' => 'stable-admin-slug-555',
        ]);
        $response->assertRedirect(route('cabinet.admin.articles'));

        $after = $this->get($detailUrl);
        $after->assertOk();
        $after->assertSee('New Admin Marker 555');
        $after->assertDontSee('Old Admin Marker 555');
    }

    public function test_manager_updated_article_is_visible_immediately_at_the_same_previously_requested_detail_url(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $article = $this->createArticle([
            'title' => 'Old Manager Marker 666',
            'content' => 'Old manager content 666',
            'slug' => 'stable-manager-slug-666',
        ]);

        $detailUrl = route('helpful_information.show_interesting_news', ['slug' => 'stable-manager-slug-666']);

        $before = $this->get($detailUrl);
        $before->assertOk();
        $before->assertSee('Old Manager Marker 666');

        $response = $this->actingAs($manager)->put(route('cabinet.manager.articles.update', $article), [
            'title' => 'New Manager Marker 666',
            'content' => 'New manager content 666',
            'image' => 'https://example.com/image-666.jpg',
            'slug' => 'stable-manager-slug-666',
        ]);
        $response->assertRedirect(route('cabinet.manager.articles'));

        $after = $this->get($detailUrl);
        $after->assertOk();
        $after->assertSee('New Manager Marker 666');
        $after->assertDontSee('Old Manager Marker 666');
    }

    // -----------------------------------------------------------------------
    // 9 & 10. Immediate removal after delete
    // -----------------------------------------------------------------------

    public function test_admin_deleted_article_disappears_immediately_after_public_pages_were_requested(): void
    {
        $admin = $this->makeUser([Role::ADMIN]);
        $article = $this->createArticle([
            'title' => 'Doomed Admin Marker 777',
            'content' => 'Doomed admin content 777',
            'slug' => 'doomed-admin-slug-777',
        ]);

        $detailUrl = route('helpful_information.show_interesting_news', ['slug' => 'doomed-admin-slug-777']);

        $this->get(route('interesting_articles.index'))->assertSee('Doomed Admin Marker 777');
        $this->get($detailUrl)->assertOk();

        $response = $this->actingAs($admin)->delete(route('cabinet.admin.articles.delete', $article));
        $response->assertRedirect();

        $this->get(route('interesting_articles.index'))->assertDontSee('Doomed Admin Marker 777');
        $this->get($detailUrl)->assertNotFound();
    }

    public function test_manager_deleted_article_disappears_immediately_after_public_pages_were_requested(): void
    {
        $manager = $this->makeUser([Role::MANAGER]);
        $article = $this->createArticle([
            'title' => 'Doomed Manager Marker 888',
            'content' => 'Doomed manager content 888',
            'slug' => 'doomed-manager-slug-888',
        ]);

        $detailUrl = route('helpful_information.show_interesting_news', ['slug' => 'doomed-manager-slug-888']);

        $this->get(route('interesting_articles.index'))->assertSee('Doomed Manager Marker 888');
        $this->get($detailUrl)->assertOk();

        $response = $this->actingAs($manager)->delete(route('cabinet.manager.articles.delete', $article));
        $response->assertRedirect();

        $this->get(route('interesting_articles.index'))->assertDontSee('Doomed Manager Marker 888');
        $this->get($detailUrl)->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // 11. Soft-delete scope remains intact
    // -----------------------------------------------------------------------

    public function test_soft_deleted_article_is_not_returned_by_public_index_or_detail(): void
    {
        $article = $this->createArticle([
            'title' => 'Soft Deleted Marker 999',
            'content' => 'Soft deleted content 999',
            'slug' => 'soft-deleted-slug-999',
        ]);

        $article->delete();

        $this->get(route('interesting_articles.index'))->assertDontSee('Soft Deleted Marker 999');
        $this->get(route('helpful_information.show_interesting_news', ['slug' => 'soft-deleted-slug-999']))
            ->assertNotFound();
    }

    // -----------------------------------------------------------------------
    // 12. Old controller-level cache keys are no longer written
    // -----------------------------------------------------------------------

    public function test_public_article_requests_do_not_create_the_old_controller_cache_keys(): void
    {
        $this->createArticle([
            'title' => 'Cache Key Marker 1010',
            'content' => 'Cache key content 1010',
            'slug' => 'cache-key-slug-1010',
        ]);

        $this->get(route('interesting_articles.index'))->assertOk();
        $this->get(route('helpful_information.show_interesting_news', ['slug' => 'cache-key-slug-1010']))->assertOk();

        $this->assertFalse(Cache::has('interesting_news_page_1'));
        $this->assertFalse(Cache::has('interesting_news_cache-key-slug-1010'));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function createArticle(array $attributes = []): Article
    {
        return Article::create(array_merge([
            'title' => 'Default Title',
            'slug' => 'default-slug-' . uniqid(),
            'content' => 'Default content',
            'image' => null,
        ], $attributes));
    }
}
