<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 13 C5A: withdrawn publication-consent enforcement foundation.
 *
 * Проверяет только серверный gate публикации при review_consents.withdrawn_at
 * и публичное fail-safe исключение отозванных отзывов на /reviews и главной
 * странице. Не проверяет сам workflow отзыва согласия (C5B) — здесь withdrawn_at
 * только читается, никогда не устанавливается и не очищается контроллерами.
 */
class ReviewWithdrawalPublicationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    private const WITHDRAWAL_MESSAGE = 'Согласие на публикацию этого отзыва отозвано. Повторная публикация невозможна.';

    // -----------------------------------------------------------------------
    // 1-4. Admin/Manager cannot publish a withdrawn review
    // -----------------------------------------------------------------------

    public function test_admin_cannot_publish_review_with_withdrawn_consent(): void
    {
        $review = $this->makeReview(['content' => 'Withdrawn gate content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Withdrawn gate content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('is_published');
        $errors = session('errors');
        $this->assertSame(self::WITHDRAWAL_MESSAGE, $errors->first('is_published'));

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
    }

    public function test_manager_cannot_publish_review_with_withdrawn_consent(): void
    {
        $review = $this->makeReview(['content' => 'Withdrawn gate content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Withdrawn gate content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('is_published');
        $errors = session('errors');
        $this->assertSame(self::WITHDRAWAL_MESSAGE, $errors->first('is_published'));

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
    }

    // -----------------------------------------------------------------------
    // 5, 6. Failed withdrawal-gate request does not partially persist state
    // -----------------------------------------------------------------------

    public function test_admin_failed_withdrawal_gate_does_not_partially_persist_unrelated_changes(): void
    {
        $review = $this->makeReview(['name' => 'Untouched Name', 'content' => 'Untouched content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => 'Tampered Name',
            'title' => 'Tampered Title',
            'content' => 'Attempted new content',
            'gender' => 'girl',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('is_published');

        $review->refresh();
        $consent->refresh();
        $this->assertSame('Untouched Name', $review->name);
        $this->assertSame('Untouched content', $review->content);
        $this->assertNull($review->title);
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
    }

    public function test_manager_failed_withdrawal_gate_does_not_partially_persist_unrelated_changes(): void
    {
        $review = $this->makeReview(['name' => 'Untouched Name', 'content' => 'Untouched content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => 'Tampered Name',
            'title' => 'Tampered Title',
            'content' => 'Attempted new content',
            'gender' => 'girl',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('is_published');

        $review->refresh();
        $consent->refresh();
        $this->assertSame('Untouched Name', $review->name);
        $this->assertSame('Untouched content', $review->content);
        $this->assertNull($review->title);
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
    }

    // -----------------------------------------------------------------------
    // 7, 8. Stale withdrawn+published review excluded from public surfaces
    // -----------------------------------------------------------------------

    public function test_stale_withdrawn_published_review_excluded_from_public_reviews_index(): void
    {
        $review = $this->makeReview(['content' => 'STALE-WITHDRAWN-REVIEWS-MARKER-1', 'is_published' => true]);
        $this->makeConsent($review, ['withdrawn_at' => now()->subHour()]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertDontSee('STALE-WITHDRAWN-REVIEWS-MARKER-1');
    }

    public function test_stale_withdrawn_published_review_excluded_from_homepage(): void
    {
        $review = $this->makeReview(['content' => 'STALE-WITHDRAWN-HOME-MARKER-2', 'is_published' => true]);
        $this->makeConsent($review, ['withdrawn_at' => now()->subHour()]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertDontSee('STALE-WITHDRAWN-HOME-MARKER-2');
    }

    // -----------------------------------------------------------------------
    // 9, 10. Non-withdrawn consent compatibility
    // -----------------------------------------------------------------------

    public function test_published_review_with_non_withdrawn_consent_remains_visible_on_reviews_index(): void
    {
        $review = $this->makeReview(['content' => 'NON-WITHDRAWN-REVIEWS-MARKER-3', 'is_published' => true]);
        $this->makeConsent($review, ['withdrawn_at' => null]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('NON-WITHDRAWN-REVIEWS-MARKER-3');
    }

    public function test_published_review_with_non_withdrawn_consent_remains_eligible_for_homepage(): void
    {
        $review = $this->makeReview(['content' => 'NON-WITHDRAWN-HOME-MARKER-4', 'is_published' => true]);
        $this->makeConsent($review, ['withdrawn_at' => null]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('NON-WITHDRAWN-HOME-MARKER-4');
    }

    // -----------------------------------------------------------------------
    // 11, 12. Legacy review with no ReviewConsent compatibility
    // -----------------------------------------------------------------------

    public function test_published_legacy_review_without_consent_remains_visible_on_reviews_index(): void
    {
        $this->makeReview(['content' => 'LEGACY-NO-CONSENT-REVIEWS-MARKER-5', 'is_published' => true]);

        $response = $this->get(route('review.index'));

        $response->assertOk();
        $response->assertSee('LEGACY-NO-CONSENT-REVIEWS-MARKER-5');
    }

    public function test_published_legacy_review_without_consent_remains_eligible_for_homepage(): void
    {
        $this->makeReview(['content' => 'LEGACY-NO-CONSENT-HOME-MARKER-6', 'is_published' => true]);

        $response = $this->get(route('home.index'));

        $response->assertOk();
        $response->assertSee('LEGACY-NO-CONSENT-HOME-MARKER-6');
    }

    // -----------------------------------------------------------------------
    // 13, 14, 15. Unpublishing a withdrawn stale-published review is allowed
    // and withdrawn_at is left untouched
    // -----------------------------------------------------------------------

    public function test_admin_can_unpublish_withdrawn_stale_published_review(): void
    {
        $review = $this->makeReview(['content' => 'Stale published content', 'is_published' => true]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $withdrawnAt = $consent->withdrawn_at;
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Stale published content',
            'gender' => 'boy',
            // is_published intentionally omitted -> resolves to false
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cabinet.admin.content'));

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
        $this->assertTrue($consent->withdrawn_at->equalTo($withdrawnAt));
    }

    public function test_manager_can_unpublish_withdrawn_stale_published_review(): void
    {
        $review = $this->makeReview(['content' => 'Stale published content', 'is_published' => true]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $withdrawnAt = $consent->withdrawn_at;
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Stale published content',
            'gender' => 'boy',
            // is_published intentionally omitted -> resolves to false
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cabinet.manager.content'));

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
        $this->assertTrue($consent->withdrawn_at->equalTo($withdrawnAt));
    }

    // -----------------------------------------------------------------------
    // 16, 17. Legacy review with no ReviewConsent may still be published
    // -----------------------------------------------------------------------

    public function test_admin_may_still_publish_legacy_review_with_no_review_consent(): void
    {
        $review = $this->makeReview(['content' => 'Legacy publish content', 'is_published' => false]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Legacy publish content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent);
    }

    public function test_manager_may_still_publish_legacy_review_with_no_review_consent(): void
    {
        $review = $this->makeReview(['content' => 'Legacy publish content', 'is_published' => false]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Legacy publish content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent);
    }

    // -----------------------------------------------------------------------
    // 18. publication_conditions enforcement is not bypassed when withdrawn_at is null
    // -----------------------------------------------------------------------

    public function test_publication_conditions_gate_still_enforced_when_withdrawn_at_is_null(): void
    {
        $review = $this->makeReview(['content' => 'Conditions gate content', 'is_published' => false]);
        $consent = $this->makeConsent($review, [
            'withdrawn_at' => null,
            'publication_conditions' => 'Remove phone numbers.',
        ]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Conditions gate content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');
        $response->assertSessionDoesntHaveErrors('is_published');

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNull($consent->publication_conditions_satisfied_at);
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

    private function makeReview(array $overrides = []): Reviews
    {
        return Reviews::create(array_merge([
            'name' => 'Withdrawal Enforcement Tourist',
            'title' => null,
            'content' => 'Default withdrawal enforcement content.',
            'image' => null,
            'is_published' => false,
        ], $overrides));
    }

    private function makeConsent(Reviews $review, array $overrides = []): ReviewConsent
    {
        return ReviewConsent::create(array_merge([
            'review_id' => $review->id,
            'evidence_id' => (string) \Illuminate\Support\Str::uuid(),
            'consent_full_name' => 'Evidence Full Name',
            'consent_email' => 'evidence@example.com',
            'user_agreement_accepted_at' => now(),
            'personal_data_consent_accepted_at' => now(),
            'review_publication_consent_accepted_at' => now(),
            'user_agreement_version' => 'withdrawal-enforcement-test-user-agreement',
            'personal_data_consent_version' => 'withdrawal-enforcement-test-personal-data-consent',
            'review_publication_consent_version' => 'withdrawal-enforcement-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'withdrawal-enforcement-test-payload-' . $review->id),
            'withdrawn_at' => null,
        ], $overrides));
    }
}
