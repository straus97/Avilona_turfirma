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
}
