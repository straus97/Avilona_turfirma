<?php

namespace Tests\Feature;

use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Stage 13: Reviews submission-hygiene slice.
 *
 * Проверяет, что публичная форма отзывов больше не собирает email (он не
 * хранится и не используется контроллером), что валидация больше не требует
 * email, что чекбокс `agree` теперь проверяется как `accepted` (а не просто
 * "поле присутствует"), и что на форме есть информационное уведомление о
 * модерации/публикации.
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
            'subject' => 'Trip feedback',
            'message' => 'HYGIENE-REVIEW-MARKER ' . str_repeat('x', 60),
            'captcha' => 'anything',
            'agree' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // A. Form no longer collects email
    // -----------------------------------------------------------------------

    public function test_review_form_does_not_contain_email_field_but_keeps_name_and_notice(): void
    {
        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertDontSee('name="email"', false);
        $response->assertSee('name="name"', false);
        $response->assertSee('Отзыв сначала поступит на модерацию и не публикуется автоматически.');
    }

    // -----------------------------------------------------------------------
    // B. Email is no longer required
    // -----------------------------------------------------------------------

    public function test_review_submission_without_email_is_not_rejected_for_email(): void
    {
        $response = $this->post(route('review_create.index'), $this->validPayload());

        $response->assertSessionDoesntHaveErrors('email');
        $response->assertRedirect(route('review.index'));
        $response->assertSessionHasNoErrors();

        $review = Reviews::where('content', 'like', 'HYGIENE-REVIEW-MARKER%')->first();
        $this->assertNotNull($review);
        $this->assertFalse((bool) $review->is_published);
    }

    // -----------------------------------------------------------------------
    // C. agree must be accepted, not merely present
    // -----------------------------------------------------------------------

    public function test_agree_zero_is_rejected_and_no_review_is_persisted(): void
    {
        $response = $this->post(route('review_create.index'), $this->validPayload([
            'agree' => '0',
            'message' => 'AGREE-ZERO-MARKER ' . str_repeat('x', 60),
        ]));

        $response->assertSessionHasErrors('agree');

        $this->assertNull(Reviews::where('content', 'like', 'AGREE-ZERO-MARKER%')->first());
    }

    // -----------------------------------------------------------------------
    // D. Valid accepted value
    // -----------------------------------------------------------------------

    public function test_agree_one_passes_validation_and_creates_unpublished_review(): void
    {
        $response = $this->post(route('review_create.index'), $this->validPayload([
            'agree' => '1',
            'message' => 'AGREE-ONE-MARKER ' . str_repeat('x', 60),
        ]));

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('review.index'));

        $review = Reviews::where('content', 'like', 'AGREE-ONE-MARKER%')->first();
        $this->assertNotNull($review);
        $this->assertFalse((bool) $review->is_published);
    }
}
