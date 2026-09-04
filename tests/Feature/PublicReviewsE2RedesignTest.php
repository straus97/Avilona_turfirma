<?php

namespace Tests\Feature;

use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * E2-A6-I1: миграция публичной страницы «Отзывы» на систему E2.
 *
 * Проверяет только визуально-структурный слой редизайна: единственный <h1>,
 * хлебные крошки, hero, отсутствие легаси includes.sidebar. Поведенческие
 * контракты Stage 13 (модерация, согласия, приватность, экранирование,
 * UX ошибок валидации) закреплены в существующих Public*Review* тестах и
 * здесь намеренно не дублируются.
 */
class PublicReviewsE2RedesignTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Validator::extend('captcha', function () {
            return true;
        });
    }

    public function test_reviews_page_has_exactly_one_h1(): void
    {
        Reviews::create([
            'name' => 'One H1 Author',
            'title' => null,
            'content' => 'ONE-H1-CONTENT-MARKER ' . str_repeat('a', 60),
            'image' => null,
            'is_published' => true,
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $this->assertSame(1, substr_count($response->getContent(), '<h1'));
    }

    public function test_reviews_page_uses_e2_breadcrumb_and_hero(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('class="e2-breadcrumb"', false);
        $response->assertSee('class="e2-page-hero"', false);
        $response->assertSeeInOrder(['Главная', 'Отзывы'], false);
    }

    public function test_reviews_view_no_longer_includes_legacy_sidebar(): void
    {
        $source = file_get_contents(resource_path('views/reviews.blade.php'));

        $this->assertStringNotContainsString("@include('includes.sidebar')", $source);
        $this->assertStringNotContainsString('col-md-10', $source);
    }

    public function test_reviews_page_does_not_render_legacy_bootstrap_modal_scripts(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertDontSee("modal('show')", false);
        $response->assertDontSee('id="successModal"', false);
        $response->assertDontSee('id="errorModal"', false);
    }

    public function test_reviews_empty_state_is_intentional(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('Пока нет опубликованных отзывов');
        // Форма отправки отзыва по-прежнему на странице.
        $response->assertSee('id="review-form"', false);
    }

    public function test_reviews_create_form_keeps_three_ordered_consent_checkboxes_and_moderation_notice(): void
    {
        $response = $this->get(route('review.index'));
        $html = $response->getContent();

        $response->assertOk();
        $response->assertSee('Отзыв сначала поступит на модерацию и не публикуется автоматически.');

        $order = [
            'name="user_agreement_accepted"',
            'name="personal_data_consent_accepted"',
            'name="review_publication_consent_accepted"',
        ];
        $positions = [];
        foreach ($order as $needle) {
            $this->assertSame(1, substr_count($html, $needle), "{$needle} must appear exactly once");
            $positions[] = strpos($html, $needle);
        }
        $this->assertLessThan($positions[1], $positions[0]);
        $this->assertLessThan($positions[2], $positions[1]);
    }

    // -----------------------------------------------------------------------
    // Neutral avatar fallback (browser-QA polish)
    // -----------------------------------------------------------------------

    public function test_review_without_image_renders_neutral_avatar_placeholder(): void
    {
        Reviews::create([
            'name' => 'No Image Author',
            'title' => null,
            'content' => 'NO-IMAGE-AVATAR-MARKER ' . str_repeat('a', 60),
            'image' => null,
            'is_published' => true,
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('e2-review__avatar--placeholder', false);
        $response->assertSee('bi-person-fill', false);
        // Decorative-only: no alt text / accessible name invented for the fallback.
        $response->assertDontSee('alt="Фото клиента No Image Author"', false);
    }

    public function test_review_with_image_still_renders_the_real_photo_not_the_placeholder(): void
    {
        Reviews::create([
            'name' => 'Has Image Author',
            'title' => null,
            'content' => 'HAS-IMAGE-AVATAR-MARKER ' . str_repeat('b', 60),
            'image' => 'img/reviews/marker-photo.jpg',
            'is_published' => true,
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('img/reviews/marker-photo.jpg', false);
        $response->assertSee('alt="Фото клиента Has Image Author"', false);
    }

    // -----------------------------------------------------------------------
    // Compact desktop grid (browser-QA polish)
    // -----------------------------------------------------------------------

    public function test_review_card_groups_reviewer_name_and_date_in_one_identity_cluster(): void
    {
        Reviews::create([
            'name' => 'Identity Cluster Author',
            'title' => null,
            'content' => 'IDENTITY-CLUSTER-MARKER ' . str_repeat('c', 60),
            'image' => null,
            'is_published' => true,
            'created_at' => '2024-05-01 12:00:00',
        ]);

        $response = $this->get(route('review.index'));
        $html = $response->getContent();

        $response->assertOk();

        $identityStart = strpos($html, 'e2-review__identity');
        $this->assertNotFalse($identityStart, 'Expected an e2-review__identity cluster in the markup');

        // The name (<h3 class="e2-card__title">) and the date
        // (<p class="e2-review__date">) for this review must both live
        // inside the same identity cluster, not float apart in the card.
        $clusterEnd = strpos($html, '</div>', $identityStart);
        $cluster = substr($html, $identityStart, $clusterEnd - $identityStart);

        $this->assertStringContainsString('Identity Cluster Author', $cluster);
        $this->assertStringContainsString('e2-review__date', $cluster);
        $this->assertStringContainsString('1 мая 2024', $cluster);
    }

    public function test_review_list_defines_a_two_column_desktop_grid_without_forcing_equal_height(): void
    {
        $css = file_get_contents(public_path('css/unified.css'));

        $this->assertMatchesRegularExpression(
            '/@media \(min-width: 992px\)\s*\{\s*\.e2-review-list\s*\{[^}]*grid-template-columns:\s*repeat\(2,[^}]*align-items:\s*start;/s',
            $css,
            'Expected a >= 992px two-column .e2-review-list grid with align-items: start (so a short card is not stretched to match a taller neighbour)'
        );
    }
}
