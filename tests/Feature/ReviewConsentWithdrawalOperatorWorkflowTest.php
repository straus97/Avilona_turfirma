<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 13 C5B: internal Admin/Manager operator workflow for RECORDING an
 * already received and already verified author request to withdraw review
 * publication consent.
 *
 * Covers only the new confirmation/withdrawal routes, the concurrency
 * hardening added to Admin/Manager updateReview (ReviewConsent::lockForUpdate
 * as the authoritative check), and the private-identity display scoped to the
 * dedicated confirmation screen. Does not duplicate the full C4/C5A suites —
 * those remain the source of truth for ordinary moderation/publication-gate
 * behavior and are only spot-checked here for regression after the lock
 * hardening.
 */
class ReviewConsentWithdrawalOperatorWorkflowTest extends TestCase
{
    use RefreshDatabase;

    private const ALREADY_WITHDRAWN_MESSAGE = 'Согласие на публикацию этого отзыва уже отозвано. Повторное действие не требуется.';

    private const NO_CONSENT_MESSAGE = 'У этого отзыва нет записи согласия на публикацию — фиксация отзыва согласия недоступна.';

    private const SUCCESS_MESSAGE = 'Отзыв согласия на публикацию зафиксирован. Материал снят с публикации.';

    private const WITHDRAWAL_PUBLISH_BLOCK_MESSAGE = 'Согласие на публикацию этого отзыва отозвано. Повторная публикация невозможна.';

    private const WITHDRAWAL_CONFIRMED_FRIENDLY_MESSAGE = 'Подтвердите, что запрос автора на отзыв согласия получен, проверен и относится к этому отзыву.';

    // -----------------------------------------------------------------------
    // 1, 2. Eligible confirmation GET
    // -----------------------------------------------------------------------

    public function test_admin_eligible_confirmation_get_renders(): void
    {
        $review = $this->makeReview(['content' => 'Eligible confirmation content']);
        $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review));

        $response->assertOk();
    }

    public function test_manager_eligible_confirmation_get_renders(): void
    {
        $review = $this->makeReview(['content' => 'Eligible confirmation content']);
        $this->makeConsent($review);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.withdraw-consent.confirm', $review));

        $response->assertOk();
    }

    // -----------------------------------------------------------------------
    // 3. Confirmation shows review id/name/content/publication state
    // -----------------------------------------------------------------------

    public function test_confirmation_screen_shows_review_identity_and_publication_state(): void
    {
        $review = $this->makeReview([
            'name' => 'Withdrawal Identity Tourist',
            'content' => 'WITHDRAWAL-IDENTITY-CONTENT-MARKER',
            'is_published' => true,
        ]);
        $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review));

        $response->assertOk();
        $response->assertSee((string) $review->id);
        $response->assertSee('Withdrawal Identity Tourist');
        $response->assertSee('WITHDRAWAL-IDENTITY-CONTENT-MARKER');
        $response->assertSee('Опубликован');
    }

    // -----------------------------------------------------------------------
    // 4. Dedicated screen shows escaped consent_full_name/email
    // -----------------------------------------------------------------------

    public function test_dedicated_screen_shows_escaped_consent_identity(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'consent_full_name' => '<script>alert(1)</script> Verification Name',
            'consent_email' => 'verify-me@example.com',
        ]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review));

        $response->assertOk();
        $response->assertSee('Verification Name');
        $response->assertSee('verify-me@example.com');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // 5. Dedicated screen omits evidence_id/hash/unrelated evidence fields
    // -----------------------------------------------------------------------

    public function test_dedicated_screen_omits_unrelated_evidence_fields(): void
    {
        $review = $this->makeReview();
        $consent = $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review));

        $response->assertOk();
        $response->assertDontSee($consent->evidence_id);
        $response->assertDontSee($consent->review_payload_sha256);
        $response->assertDontSee($consent->user_agreement_version);
    }

    // -----------------------------------------------------------------------
    // 6, 7. Ordinary edit screens still hide consent identity
    // -----------------------------------------------------------------------

    public function test_ordinary_admin_edit_screen_still_hides_consent_identity(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'consent_full_name' => 'Hidden Identity Name',
            'consent_email' => 'hidden-identity@example.com',
        ]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee('Hidden Identity Name');
        $response->assertDontSee('hidden-identity@example.com');
    }

    public function test_ordinary_manager_edit_screen_still_hides_consent_identity(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'consent_full_name' => 'Hidden Identity Name',
            'consent_email' => 'hidden-identity@example.com',
        ]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee('Hidden Identity Name');
        $response->assertDontSee('hidden-identity@example.com');
    }

    // -----------------------------------------------------------------------
    // 8, 9, 10. withdrawal_confirmed required / rejected / no writes
    // -----------------------------------------------------------------------

    public function test_withdrawal_confirmed_is_required(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), []);

        $response->assertSessionHasErrors('withdrawal_confirmed');
        $errors = session('errors');
        $this->assertSame(self::WITHDRAWAL_CONFIRMED_FRIENDLY_MESSAGE, $errors->first('withdrawal_confirmed'));

        $review->refresh();
        $consent->refresh();
        $this->assertNull($consent->withdrawn_at);
        $this->assertTrue((bool) $review->is_published);
    }

    public function test_unaccepted_withdrawal_confirmed_is_rejected(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '0',
        ]);

        $response->assertSessionHasErrors('withdrawal_confirmed');
        $errors = session('errors');
        $message = $errors->first('withdrawal_confirmed');
        $this->assertSame(self::WITHDRAWAL_CONFIRMED_FRIENDLY_MESSAGE, $message);
        $this->assertStringNotContainsString('withdrawal confirmed', $message);
        $this->assertStringNotContainsString('withdrawal_confirmed', $message);

        $review->refresh();
        $consent->refresh();
        $this->assertNull($consent->withdrawn_at);
        $this->assertTrue((bool) $review->is_published);
    }

    // -----------------------------------------------------------------------
    // Browser-QA correction: Manager equivalent of the friendly
    // withdrawal_confirmed message (defect 2)
    // -----------------------------------------------------------------------

    public function test_manager_withdrawal_confirmed_required_uses_friendly_russian_message(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->post(route('cabinet.manager.reviews.withdraw-consent', $review), []);

        $response->assertSessionHasErrors('withdrawal_confirmed');
        $errors = session('errors');
        $this->assertSame(self::WITHDRAWAL_CONFIRMED_FRIENDLY_MESSAGE, $errors->first('withdrawal_confirmed'));

        $review->refresh();
        $consent->refresh();
        $this->assertNull($consent->withdrawn_at);
        $this->assertTrue((bool) $review->is_published);
    }

    public function test_failed_validation_causes_no_writes(): void
    {
        $review = $this->makeReview(['is_published' => true, 'content' => 'Untouched by failed validation']);
        $consent = $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), []);

        $review->refresh();
        $consent->refresh();
        $this->assertSame('Untouched by failed validation', $review->content);
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($consent->withdrawn_at);
    }

    // -----------------------------------------------------------------------
    // 11, 12. First POST sets withdrawn_at + unpublishes
    // -----------------------------------------------------------------------

    public function test_admin_first_post_sets_withdrawn_at_and_unpublishes(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cabinet.admin.reviews.edit', $review));
        $response->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $review->refresh();
        $consent->refresh();
        $this->assertNotNull($consent->withdrawn_at);
        $this->assertFalse((bool) $review->is_published);
    }

    public function test_manager_first_post_sets_withdrawn_at_and_unpublishes(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->post(route('cabinet.manager.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cabinet.manager.reviews.edit', $review));
        $response->assertSessionHas('success', self::SUCCESS_MESSAGE);

        $review->refresh();
        $consent->refresh();
        $this->assertNotNull($consent->withdrawn_at);
        $this->assertFalse((bool) $review->is_published);
    }

    // -----------------------------------------------------------------------
    // 13, 14. Repeated POST preserves exact withdrawn_at
    // -----------------------------------------------------------------------

    public function test_admin_repeated_post_preserves_exact_withdrawn_at(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $originalWithdrawnAt = $consent->withdrawn_at;
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::ALREADY_WITHDRAWN_MESSAGE);

        $review->refresh();
        $consent->refresh();
        $this->assertTrue($consent->withdrawn_at->equalTo($originalWithdrawnAt));
        $this->assertFalse((bool) $review->is_published);
    }

    public function test_manager_repeated_post_preserves_exact_withdrawn_at(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $originalWithdrawnAt = $consent->withdrawn_at;
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->post(route('cabinet.manager.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertSessionHas('status', self::ALREADY_WITHDRAWN_MESSAGE);

        $review->refresh();
        $consent->refresh();
        $this->assertTrue($consent->withdrawn_at->equalTo($originalWithdrawnAt));
        $this->assertFalse((bool) $review->is_published);
    }

    // -----------------------------------------------------------------------
    // 15. Stale published + already-withdrawn repeated POST converges to unpublished
    // -----------------------------------------------------------------------

    public function test_stale_published_already_withdrawn_repeated_post_converges_to_unpublished(): void
    {
        $review = $this->makeReview(['is_published' => true]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $originalWithdrawnAt = $consent->withdrawn_at;
        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertTrue($consent->withdrawn_at->equalTo($originalWithdrawnAt));
    }

    // -----------------------------------------------------------------------
    // 16, 17, 18. Already-withdrawn edit/confirmation state
    // -----------------------------------------------------------------------

    public function test_admin_already_withdrawn_edit_has_no_action_link(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee(route('cabinet.admin.reviews.withdraw-consent.confirm', $review), false);
    }

    public function test_manager_already_withdrawn_edit_has_no_action_link(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee(route('cabinet.manager.reviews.withdraw-consent.confirm', $review), false);
    }

    public function test_already_withdrawn_direct_confirmation_get_redirects_safely(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['withdrawn_at' => now()->subDay()]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review));

        $response->assertRedirect(route('cabinet.admin.reviews.edit', $review));
        $response->assertSessionHas('status', self::ALREADY_WITHDRAWN_MESSAGE);
    }

    // -----------------------------------------------------------------------
    // 19, 20, 21, 22. Legacy / no-consent state
    // -----------------------------------------------------------------------

    public function test_admin_legacy_edit_has_no_action_link(): void
    {
        $review = $this->makeReview();
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee(self::NO_CONSENT_MESSAGE);
        $response->assertDontSee(route('cabinet.admin.reviews.withdraw-consent.confirm', $review), false);
    }

    public function test_manager_legacy_edit_has_no_action_link(): void
    {
        $review = $this->makeReview();
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee(self::NO_CONSENT_MESSAGE);
        $response->assertDontSee(route('cabinet.manager.reviews.withdraw-consent.confirm', $review), false);
    }

    public function test_legacy_direct_confirmation_get_redirects_safely(): void
    {
        $review = $this->makeReview();
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review));

        $response->assertRedirect(route('cabinet.admin.reviews.edit', $review));
        $response->assertSessionHas('error', self::NO_CONSENT_MESSAGE);
    }

    public function test_legacy_post_fabricates_no_consent_and_mutates_no_review(): void
    {
        $review = $this->makeReview(['is_published' => true, 'content' => 'Legacy untouched content']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $response->assertSessionHas('error', self::NO_CONSENT_MESSAGE);

        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertSame('Legacy untouched content', $review->content);
        $this->assertNull($review->reviewConsent);
        $this->assertDatabaseMissing('review_consents', ['review_id' => $review->id]);
    }

    // -----------------------------------------------------------------------
    // 23, 24. Public surfaces exclude review after successful withdrawal
    // -----------------------------------------------------------------------

    public function test_successful_withdrawal_removes_review_from_public_reviews_index(): void
    {
        $review = $this->makeReview(['content' => 'WITHDRAWAL-WORKFLOW-REVIEWS-MARKER', 'is_published' => true]);
        $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $this->get(route('review.index'))->assertSee('WITHDRAWAL-WORKFLOW-REVIEWS-MARKER');

        $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $this->get(route('review.index'))->assertDontSee('WITHDRAWAL-WORKFLOW-REVIEWS-MARKER');
    }

    public function test_successful_withdrawal_removes_review_from_homepage(): void
    {
        $review = $this->makeReview(['content' => 'WITHDRAWAL-WORKFLOW-HOME-MARKER', 'is_published' => true]);
        $this->makeConsent($review);
        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ]);

        $this->get(route('home.index'))->assertDontSee('WITHDRAWAL-WORKFLOW-HOME-MARKER');
    }

    // -----------------------------------------------------------------------
    // 25, 26, 27. Role/guest protection
    // -----------------------------------------------------------------------

    public function test_unsupported_role_blocked_from_admin_routes(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review);
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review))->assertForbidden();
        $this->actingAs($tourist)->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ])->assertForbidden();
    }

    public function test_unsupported_role_blocked_from_manager_routes(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review);
        $tourist = $this->makeUser([Role::TOURIST]);

        $this->actingAs($tourist)->get(route('cabinet.manager.reviews.withdraw-consent.confirm', $review))->assertForbidden();
        $this->actingAs($tourist)->post(route('cabinet.manager.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ])->assertForbidden();
    }

    public function test_guest_is_blocked_from_withdrawal_routes(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review);

        $this->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $review))->assertRedirect(route('login'));
        $this->post(route('cabinet.admin.reviews.withdraw-consent', $review), [
            'withdrawal_confirmed' => '1',
        ])->assertRedirect(route('login'));
    }

    // -----------------------------------------------------------------------
    // 28. Admin/Manager wording/control parity
    // -----------------------------------------------------------------------

    public function test_admin_and_manager_confirmation_wording_parity(): void
    {
        $adminReview = $this->makeReview();
        $this->makeConsent($adminReview);
        $admin = $this->makeUser([Role::ADMIN]);

        $managerReview = $this->makeReview();
        $this->makeConsent($managerReview);
        $manager = $this->makeUser([Role::MANAGER]);

        $adminResponse = $this->actingAs($admin)->get(route('cabinet.admin.reviews.withdraw-consent.confirm', $adminReview));
        $managerResponse = $this->actingAs($manager)->get(route('cabinet.manager.reviews.withdraw-consent.confirm', $managerReview));

        $adminResponse->assertOk();
        $managerResponse->assertOk();

        $checkboxText = 'Я подтверждаю, что запрос автора на отзыв согласия получен, проверен и относится к этому отзыву.';
        $submitText = 'Зафиксировать отзыв согласия';

        $adminResponse->assertSee($checkboxText);
        $managerResponse->assertSee($checkboxText);
        $adminResponse->assertSee($submitText);
        $managerResponse->assertSee($submitText);
    }

    // -----------------------------------------------------------------------
    // Browser-QA correction: is_published validation error must be visibly
    // rendered near the publication toggle on the ordinary edit screen
    // (defect 1)
    // -----------------------------------------------------------------------

    public function test_admin_edit_screen_visibly_renders_is_published_validation_error(): void
    {
        $review = $this->makeReview();
        $admin = $this->makeUser([Role::ADMIN]);

        $errors = new \Illuminate\Support\ViewErrorBag();
        $errors = $errors->put('default', new \Illuminate\Support\MessageBag([
            'is_published' => self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE,
        ]));

        $response = $this->actingAs($admin)
            ->withSession(['errors' => $errors])
            ->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee(self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE);
    }

    public function test_manager_edit_screen_visibly_renders_is_published_validation_error(): void
    {
        $review = $this->makeReview();
        $manager = $this->makeUser([Role::MANAGER]);

        $errors = new \Illuminate\Support\ViewErrorBag();
        $errors = $errors->put('default', new \Illuminate\Support\MessageBag([
            'is_published' => self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE,
        ]));

        $response = $this->actingAs($manager)
            ->withSession(['errors' => $errors])
            ->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee(self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE);
    }

    public function test_admin_end_to_end_republish_attempt_after_withdrawal_shows_error_on_redisplay(): void
    {
        $review = $this->makeReview(['content' => 'Republish attempt content', 'is_published' => false]);
        $this->makeConsent($review, ['withdrawn_at' => now()->subMinute()]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->from(route('cabinet.admin.reviews.edit', $review))
            ->actingAs($admin)
            ->put(route('cabinet.admin.reviews.update', $review), [
                'name' => $review->name,
                'title' => 'Title',
                'content' => 'Republish attempt content',
                'gender' => 'boy',
                'is_published' => '1',
            ]);

        $response->assertSessionHasErrors('is_published');

        $followUp = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $followUp->assertOk();
        $followUp->assertSee(self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE);
    }

    // -----------------------------------------------------------------------
    // 29, 30. Normal updateReview publish after withdrawal is rejected
    // -----------------------------------------------------------------------

    public function test_admin_normal_publish_after_withdrawal_is_rejected(): void
    {
        $review = $this->makeReview(['content' => 'Publish-after-withdrawal content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subMinute()]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Publish-after-withdrawal content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('is_published');
        $errors = session('errors');
        $this->assertSame(self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE, $errors->first('is_published'));

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
    }

    public function test_manager_normal_publish_after_withdrawal_is_rejected(): void
    {
        $review = $this->makeReview(['content' => 'Publish-after-withdrawal content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['withdrawn_at' => now()->subMinute()]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Publish-after-withdrawal content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('is_published');
        $errors = session('errors');
        $this->assertSame(self::WITHDRAWAL_PUBLISH_BLOCK_MESSAGE, $errors->first('is_published'));

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertNotNull($consent->withdrawn_at);
    }

    // -----------------------------------------------------------------------
    // 31, 32. publication_conditions / legacy publication remain compatible
    // -----------------------------------------------------------------------

    public function test_publication_conditions_behavior_remains_compatible_after_lock_hardening(): void
    {
        $review = $this->makeReview(['content' => 'Conditions compatibility content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Conditions compatibility content',
            'gender' => 'boy',
            'is_published' => '1',
            'publication_conditions_satisfied' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $review->refresh();
        $consent->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNotNull($consent->publication_conditions_satisfied_at);
    }

    public function test_legacy_publication_remains_compatible_after_lock_hardening(): void
    {
        $review = $this->makeReview(['content' => 'Legacy compatibility content', 'is_published' => false]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Legacy compatibility content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent);
    }

    // -----------------------------------------------------------------------
    // Lock / structure regression coverage (SQLite :memory: cannot
    // deterministically exercise a real concurrent row-lock race, so this is
    // a narrow structural assertion instead of a fabricated multithreaded
    // test).
    // -----------------------------------------------------------------------

    public function test_admin_update_review_contains_authoritative_locked_consent_check(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php'));

        $this->assertStringContainsString('ReviewConsent::where(\'review_id\', $review->id)->lockForUpdate()->first()', $source);
        $this->assertMatchesRegularExpression(
            '/DB::transaction\(function \(\) use \([^)]*\) \{\s*\$lockedConsent = \$existingConsent[\s\S]*?lockForUpdate/',
            $source,
            'AdminController::updateReview must re-query ReviewConsent with lockForUpdate() inside its DB::transaction, before publication persistence.'
        );
    }

    public function test_manager_update_review_contains_authoritative_locked_consent_check(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Manager/ManagerController.php'));

        $this->assertStringContainsString('ReviewConsent::where(\'review_id\', $review->id)->lockForUpdate()->first()', $source);
        $this->assertMatchesRegularExpression(
            '/DB::transaction\(function \(\) use \([^)]*\) \{\s*\$lockedConsent = \$existingConsent[\s\S]*?lockForUpdate/',
            $source,
            'ManagerController::updateReview must re-query ReviewConsent with lockForUpdate() inside its DB::transaction, before publication persistence.'
        );
    }

    public function test_admin_withdraw_consent_uses_locked_consent(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php'));

        $this->assertMatchesRegularExpression(
            '/function withdrawConsent\([\s\S]*?lockForUpdate\(\)->first\(\)/',
            $source
        );
    }

    public function test_manager_withdraw_consent_uses_locked_consent(): void
    {
        $source = file_get_contents(app_path('Http/Controllers/Manager/ManagerController.php'));

        $this->assertMatchesRegularExpression(
            '/function withdrawConsent\([\s\S]*?lockForUpdate\(\)->first\(\)/',
            $source
        );
    }

    public function test_no_c5b_code_ever_clears_withdrawn_at(): void
    {
        $adminSource = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php'));
        $managerSource = file_get_contents(app_path('Http/Controllers/Manager/ManagerController.php'));

        $this->assertDoesNotMatchRegularExpression('/withdrawn_at\s*=\s*null/i', $adminSource);
        $this->assertDoesNotMatchRegularExpression('/withdrawn_at\s*=\s*null/i', $managerSource);
    }

    public function test_update_review_publication_conditions_write_uses_locked_consent_instance(): void
    {
        $adminSource = file_get_contents(app_path('Http/Controllers/Admin/AdminController.php'));
        $managerSource = file_get_contents(app_path('Http/Controllers/Manager/ManagerController.php'));

        $this->assertStringContainsString('$lockedConsent->publication_conditions_satisfied_at = $resultingSatisfiedAt;', $adminSource);
        $this->assertStringContainsString('$lockedConsent->publication_conditions_satisfied_at = $resultingSatisfiedAt;', $managerSource);
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
            'name' => 'Withdrawal Workflow Tourist',
            'title' => null,
            'content' => 'Default withdrawal workflow content.',
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
            'user_agreement_version' => 'withdrawal-workflow-test-user-agreement',
            'personal_data_consent_version' => 'withdrawal-workflow-test-personal-data-consent',
            'review_publication_consent_version' => 'withdrawal-workflow-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'withdrawal-workflow-test-payload-' . $review->id),
            'withdrawn_at' => null,
        ], $overrides));
    }
}
