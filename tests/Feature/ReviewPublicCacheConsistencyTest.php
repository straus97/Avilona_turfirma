<?php

namespace Tests\Feature;

use App\Models\Reviews;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Правило: публичные точки отзывов (home.index — тизер отзывов на главной,
 * review.index — список отзывов) больше не используют ни middleware
 * cache.response, ни контроллерный Cache::remember для отзывов
 * (reviews_page_*, home_reviews). Review\CreateController больше не вызывает
 * обречённый Cache::tags(['reviews'])->flush() (untagged-кэш реагировать на
 * него всё равно не мог). Модерация admin/manager должна быть видна
 * немедленно на обеих публичных точках, даже если они уже были запрошены.
 */
class ReviewPublicCacheConsistencyTest extends TestCase
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

    public function test_public_review_routes_do_not_use_response_cache_middleware(): void
    {
        $homeRoute = Route::getRoutes()->getByName('home.index');
        $reviewIndexRoute = Route::getRoutes()->getByName('review.index');

        $this->assertNotNull($homeRoute);
        $this->assertNotNull($reviewIndexRoute);

        $this->assertNotContains('cache.response', $homeRoute->gatherMiddleware());
        $this->assertNotContains('cache.response', $reviewIndexRoute->gatherMiddleware());

        $this->assertSame('/', $homeRoute->uri());
        $this->assertContains('GET', $homeRoute->methods());
        $this->assertContains('HEAD', $homeRoute->methods());

        $this->assertSame('reviews', $reviewIndexRoute->uri());
        $this->assertContains('GET', $reviewIndexRoute->methods());
        $this->assertContains('HEAD', $reviewIndexRoute->methods());

        $createRoute = Route::getRoutes()->getByName('review_create.index');
        $this->assertNotNull($createRoute);
        $this->assertSame('reviews/create', $createRoute->uri());
        $this->assertContains('POST', $createRoute->methods());
        $this->assertNotContains('GET', $createRoute->methods());
    }

    // -----------------------------------------------------------------------
    // 2. Static source regression
    // -----------------------------------------------------------------------

    public function test_public_review_controllers_do_not_use_review_cache_layers(): void
    {
        $reviewIndexController = file_get_contents(app_path('Http/Controllers/Review/IndexController.php'));
        $homeController = file_get_contents(app_path('Http/Controllers/Home/IndexController.php'));
        $createController = file_get_contents(app_path('Http/Controllers/Review/CreateController.php'));

        $this->assertStringNotContainsString('Cache::remember', $reviewIndexController);
        $this->assertStringNotContainsString('Illuminate\Support\Facades\Cache', $reviewIndexController);

        $this->assertStringNotContainsString("Cache::remember('home_reviews'", $homeController);
        $this->assertStringNotContainsString("Cache::remember('home_news'", $homeController);
        $this->assertStringContainsString("Cache::remember('home_best_offers'", $homeController);
        $this->assertStringContainsString("Cache::remember('home_partners'", $homeController);

        $this->assertStringNotContainsString('Cache::tags', $createController);
        $this->assertStringNotContainsString('Illuminate\Support\Facades\Cache', $createController);

        $routesContent = file_get_contents(base_path('routes/web.php'));

        foreach (['home.index', 'review.index'] as $name) {
            preg_match('/Route::get\([^;]*?"' . preg_quote($name, '/') . '"[^;]*?\);/s', $routesContent, $matches);
            $this->assertNotEmpty($matches, "Route definition for {$name} was not found");
            $this->assertStringNotContainsString('cache.response', $matches[0]);
        }
    }

    // -----------------------------------------------------------------------
    // 3, 4. Public surfaces show only published reviews
    // -----------------------------------------------------------------------

    public function test_public_review_index_shows_only_published_reviews(): void
    {
        $this->makeReview('Published Author', true, ['content' => 'PUBLISHED-INDEX-MARKER-111']);
        $this->makeReview('Unpublished Author', false, ['content' => 'UNPUBLISHED-INDEX-MARKER-111']);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('PUBLISHED-INDEX-MARKER-111');
        $response->assertDontSee('UNPUBLISHED-INDEX-MARKER-111');
    }

    public function test_homepage_review_teaser_shows_only_published_reviews(): void
    {
        $this->makeReview('Published Author', true, ['content' => 'PUBLISHED-HOME-MARKER-222']);
        $this->makeReview('Unpublished Author', false, ['content' => 'UNPUBLISHED-HOME-MARKER-222']);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('PUBLISHED-HOME-MARKER-222');
        $response->assertDontSee('UNPUBLISHED-HOME-MARKER-222');
    }

    // -----------------------------------------------------------------------
    // 5, 6. Immediate visibility after publish
    // -----------------------------------------------------------------------

    public function test_admin_publish_is_visible_immediately_after_public_pages_were_requested(): void
    {
        $review = $this->makeReview('Pending Author', false, ['content' => 'PENDING-ADMIN-PUBLISH-333']);

        $this->get(route('review.index'))->assertDontSee('PENDING-ADMIN-PUBLISH-333');
        $this->get(route('home.index'))->assertDontSee('PENDING-ADMIN-PUBLISH-333');

        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => 'Pending Author',
            'title' => 'Pending Author Review',
            'content' => 'PENDING-ADMIN-PUBLISH-333',
            'gender' => 'boy',
            'is_published' => '1',
        ]);
        $response->assertRedirect(route('cabinet.admin.content'));

        $this->get(route('review.index'))->assertSee('PENDING-ADMIN-PUBLISH-333');
        $this->get(route('home.index'))->assertSee('PENDING-ADMIN-PUBLISH-333');
    }

    public function test_manager_publish_is_visible_immediately_after_public_pages_were_requested(): void
    {
        $review = $this->makeReview('Pending Author', false, ['content' => 'PENDING-MANAGER-PUBLISH-444']);

        $this->get(route('review.index'))->assertDontSee('PENDING-MANAGER-PUBLISH-444');
        $this->get(route('home.index'))->assertDontSee('PENDING-MANAGER-PUBLISH-444');

        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => 'Pending Author',
            'title' => 'Pending Author Review',
            'content' => 'PENDING-MANAGER-PUBLISH-444',
            'gender' => 'boy',
            'is_published' => '1',
        ]);
        $response->assertRedirect(route('cabinet.manager.content'));

        $this->get(route('review.index'))->assertSee('PENDING-MANAGER-PUBLISH-444');
        $this->get(route('home.index'))->assertSee('PENDING-MANAGER-PUBLISH-444');
    }

    // -----------------------------------------------------------------------
    // 7, 8. Immediate removal after unpublish
    // -----------------------------------------------------------------------

    public function test_admin_unpublish_disappears_immediately_after_public_pages_were_requested(): void
    {
        $review = $this->makeReview('Published Author', true, ['content' => 'TO-UNPUBLISH-ADMIN-555']);

        $this->get(route('review.index'))->assertSee('TO-UNPUBLISH-ADMIN-555');
        $this->get(route('home.index'))->assertSee('TO-UNPUBLISH-ADMIN-555');

        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => 'Published Author',
            'title' => 'Published Author Review',
            'content' => 'TO-UNPUBLISH-ADMIN-555',
            'gender' => 'boy',
            // is_published intentionally omitted so $request->boolean('is_published') resolves to false.
        ]);
        $response->assertRedirect(route('cabinet.admin.content'));

        $this->get(route('review.index'))->assertDontSee('TO-UNPUBLISH-ADMIN-555');
        $this->get(route('home.index'))->assertDontSee('TO-UNPUBLISH-ADMIN-555');
    }

    public function test_manager_unpublish_disappears_immediately_after_public_pages_were_requested(): void
    {
        $review = $this->makeReview('Published Author', true, ['content' => 'TO-UNPUBLISH-MANAGER-666']);

        $this->get(route('review.index'))->assertSee('TO-UNPUBLISH-MANAGER-666');
        $this->get(route('home.index'))->assertSee('TO-UNPUBLISH-MANAGER-666');

        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => 'Published Author',
            'title' => 'Published Author Review',
            'content' => 'TO-UNPUBLISH-MANAGER-666',
            'gender' => 'boy',
            // is_published intentionally omitted so $request->boolean('is_published') resolves to false.
        ]);
        $response->assertRedirect(route('cabinet.manager.content'));

        $this->get(route('review.index'))->assertDontSee('TO-UNPUBLISH-MANAGER-666');
        $this->get(route('home.index'))->assertDontSee('TO-UNPUBLISH-MANAGER-666');
    }

    // -----------------------------------------------------------------------
    // 9, 10. Legacy review cache keys are dead
    // -----------------------------------------------------------------------

    public function test_public_review_pages_ignore_legacy_cache_values(): void
    {
        $this->makeReview('Current Author', true, ['content' => 'CURRENT-DB-MARKER-777']);

        Cache::put('reviews_page_1', 'stale-reviews-page-value-777', now()->addMinutes(60));
        Cache::put('home_reviews', 'stale-home-reviews-value-777', now()->addMinutes(60));

        $indexResponse = $this->get(route('review.index'));
        $indexResponse->assertOk();
        $indexResponse->assertSee('CURRENT-DB-MARKER-777');
        $indexResponse->assertDontSee('stale-reviews-page-value-777');

        $homeResponse = $this->get(route('home.index'));
        $homeResponse->assertOk();
        $homeResponse->assertSee('CURRENT-DB-MARKER-777');
        $homeResponse->assertDontSee('stale-home-reviews-value-777');
    }

    public function test_public_review_requests_do_not_create_old_review_cache_keys(): void
    {
        $this->assertFalse(Cache::has('reviews_page_1'));
        $this->assertFalse(Cache::has('home_reviews'));

        $this->makeReview('No Legacy Key Author', true, ['content' => 'NO-LEGACY-KEY-MARKER-888']);

        $this->get(route('review.index'))->assertOk();
        $this->get(route('home.index'))->assertOk();

        $this->assertFalse(Cache::has('reviews_page_1'));
        $this->assertFalse(Cache::has('home_reviews'));
    }

    // -----------------------------------------------------------------------
    // 11. Soft-delete scope remains intact
    // -----------------------------------------------------------------------

    public function test_soft_deleted_review_is_not_returned_by_review_index_or_homepage(): void
    {
        $review = $this->makeReview('Soft Deleted Author', true, ['content' => 'SOFT-DELETED-MARKER-999']);
        $review->delete();

        $this->get(route('review.index'))->assertDontSee('SOFT-DELETED-MARKER-999');
        $this->get(route('home.index'))->assertDontSee('SOFT-DELETED-MARKER-999');
    }

    // -----------------------------------------------------------------------
    // 12. Public submission survives removal of the tagged cache flush
    // -----------------------------------------------------------------------

    public function test_public_review_submission_succeeds_without_tagged_cache_support_and_remains_unpublished(): void
    {
        // The array cache store used in tests has no tag support; the captcha
        // rule itself is unrelated to this slice, so it is deterministically
        // stubbed here rather than disabling FormRequest validation.
        Validator::extend('captcha', function () {
            return true;
        });

        $response = $this->post(route('review_create.index'), [
            'name' => 'Submitting Tourist',
            'email' => 'tourist@example.com',
            'subject' => 'Trip feedback',
            'message' => 'SUBMITTED-REVIEW-MARKER-1010 ' . str_repeat('x', 60),
            'captcha' => 'anything',
            'agree' => '1',
        ]);

        $response->assertRedirect(route('review.index'));
        $response->assertSessionHasNoErrors();

        $review = Reviews::where('content', 'like', 'SUBMITTED-REVIEW-MARKER-1010%')->first();
        $this->assertNotNull($review);
        $this->assertFalse((bool) $review->is_published);

        $this->get(route('review.index'))->assertDontSee('SUBMITTED-REVIEW-MARKER-1010');
        $this->get(route('home.index'))->assertDontSee('SUBMITTED-REVIEW-MARKER-1010');
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

    private function makeReview(string $name, bool $isPublished, array $overrides = []): Reviews
    {
        return Reviews::create(array_merge([
            'name' => $name,
            'title' => null,
            'content' => 'Content for ' . $name,
            'image' => null,
            'is_published' => $isPublished,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides));
    }
}
