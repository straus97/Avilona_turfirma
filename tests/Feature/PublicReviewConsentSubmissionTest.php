<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Ramsey\Uuid\Uuid;
use Tests\TestCase;

/**
 * Stage 13 C3: Reviews public-submission consent-evidence integration slice.
 *
 * Проверяет, что публичная форма отзывов атомарно создаёт запись reviews
 * (title = null, is_published = 0) и ровно одну связанную запись
 * review_consents, что приватные consent-поля не дублируются в публичный
 * review, что publication_scope/evidence_id/acceptance-таймстампы/версии
 * документов/review_payload_sha256 формируются по утверждённому контракту,
 * и что каждое из трёх подтверждений независимо обязательно (accepted, а
 * не просто "поле присутствует").
 */
class PublicReviewConsentSubmissionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Validator::extend('captcha', function () {
            return true;
        });
    }

    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'name' => 'Public Review Name',
            'consent_full_name' => 'Consent Evidence Full Name',
            'consent_email' => 'consent-evidence@example.com',
            'message' => 'CONSENT-SUBMIT-MARKER ' . str_repeat('a', 60),
            'publication_conditions' => 'Please remove any phone numbers before publishing.',
            'captcha' => 'anything',
            'user_agreement_accepted' => '1',
            'personal_data_consent_accepted' => '1',
            'review_publication_consent_accepted' => '1',
        ], $overrides);
    }

    // -----------------------------------------------------------------------
    // A. Successful atomic submission
    // -----------------------------------------------------------------------

    public function test_successful_submission_atomically_creates_review_and_consent(): void
    {
        $response = $this->post(route('review_create.index'), $this->validPayload());

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('review.index'));

        $this->assertSame(1, Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->count());

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->first();
        $this->assertNotNull($review);
        $this->assertFalse((bool) $review->is_published);
        $this->assertNull($review->title);
        $this->assertSame('Public Review Name', $review->name);
        $this->assertStringStartsWith('CONSENT-SUBMIT-MARKER', $review->content);

        $this->assertSame(1, ReviewConsent::where('review_id', $review->id)->count());
    }

    // -----------------------------------------------------------------------
    // B. Private consent values
    // -----------------------------------------------------------------------

    public function test_private_consent_values_are_persisted_on_consent_row_not_on_review(): void
    {
        $this->post(route('review_create.index'), $this->validPayload());

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->firstOrFail();
        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();

        $this->assertSame('Consent Evidence Full Name', $consent->consent_full_name);
        $this->assertSame('consent-evidence@example.com', $consent->consent_email);
        $this->assertSame('Please remove any phone numbers before publishing.', $consent->publication_conditions);

        $this->assertDatabaseMissing('reviews', [
            'id' => $review->id,
            'name' => 'Consent Evidence Full Name',
        ]);
    }

    // -----------------------------------------------------------------------
    // C. Publication scope
    // -----------------------------------------------------------------------

    public function test_publication_scope_is_exactly_name_and_content(): void
    {
        $this->post(route('review_create.index'), $this->validPayload());

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->firstOrFail();
        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();

        $this->assertSame(['name', 'content'], $consent->publication_scope);
    }

    // -----------------------------------------------------------------------
    // D. Evidence UUID
    // -----------------------------------------------------------------------

    public function test_evidence_id_is_a_valid_uuid(): void
    {
        $this->post(route('review_create.index'), $this->validPayload());

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->firstOrFail();
        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();

        $this->assertMatchesRegularExpression(
            '/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/i',
            $consent->evidence_id
        );
    }

    // -----------------------------------------------------------------------
    // E. Acceptance timestamps
    // -----------------------------------------------------------------------

    public function test_acceptance_timestamps_are_non_null_and_share_one_instant(): void
    {
        $this->post(route('review_create.index'), $this->validPayload());

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->firstOrFail();
        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();

        $this->assertNotNull($consent->user_agreement_accepted_at);
        $this->assertNotNull($consent->personal_data_consent_accepted_at);
        $this->assertNotNull($consent->review_publication_consent_accepted_at);

        $this->assertTrue($consent->user_agreement_accepted_at->equalTo($consent->personal_data_consent_accepted_at));
        $this->assertTrue($consent->personal_data_consent_accepted_at->equalTo($consent->review_publication_consent_accepted_at));

        $this->assertNull($consent->withdrawn_at);
    }

    // -----------------------------------------------------------------------
    // F. Document version fingerprints
    // -----------------------------------------------------------------------

    public function test_document_version_fingerprints_match_the_actual_committed_artifacts(): void
    {
        $this->post(route('review_create.index'), $this->validPayload());

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->firstOrFail();
        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();

        $expectedUserAgreementVersion = 'sha256:' . hash_file('sha256', public_path('documents/User_Agreement.pdf'));
        $expectedPersonalDataConsentVersion = 'sha256:' . hash_file(
            'sha256',
            resource_path('views/legal/review-personal-data-consent.blade.php')
        );
        $expectedPublicationConsentVersion = 'sha256:' . hash_file(
            'sha256',
            resource_path('views/legal/review-publication-consent.blade.php')
        );

        $this->assertSame($expectedUserAgreementVersion, $consent->user_agreement_version);
        $this->assertSame($expectedPersonalDataConsentVersion, $consent->personal_data_consent_version);
        $this->assertSame($expectedPublicationConsentVersion, $consent->review_publication_consent_version);

        foreach ([
            $consent->user_agreement_version,
            $consent->personal_data_consent_version,
            $consent->review_publication_consent_version,
        ] as $version) {
            $this->assertMatchesRegularExpression('/^sha256:[0-9a-f]{64}$/', $version);
        }
    }

    // -----------------------------------------------------------------------
    // G. Review payload hash
    // -----------------------------------------------------------------------

    public function test_review_payload_sha256_matches_independent_canonical_reconstruction(): void
    {
        $payload = $this->validPayload();
        $this->post(route('review_create.index'), $payload);

        $review = Reviews::where('content', 'like', 'CONSENT-SUBMIT-MARKER%')->firstOrFail();
        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();

        $expectedHash = hash('sha256', json_encode([
            'name' => $payload['name'],
            'content' => $payload['message'],
        ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES | JSON_THROW_ON_ERROR));

        $this->assertSame($expectedHash, $consent->review_payload_sha256);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $consent->review_payload_sha256);
    }

    // -----------------------------------------------------------------------
    // H. Consents are independently required
    // -----------------------------------------------------------------------

    public function test_each_confirmation_is_independently_required(): void
    {
        foreach (['user_agreement_accepted', 'personal_data_consent_accepted', 'review_publication_consent_accepted'] as $field) {
            $marker = 'MISSING-' . strtoupper($field) . '-MARKER';
            $payload = $this->validPayload(['message' => $marker . ' ' . str_repeat('b', 60)]);
            unset($payload[$field]);

            $response = $this->post(route('review_create.index'), $payload);

            $response->assertSessionHasErrors($field);
            $this->assertNull(Reviews::where('content', 'like', $marker . '%')->first());
            $this->assertSame(0, ReviewConsent::whereHas('review', function ($query) use ($marker) {
                $query->where('content', 'like', $marker . '%');
            })->count());
        }
    }

    // -----------------------------------------------------------------------
    // I. Accepted semantics (not merely present)
    // -----------------------------------------------------------------------

    public function test_present_but_unaccepted_confirmation_value_of_zero_does_not_satisfy_the_rule(): void
    {
        foreach (['user_agreement_accepted', 'personal_data_consent_accepted', 'review_publication_consent_accepted'] as $field) {
            $marker = 'UNACCEPTED-' . strtoupper($field) . '-MARKER';

            $response = $this->post(route('review_create.index'), $this->validPayload([
                $field => '0',
                'message' => $marker . ' ' . str_repeat('c', 60),
            ]));

            $response->assertSessionHasErrors($field);
            $this->assertNull(Reviews::where('content', 'like', $marker . '%')->first());
        }
    }

    // -----------------------------------------------------------------------
    // J. consent_full_name required
    // -----------------------------------------------------------------------

    public function test_missing_consent_full_name_fails_validation_and_creates_nothing(): void
    {
        $payload = $this->validPayload(['message' => 'MISSING-CONSENT-FULL-NAME-MARKER ' . str_repeat('d', 60)]);
        unset($payload['consent_full_name']);

        $response = $this->post(route('review_create.index'), $payload);

        $response->assertSessionHasErrors('consent_full_name');
        $this->assertNull(Reviews::where('content', 'like', 'MISSING-CONSENT-FULL-NAME-MARKER%')->first());
    }

    // -----------------------------------------------------------------------
    // K. consent_email required and validated
    // -----------------------------------------------------------------------

    public function test_missing_consent_email_fails_validation_and_creates_nothing(): void
    {
        $payload = $this->validPayload(['message' => 'MISSING-CONSENT-EMAIL-MARKER ' . str_repeat('e', 60)]);
        unset($payload['consent_email']);

        $response = $this->post(route('review_create.index'), $payload);

        $response->assertSessionHasErrors('consent_email');
        $this->assertNull(Reviews::where('content', 'like', 'MISSING-CONSENT-EMAIL-MARKER%')->first());
    }

    public function test_malformed_consent_email_fails_validation_and_creates_nothing(): void
    {
        $response = $this->post(route('review_create.index'), $this->validPayload([
            'consent_email' => 'not-an-email',
            'message' => 'MALFORMED-CONSENT-EMAIL-MARKER ' . str_repeat('f', 60),
        ]));

        $response->assertSessionHasErrors('consent_email');
        $this->assertNull(Reviews::where('content', 'like', 'MALFORMED-CONSENT-EMAIL-MARKER%')->first());
    }

    // -----------------------------------------------------------------------
    // L. Optional publication conditions
    // -----------------------------------------------------------------------

    public function test_submission_succeeds_when_publication_conditions_is_blank(): void
    {
        $payload = $this->validPayload([
            'message' => 'BLANK-PUBLICATION-CONDITIONS-MARKER ' . str_repeat('g', 60),
            'publication_conditions' => '',
        ]);

        $response = $this->post(route('review_create.index'), $payload);

        $response->assertSessionHasNoErrors();

        $review = Reviews::where('content', 'like', 'BLANK-PUBLICATION-CONDITIONS-MARKER%')->first();
        $this->assertNotNull($review);

        $consent = ReviewConsent::where('review_id', $review->id)->firstOrFail();
        $this->assertNull($consent->publication_conditions);
    }

    public function test_submission_succeeds_when_publication_conditions_is_omitted(): void
    {
        $payload = $this->validPayload(['message' => 'OMITTED-PUBLICATION-CONDITIONS-MARKER ' . str_repeat('h', 60)]);
        unset($payload['publication_conditions']);

        $response = $this->post(route('review_create.index'), $payload);

        $response->assertSessionHasNoErrors();
        $this->assertNotNull(Reviews::where('content', 'like', 'OMITTED-PUBLICATION-CONDITIONS-MARKER%')->first());
    }

    // -----------------------------------------------------------------------
    // M. No legacy subject contract
    // -----------------------------------------------------------------------

    public function test_extra_subject_field_is_ignored_and_title_remains_null(): void
    {
        $payload = $this->validPayload([
            'message' => 'EXTRA-SUBJECT-FIELD-MARKER ' . str_repeat('i', 60),
        ]);
        $payload['subject'] = 'SHOULD-NOT-BE-STORED';

        $response = $this->post(route('review_create.index'), $payload);

        $response->assertSessionHasNoErrors();

        $review = Reviews::where('content', 'like', 'EXTRA-SUBJECT-FIELD-MARKER%')->firstOrFail();
        $this->assertNull($review->title);
    }

    // -----------------------------------------------------------------------
    // N. Transaction rollback
    // -----------------------------------------------------------------------

    public function test_review_is_rolled_back_when_consent_evidence_persistence_fails(): void
    {
        $collidingReview = Reviews::create([
            'name' => 'Existing Evidence Owner',
            'title' => null,
            'content' => 'Existing evidence owner content.',
            'image' => null,
            'is_published' => false,
        ]);

        $fixedUuid = (string) Uuid::uuid4();

        ReviewConsent::create([
            'review_id' => $collidingReview->id,
            'evidence_id' => $fixedUuid,
            'consent_full_name' => 'Existing Evidence Full Name',
            'consent_email' => 'existing-evidence@example.com',
            'user_agreement_accepted_at' => now(),
            'personal_data_consent_accepted_at' => now(),
            'review_publication_consent_accepted_at' => now(),
            'user_agreement_version' => 'sha256:' . hash_file('sha256', public_path('documents/User_Agreement.pdf')),
            'personal_data_consent_version' => 'sha256:' . hash_file('sha256', resource_path('views/legal/review-personal-data-consent.blade.php')),
            'review_publication_consent_version' => 'sha256:' . hash_file('sha256', resource_path('views/legal/review-publication-consent.blade.php')),
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'existing-evidence-payload'),
        ]);

        Str::createUuidsUsing(fn () => $fixedUuid);

        try {
            $marker = 'ROLLBACK-MARKER-' . str_repeat('j', 20);

            $threw = false;
            try {
                $this->post(route('review_create.index'), $this->validPayload([
                    'message' => $marker . ' ' . str_repeat('k', 60),
                ]));
            } catch (QueryException $e) {
                $threw = true;
            }

            $this->assertNull(Reviews::where('content', 'like', $marker . '%')->first());
            $this->assertSame(1, ReviewConsent::where('evidence_id', $fixedUuid)->count());
        } finally {
            Str::createUuidsNormally();
        }
    }

    // -----------------------------------------------------------------------
    // O. Public review title is not rendered
    // -----------------------------------------------------------------------

    public function test_public_reviews_page_does_not_render_historical_review_title(): void
    {
        Reviews::create([
            'name' => 'Historical Public Name Marker',
            'title' => 'HISTORICAL-TITLE-MARKER',
            'content' => 'HISTORICAL-CONTENT-MARKER ' . str_repeat('l', 60),
            'image' => null,
            'is_published' => true,
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('Historical Public Name Marker', false);
        $response->assertSee('HISTORICAL-CONTENT-MARKER', false);
        $response->assertDontSee('HISTORICAL-TITLE-MARKER', false);
    }

    // -----------------------------------------------------------------------
    // P. Private consent values are not rendered on the public reviews page
    // -----------------------------------------------------------------------

    public function test_public_reviews_page_does_not_render_private_consent_values(): void
    {
        $review = Reviews::create([
            'name' => 'Rendered Public Name Marker',
            'title' => null,
            'content' => 'RENDERED-CONTENT-MARKER ' . str_repeat('m', 60),
            'image' => null,
            'is_published' => true,
        ]);

        ReviewConsent::create([
            'review_id' => $review->id,
            'evidence_id' => (string) Uuid::uuid4(),
            'consent_full_name' => 'PRIVATE-CONSENT-FULL-NAME-MARKER',
            'consent_email' => 'private-consent-email-marker@example.com',
            'user_agreement_accepted_at' => now(),
            'personal_data_consent_accepted_at' => now(),
            'review_publication_consent_accepted_at' => now(),
            'user_agreement_version' => 'sha256:' . hash_file('sha256', public_path('documents/User_Agreement.pdf')),
            'personal_data_consent_version' => 'sha256:' . hash_file('sha256', resource_path('views/legal/review-personal-data-consent.blade.php')),
            'review_publication_consent_version' => 'sha256:' . hash_file('sha256', resource_path('views/legal/review-publication-consent.blade.php')),
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'rendered-consent-payload'),
        ]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('Rendered Public Name Marker', false);
        $response->assertSee('RENDERED-CONTENT-MARKER', false);
        $response->assertDontSee('PRIVATE-CONSENT-FULL-NAME-MARKER', false);
        $response->assertDontSee('private-consent-email-marker@example.com', false);
    }
}
