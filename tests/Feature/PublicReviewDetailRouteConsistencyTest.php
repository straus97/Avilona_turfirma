<?php

namespace Tests\Feature;

use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * Публичный маршрут детальной страницы отзыва (reviews.show →
 * App\Http\Controllers\Home\ReviewsController) был неполным и небезопасным:
 * рендерил отсутствующий Blade-вид, не был доступен из публичного списка,
 * не имел тестов и не учитывал is_published. Маршрут и контроллер удалены;
 * этот тест фиксирует, что удалённая поверхность больше не зарегистрирована
 * и что остальная публичная/модераторская функциональность отзывов цела.
 */
class PublicReviewDetailRouteConsistencyTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Response-кэш маршрутов (middleware cache.response) кэширует тело по
        // полному URL. Чистим кэш перед каждым тестом для детерминизма.
        Cache::flush();
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

    /** 1. Удалённый маршрут детали отзыва больше не зарегистрирован. */
    public function test_unsupported_public_review_detail_route_is_not_registered(): void
    {
        $this->assertFalse(Route::has('reviews.show'));

        foreach (Route::getRoutes() as $route) {
            $uri = $route->uri();
            $methods = $route->methods();

            if ($uri === 'reviews/{id_reviews}' && (in_array('GET', $methods, true) || in_array('HEAD', $methods, true))) {
                $this->fail('A GET/HEAD route with URI reviews/{id_reviews} must not exist.');
            }

            $action = $route->getActionName();
            $this->assertStringNotContainsString(
                'App\Http\Controllers\Home\ReviewsController',
                $action,
                'No route action may reference the removed Home\ReviewsController'
            );
        }
    }

    /** 2. Опубликованный отзыв недоступен по удалённому URL детали. */
    public function test_published_review_is_not_accessible_through_removed_detail_url(): void
    {
        $review = $this->makeReview('Published Author', true);

        $this->get('/reviews/' . $review->id)->assertNotFound();
    }

    /** 3. Неопубликованный отзыв недоступен по удалённому URL детали. */
    public function test_unpublished_review_is_not_accessible_through_removed_detail_url(): void
    {
        $review = $this->makeReview('Unpublished Author', false);

        $this->get('/reviews/' . $review->id)->assertNotFound();
    }

    /** 4. Мягко удалённый отзыв недоступен по удалённому URL детали. */
    public function test_soft_deleted_review_is_not_accessible_through_removed_detail_url(): void
    {
        $review = $this->makeReview('Trashed Author', true);
        $review->delete();
        $this->assertSoftDeleted('reviews', ['id' => $review->id]);

        $this->get('/reviews/' . $review->id)->assertNotFound();
    }

    /** 5. Публичный список отзывов доступен и фильтрует неопубликованные. */
    public function test_public_reviews_index_remains_available_and_filters_unpublished_reviews(): void
    {
        $this->makeReview('Published Marker Author', true, ['content' => 'PUBLISHED-MARKER-TEXT']);
        $this->makeReview('Unpublished Marker Author', false, ['content' => 'UNPUBLISHED-MARKER-TEXT']);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('PUBLISHED-MARKER-TEXT');
        $response->assertDontSee('UNPUBLISHED-MARKER-TEXT');
    }

    /** 6. Маршрут отправки отзыва по-прежнему зарегистрирован (без отправки формы). */
    public function test_review_submission_route_remains_registered(): void
    {
        $this->assertTrue(Route::has('review_create.index'));

        $route = Route::getRoutes()->getByName('review_create.index');

        $this->assertNotNull($route);
        $this->assertSame('reviews/create', $route->uri());
        $this->assertContains('POST', $route->methods());
    }

    /** 7. Маршруты модерации отзывов менеджером и админом остаются зарегистрированы. */
    public function test_manager_and_admin_review_moderation_routes_remain_registered(): void
    {
        $expectations = [
            'cabinet.manager.reviews.edit' => 'GET',
            'cabinet.manager.reviews.update' => 'PUT',
            'cabinet.admin.reviews.edit' => 'GET',
            'cabinet.admin.reviews.update' => 'PUT',
        ];

        foreach ($expectations as $name => $method) {
            $this->assertTrue(Route::has($name), "Route [{$name}] must remain registered");

            $route = Route::getRoutes()->getByName($name);
            $this->assertNotNull($route);
            $this->assertContains($method, $route->methods(), "Route [{$name}] must accept {$method}");
        }
    }

    /** 8. Удалённый маршрут и контроллер не переиспользуются в исходниках. */
    public function test_removed_route_and_controller_do_not_reappear_in_source(): void
    {
        $routesSource = file_get_contents(base_path('routes/web.php'));

        $this->assertStringNotContainsString('/reviews/{id_reviews}', $routesSource);
        $this->assertStringNotContainsString('->name("reviews.show")', $routesSource);
        $this->assertStringNotContainsString("->name('reviews.show')", $routesSource);

        $this->assertFileDoesNotExist(base_path('app/Http/Controllers/Home/ReviewsController.php'));
    }
}
