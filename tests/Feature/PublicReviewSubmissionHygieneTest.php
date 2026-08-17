<?php

namespace Tests\Feature;

use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Stage 13 C3: Reviews public-form hygiene slice.
 *
 * Проверяет, что публичная форма отзывов больше не собирает legacy-поля
 * (subject/"Тема", generic email, legacy чекбокс agree), что вместо них
 * присутствуют новые поля consent_full_name/consent_email и ровно три
 * отдельных обязательных чекбокса согласия (accepted-семантика) в
 * утверждённом порядке, что заголовок отзыва (title) больше не выводится
 * публично, и что уведомление/текст об успехе про модерацию сохранены.
 *
 * Секция G дополнительно закрывает UX-слой ошибок валидации: возврат
 * посетителя к форме, сводное сообщение об ошибке и фокус на первом
 * невалидном поле — без перевода формы на AJAX.
 */
class PublicReviewSubmissionHygieneTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // Captcha rule is unrelated to this slice; stubbed the same way the
        // existing review/contact feature tests do it, instead of bypassing
        // unrelated FormRequest validation.
        Validator::extend('captcha', function () {
            return true;
        });
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Submitting Tourist',
            'consent_full_name' => 'Submitting Tourist Full Name',
            'consent_email' => 'tourist@example.com',
            'message' => 'HYGIENE-REVIEW-MARKER ' . str_repeat('x', 60),
            'publication_conditions' => '',
            'captcha' => 'anything',
            'user_agreement_accepted' => '1',
            'personal_data_consent_accepted' => '1',
            'review_publication_consent_accepted' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // A. Legacy fields are gone; new fields and moderation notice are present
    // -----------------------------------------------------------------------

    public function test_review_form_does_not_contain_legacy_fields_and_keeps_new_fields_and_notice(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertDontSee('name="email"', false);
        $response->assertDontSee('name="subject"', false);
        $response->assertDontSee("old('subject')", false);
        $response->assertDontSee('name="agree"', false);

        $response->assertSee('name="name"', false);
        $response->assertSee('name="consent_full_name"', false);
        $response->assertSee('name="consent_email"', false);
        $response->assertSee('name="message"', false);
        $response->assertSee('name="publication_conditions"', false);
        $response->assertSee('name="captcha"', false);

        $response->assertSee('Отзыв сначала поступит на модерацию и не публикуется автоматически.');
    }

    public function test_review_form_private_consent_fields_are_described_as_non_public(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('не публикуются вместе с отзывом', false);
    }

    // -----------------------------------------------------------------------
    // B. Exactly three required confirmations, in the approved order
    // -----------------------------------------------------------------------

    public function test_review_form_contains_exactly_three_required_confirmations_in_order(): void
    {
        $response = $this->get(route('review.index'));
        $html = $response->getContent();

        $fields = [
            'user_agreement_accepted',
            'personal_data_consent_accepted',
            'review_publication_consent_accepted',
        ];

        $positions = [];
        foreach ($fields as $field) {
            $this->assertSame(1, substr_count($html, 'name="' . $field . '"'), "Field {$field} must appear exactly once");
            $positions[] = strpos($html, 'name="' . $field . '"');
        }

        $this->assertTrue($positions === $positions, 'sanity');
        $this->assertLessThan($positions[1], $positions[0], 'user_agreement_accepted must come before personal_data_consent_accepted');
        $this->assertLessThan($positions[2], $positions[1], 'personal_data_consent_accepted must come before review_publication_consent_accepted');
    }

    public function test_review_form_publication_consent_checkbox_has_approved_wording_and_links(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertSee('Я даю согласие на публикацию моего отзыва и указанного мной имени на avilona.ru после модерации.');
        $response->assertSee(route('review_personal_data_consent.info'), false);
        $response->assertSee(route('review_publication_consent.info'), false);
    }

    // -----------------------------------------------------------------------
    // C. Public title rendering is removed
    // -----------------------------------------------------------------------

    public function test_public_reviews_page_does_not_render_review_title(): void
    {
        Reviews::create([
            'name' => 'Titled Review Author',
            'title' => 'HYGIENE-TITLE-MARKER',
            'content' => 'HYGIENE-TITLE-CONTENT-MARKER ' . str_repeat('y', 60),
            'image' => null,
            'is_published' => true,
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('HYGIENE-TITLE-CONTENT-MARKER', false);
        $response->assertDontSee('HYGIENE-TITLE-MARKER', false);
    }

    // -----------------------------------------------------------------------
    // D. CreateRequest no longer contains legacy rules and uses accepted semantics
    // -----------------------------------------------------------------------

    public function test_create_request_source_has_no_legacy_rules_and_uses_accepted_semantics(): void
    {
        $source = file_get_contents(app_path('Http/Requests/Review/CreateRequest.php'));

        $this->assertStringNotContainsString("'subject'", $source);
        $this->assertStringNotContainsString("'agree'", $source);
        $this->assertStringNotContainsString("'email' =>", $source);

        $this->assertMatchesRegularExpression("/'user_agreement_accepted'\\s*=>\\s*'accepted'/", $source);
        $this->assertMatchesRegularExpression("/'personal_data_consent_accepted'\\s*=>\\s*'accepted'/", $source);
        $this->assertMatchesRegularExpression("/'review_publication_consent_accepted'\\s*=>\\s*'accepted'/", $source);
    }

    // -----------------------------------------------------------------------
    // E. Successful submission remains unpublished and no premature-publication wording
    // -----------------------------------------------------------------------

    public function test_review_submission_succeeds_and_remains_unpublished_without_auto_publication_claim(): void
    {
        $response = $this->post(route('review_create.index'), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('review.index'));

        $review = Reviews::where('content', 'like', 'HYGIENE-REVIEW-MARKER%')->first();
        $this->assertNotNull($review);
        $this->assertFalse((bool) $review->is_published);

        $follow = $this->get(route('review.index'));
        $follow->assertDontSee('автоматически публикуется', false);
    }

    // -----------------------------------------------------------------------
    // F. Each of the three confirmations must be accepted, not merely present
    // -----------------------------------------------------------------------

    public function test_unaccepted_confirmation_values_are_rejected_and_no_review_is_persisted(): void
    {
        foreach (['user_agreement_accepted', 'personal_data_consent_accepted', 'review_publication_consent_accepted'] as $field) {
            $marker = 'HYGIENE-UNACCEPTED-' . strtoupper($field) . '-MARKER';

            $response = $this->post(route('review_create.index'), $this->validPayload([
                $field => '0',
                'message' => $marker . ' ' . str_repeat('z', 60),
            ]));

            $response->assertSessionHasErrors($field);
            $this->assertNull(Reviews::where('content', 'like', $marker . '%')->first());
        }
    }

    // -----------------------------------------------------------------------
    // G. Validation-error UX: return to form, error summary, focus first field
    // -----------------------------------------------------------------------

    private const ERROR_SUMMARY = 'Отзыв не отправлен. Проверьте выделенные поля и попробуйте ещё раз.';

    public function test_review_form_exposes_exactly_one_review_form_anchor_id(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $this->assertSame(
            1,
            substr_count($response->getContent(), 'id="review-form"'),
            'The public review form must expose exactly one id="review-form" anchor'
        );
    }

    public function test_clean_review_page_get_renders_form_without_error_summary_or_auto_scroll(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        // Форма отрисована как обычно.
        $response->assertSee('id="review-form"', false);
        $response->assertSee('name="message"', false);
        $response->assertSee('name="consent_email"', false);

        // Ни сводки ошибок, ни автоскролла на чистом GET быть не должно.
        $response->assertDontSee(self::ERROR_SUMMARY);
        $response->assertDontSee('scrollIntoView', false);
    }

    public function test_invalid_review_submission_returns_to_form_with_error_summary_and_field_feedback(): void
    {
        $payload = $this->validPayload(['consent_email' => '']);

        $direct = $this->from(route('review.index'))->post(route('review_create.index'), $payload);
        $direct->assertSessionHasErrors('consent_email');
        $direct->assertRedirect(route('review.index'));

        $response = $this->from(route('review.index'))->followingRedirects()->post(route('review_create.index'), $payload);

        $response->assertOk();
        // 1. Посетитель снова видит форму отзывов.
        $response->assertSee('id="review-form"', false);
        // 2. Сводное сообщение об ошибке.
        $response->assertSee(self::ERROR_SUMMARY);
        // 3. Существующая полевая обратная связь сохранена.
        $response->assertSee('Пожалуйста, укажите корректный email');
        // 4. old(...) значения по-прежнему подставляются.
        $response->assertSee('value="Submitting Tourist"', false);
        // 5. Возврат к форме и фокус на первом невалидном поле.
        $response->assertSee('scrollIntoView', false);
        $this->assertMatchesRegularExpression(
            '/firstErrorField\s*=\s*"consent_email"/',
            $response->getContent(),
            'The first server-side validation error key must be handed to JS via Blade JSON encoding'
        );

        // Сводка не должна перечислять введённые персональные данные
        // (сами поля при этом штатно восстанавливаются через old(...)).
        $this->assertSame(
            1,
            preg_match_all('/<div class="alert alert-danger" role="alert">(.*?)<\/div>/s', $response->getContent(), $matches),
            'The validation error summary alert must be rendered exactly once'
        );
        $this->assertStringNotContainsString('Submitting Tourist Full Name', $matches[1][0]);
        $this->assertStringNotContainsString('consent_full_name', $matches[1][0]);
        $this->assertStringNotContainsString('consent_email', $matches[1][0]);
    }

    public function test_review_blade_implements_validation_error_ux_without_ajax(): void
    {
        $source = file_get_contents(resource_path('views/reviews.blade.php'));

        // Условное поведение только при ошибках валидации.
        $this->assertStringContainsString('$errors->any()', $source);
        $this->assertStringContainsString('id="review-form"', $source);
        $this->assertStringContainsString("getElementById('review-form')", $source);
        $this->assertStringContainsString('scrollIntoView', $source);
        // Первый ключ ошибки + безопасный поиск поля через коллекцию формы.
        $this->assertStringContainsString('$errors->keys()[0]', $source);
        $this->assertStringContainsString('form.elements.namedItem(', $source);

        // Форма остаётся обычным POST -> redirect-back, без AJAX.
        $this->assertStringNotContainsString('fetch(', $source);
        $this->assertStringNotContainsString('XMLHttpRequest', $source);
        $this->assertStringNotContainsString('axios', $source);
        $this->assertSame(
            1,
            substr_count($source, '$.ajax('),
            'Only the pre-existing captcha refresh may use jQuery AJAX; the review form must not be submitted asynchronously'
        );
        $this->assertStringContainsString('route(\'captcha_reload.index\')', $source);
    }
}
