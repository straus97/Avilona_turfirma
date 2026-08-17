<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stage 13: Reviews moderation schema-foundation slice (C4A).
 *
 * Проверяет только новый database/model контракт, необходимый будущей C4
 * moderation-логике: reviews.is_moderator_edited, reviews.moderator_edited_at,
 * и обратную связь Reviews -> ReviewConsent. Не проверяет модерацию,
 * server-side gate, публичное раскрытие изменений модератора или Admin/Manager
 * поведение — они появятся в C4B/C4C.
 */
class ReviewModerationSchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // A. reviews schema
    // -----------------------------------------------------------------------

    public function test_reviews_table_has_moderation_state_columns(): void
    {
        $this->assertTrue(Schema::hasColumns('reviews', [
            'is_moderator_edited',
            'moderator_edited_at',
        ]));
    }

    public function test_is_moderator_edited_defaults_to_false_for_new_rows(): void
    {
        $review = $this->makeReview();

        $this->assertFalse((bool) $review->is_moderator_edited);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'is_moderator_edited' => 0,
        ]);
    }

    public function test_moderator_edited_at_is_nullable_and_null_by_default(): void
    {
        $review = $this->makeReview();

        $this->assertNull($review->moderator_edited_at);

        $this->assertDatabaseHas('reviews', [
            'id' => $review->id,
            'moderator_edited_at' => null,
        ]);
    }

    // -----------------------------------------------------------------------
    // B. Model casts
    // -----------------------------------------------------------------------

    public function test_is_moderator_edited_is_cast_to_boolean(): void
    {
        $review = $this->makeReview();
        $review->refresh();

        $this->assertIsBool($review->is_moderator_edited);
        $this->assertFalse($review->is_moderator_edited);

        $review->is_moderator_edited = true;
        $review->save();
        $review->refresh();

        $this->assertIsBool($review->is_moderator_edited);
        $this->assertTrue($review->is_moderator_edited);
    }

    public function test_moderator_edited_at_is_cast_to_datetime(): void
    {
        $review = $this->makeReview();

        $review->moderator_edited_at = now();
        $review->save();
        $review->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $review->moderator_edited_at);
    }

    // -----------------------------------------------------------------------
    // C. Relation with consent
    // -----------------------------------------------------------------------

    public function test_review_reviewconsent_relation_resolves_the_correct_row_using_review_id(): void
    {
        $review = $this->makeReview();
        $consent = ReviewConsent::create($this->consentPayload($review->id));

        $this->assertNotNull($review->reviewConsent);
        $this->assertTrue($review->reviewConsent->is($consent));

        // Inverse relation must remain valid.
        $this->assertTrue($consent->review->is($review));
    }

    // -----------------------------------------------------------------------
    // D. Legacy null safety
    // -----------------------------------------------------------------------

    public function test_review_without_consent_resolves_reviewconsent_as_null(): void
    {
        $review = $this->makeReview();

        $this->assertNull($review->reviewConsent);

        $this->assertDatabaseMissing('review_consents', [
            'review_id' => $review->id,
        ]);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeReview(): Reviews
    {
        return Reviews::create([
            'name' => 'Moderation Schema Foundation Tourist',
            'title' => null,
            'content' => 'Content used only for moderation schema-foundation testing.',
            'image' => null,
            'is_published' => 0,
        ]);
    }

    private function consentPayload(int $reviewId, array $overrides = []): array
    {
        return array_merge([
            'review_id' => $reviewId,
            'evidence_id' => '77777777-7777-4777-8777-777777777777',
            'consent_full_name' => 'Evidence Full Name',
            'consent_email' => 'evidence@example.com',
            'user_agreement_accepted_at' => '2026-08-17 10:00:00',
            'personal_data_consent_accepted_at' => '2026-08-17 10:00:01',
            'review_publication_consent_accepted_at' => '2026-08-17 10:00:02',
            'user_agreement_version' => 'moderation-schema-foundation-test-user-agreement',
            'personal_data_consent_version' => 'moderation-schema-foundation-test-personal-data-consent',
            'review_publication_consent_version' => 'moderation-schema-foundation-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'moderation-schema-foundation-deterministic-test-payload'),
        ], $overrides);
    }
}
