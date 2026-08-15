<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * Stage 13: Reviews consent-evidence schema-foundation slice (C1).
 *
 * Проверяет только новый database/model контракт review_consents / ReviewConsent:
 * структуру таблицы, ограничения уникальности, каскадное удаление, приватную
 * сериализацию и связь с Reviews. Не проверяет форму, контроллер, валидацию
 * или withdrawal-флоу — они появятся в C2/C3.
 */
class ReviewConsentSchemaFoundationTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // A. Schema contract
    // -----------------------------------------------------------------------

    public function test_review_consents_table_has_approved_columns(): void
    {
        $this->assertTrue(Schema::hasTable('review_consents'));

        $expectedColumns = [
            'id',
            'review_id',
            'evidence_id',
            'consent_full_name',
            'consent_email',
            'user_agreement_accepted_at',
            'personal_data_consent_accepted_at',
            'review_publication_consent_accepted_at',
            'user_agreement_version',
            'personal_data_consent_version',
            'review_publication_consent_version',
            'publication_scope',
            'publication_conditions',
            'review_payload_sha256',
            'withdrawn_at',
            'created_at',
            'updated_at',
        ];

        $this->assertTrue(Schema::hasColumns('review_consents', $expectedColumns));
    }

    public function test_review_consents_table_does_not_contain_forbidden_collection_fields(): void
    {
        $forbidden = ['ip_address', 'ip', 'user_agent', 'phone', 'session_id'];

        foreach ($forbidden as $column) {
            $this->assertFalse(
                Schema::hasColumn('review_consents', $column),
                "review_consents unexpectedly has forbidden column: {$column}"
            );
        }
    }

    // -----------------------------------------------------------------------
    // B. Evidence can be persisted
    // -----------------------------------------------------------------------

    public function test_evidence_can_be_persisted_and_belongs_to_review(): void
    {
        $review = $this->makeReview();

        $consent = ReviewConsent::create($this->consentPayload($review->id));

        $this->assertDatabaseHas('review_consents', [
            'id' => $consent->id,
            'review_id' => $review->id,
            'evidence_id' => $consent->evidence_id,
        ]);

        $this->assertTrue($consent->review->is($review));
    }

    // -----------------------------------------------------------------------
    // C. Model casts
    // -----------------------------------------------------------------------

    public function test_model_casts_behave_as_expected(): void
    {
        $review = $this->makeReview();

        $consent = ReviewConsent::create($this->consentPayload($review->id));

        $this->assertIsArray($consent->publication_scope);
        $this->assertSame(['name', 'content'], $consent->publication_scope);

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $consent->user_agreement_accepted_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $consent->personal_data_consent_accepted_at);
        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $consent->review_publication_consent_accepted_at);

        $this->assertNull($consent->withdrawn_at);

        $consent->withdrawn_at = now();
        $consent->save();
        $consent->refresh();

        $this->assertInstanceOf(\Illuminate\Support\Carbon::class, $consent->withdrawn_at);
    }

    // -----------------------------------------------------------------------
    // D. Privacy serialization
    // -----------------------------------------------------------------------

    public function test_private_consent_fields_are_hidden_from_serialization_but_still_persisted(): void
    {
        $review = $this->makeReview();

        $consent = ReviewConsent::create($this->consentPayload($review->id));

        $array = $consent->toArray();

        $this->assertArrayNotHasKey('consent_full_name', $array);
        $this->assertArrayNotHasKey('consent_email', $array);
        $this->assertArrayNotHasKey('publication_conditions', $array);

        // Still actually persisted and retrievable directly from attributes.
        $this->assertSame('Evidence Full Name', $consent->getAttribute('consent_full_name'));
        $this->assertSame('evidence@example.com', $consent->getAttribute('consent_email'));
        $this->assertSame('No promotional use please.', $consent->getAttribute('publication_conditions'));

        $this->assertDatabaseHas('review_consents', [
            'id' => $consent->id,
            'consent_full_name' => 'Evidence Full Name',
            'consent_email' => 'evidence@example.com',
            'publication_conditions' => 'No promotional use please.',
        ]);
    }

    // -----------------------------------------------------------------------
    // E. One evidence record per review
    // -----------------------------------------------------------------------

    public function test_only_one_evidence_record_allowed_per_review(): void
    {
        $review = $this->makeReview();

        ReviewConsent::create($this->consentPayload($review->id, [
            'evidence_id' => '11111111-1111-4111-8111-111111111111',
        ]));

        $this->expectException(QueryException::class);

        ReviewConsent::create($this->consentPayload($review->id, [
            'evidence_id' => '22222222-2222-4222-8222-222222222222',
        ]));
    }

    // -----------------------------------------------------------------------
    // F. Different reviews can have different evidence rows
    // -----------------------------------------------------------------------

    public function test_different_reviews_can_each_have_their_own_evidence_row(): void
    {
        $reviewOne = $this->makeReview();
        $reviewTwo = $this->makeReview();

        $consentOne = ReviewConsent::create($this->consentPayload($reviewOne->id, [
            'evidence_id' => '33333333-3333-4333-8333-333333333333',
        ]));
        $consentTwo = ReviewConsent::create($this->consentPayload($reviewTwo->id, [
            'evidence_id' => '44444444-4444-4444-8444-444444444444',
        ]));

        $this->assertDatabaseHas('review_consents', ['id' => $consentOne->id, 'review_id' => $reviewOne->id]);
        $this->assertDatabaseHas('review_consents', ['id' => $consentTwo->id, 'review_id' => $reviewTwo->id]);
    }

    // -----------------------------------------------------------------------
    // G. Soft delete preserves evidence
    // -----------------------------------------------------------------------

    public function test_soft_deleting_review_preserves_consent_evidence_row(): void
    {
        $review = $this->makeReview();
        $consent = ReviewConsent::create($this->consentPayload($review->id));

        $review->delete();

        $this->assertSoftDeleted('reviews', ['id' => $review->id]);
        $this->assertDatabaseHas('review_consents', ['id' => $consent->id]);
    }

    // -----------------------------------------------------------------------
    // H. Hard delete cascades evidence
    // -----------------------------------------------------------------------

    public function test_force_deleting_review_cascades_to_consent_evidence_row(): void
    {
        $review = $this->makeReview();
        $consent = ReviewConsent::create($this->consentPayload($review->id));

        $review->forceDelete();

        $this->assertDatabaseMissing('review_consents', ['id' => $consent->id]);
    }

    // -----------------------------------------------------------------------
    // I. evidence_id uniqueness
    // -----------------------------------------------------------------------

    public function test_evidence_id_is_unique_across_reviews(): void
    {
        $reviewOne = $this->makeReview();
        $reviewTwo = $this->makeReview();

        $sharedEvidenceId = '55555555-5555-4555-8555-555555555555';

        ReviewConsent::create($this->consentPayload($reviewOne->id, [
            'evidence_id' => $sharedEvidenceId,
        ]));

        $this->expectException(QueryException::class);

        ReviewConsent::create($this->consentPayload($reviewTwo->id, [
            'evidence_id' => $sharedEvidenceId,
        ]));
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeReview(): Reviews
    {
        return Reviews::create([
            'name' => 'Schema Foundation Tourist',
            'title' => null,
            'content' => 'Content used only for consent-evidence schema-foundation testing.',
            'image' => null,
            'is_published' => 0,
        ]);
    }

    private function consentPayload(int $reviewId, array $overrides = []): array
    {
        return array_merge([
            'review_id' => $reviewId,
            'evidence_id' => '66666666-6666-4666-8666-666666666666',
            'consent_full_name' => 'Evidence Full Name',
            'consent_email' => 'evidence@example.com',
            'user_agreement_accepted_at' => '2026-08-16 10:00:00',
            'personal_data_consent_accepted_at' => '2026-08-16 10:00:01',
            'review_publication_consent_accepted_at' => '2026-08-16 10:00:02',
            'user_agreement_version' => 'schema-foundation-test-user-agreement',
            'personal_data_consent_version' => 'schema-foundation-test-personal-data-consent',
            'review_publication_consent_version' => 'schema-foundation-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => 'No promotional use please.',
            'review_payload_sha256' => hash('sha256', 'schema-foundation-deterministic-test-payload'),
        ], $overrides);
    }
}
