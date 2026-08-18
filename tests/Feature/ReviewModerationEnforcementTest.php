<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Stage 13 C4B1: server-side moderation enforcement.
 *
 * Проверяет только серверную логику Admin/Manager updateReview: имя отзыва
 * неизменяемо модерацией, маркер редактирования контента модератором,
 * gate публикации по publication_conditions_satisfied_at, инвалидацию
 * устаревшего подтверждения при изменении контента, атомарность неудачного
 * запроса и null-safe поведение для legacy-отзывов без ReviewConsent. Не
 * проверяет UI модератора и публичное раскрытие правок (C4B2/C4C).
 */
class ReviewModerationEnforcementTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1, 2. Immutable name
    // -----------------------------------------------------------------------

    public function test_admin_cannot_change_review_name_via_moderation_update(): void
    {
        $review = $this->makeReview(['name' => 'Original Tourist Name']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => 'Tampered Name',
            'title' => 'Some Title',
            'content' => $review->content,
            'gender' => 'boy',
            'is_published' => '0',
        ]);

        $response->assertRedirect(route('cabinet.admin.content'));
        $response->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame('Original Tourist Name', $review->name);
    }

    public function test_manager_cannot_change_review_name_via_moderation_update(): void
    {
        $review = $this->makeReview(['name' => 'Original Tourist Name']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => 'Tampered Name',
            'title' => 'Some Title',
            'content' => $review->content,
            'gender' => 'boy',
            'is_published' => '0',
        ]);

        $response->assertRedirect(route('cabinet.manager.content'));
        $response->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertSame('Original Tourist Name', $review->name);
    }

    // -----------------------------------------------------------------------
    // 3, 4. Content edit marker
    // -----------------------------------------------------------------------

    public function test_admin_changing_content_sets_moderator_edited_marker(): void
    {
        $review = $this->makeReview(['content' => 'Original content']);
        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Changed content',
            'gender' => 'boy',
            'is_published' => '0',
        ])->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertTrue((bool) $review->is_moderator_edited);
        $this->assertNotNull($review->moderator_edited_at);
        $this->assertSame('Changed content', $review->content);
    }

    public function test_manager_changing_content_sets_moderator_edited_marker(): void
    {
        $review = $this->makeReview(['content' => 'Original content']);
        $manager = $this->makeUser([Role::MANAGER]);

        $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Changed content',
            'gender' => 'boy',
            'is_published' => '0',
        ])->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertTrue((bool) $review->is_moderator_edited);
        $this->assertNotNull($review->moderator_edited_at);
        $this->assertSame('Changed content', $review->content);
    }

    // -----------------------------------------------------------------------
    // 5. No-op content update
    // -----------------------------------------------------------------------

    public function test_admin_unchanged_content_does_not_mark_review_as_edited(): void
    {
        $review = $this->makeReview(['content' => 'Stable content']);
        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Stable content',
            'gender' => 'boy',
            'is_published' => '1',
        ])->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
    }

    public function test_manager_unchanged_content_does_not_mark_review_as_edited(): void
    {
        $review = $this->makeReview(['content' => 'Stable content']);
        $manager = $this->makeUser([Role::MANAGER]);

        $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Stable content',
            'gender' => 'boy',
            'is_published' => '1',
        ])->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
    }

    // -----------------------------------------------------------------------
    // 6. Sticky marker / latest timestamp
    // -----------------------------------------------------------------------

    public function test_marker_stays_sticky_and_timestamp_advances_on_further_content_edit(): void
    {
        $review = $this->makeReview(['content' => 'First content']);
        $review->is_moderator_edited = true;
        $review->moderator_edited_at = now()->subDays(3);
        $review->save();
        $firstTimestamp = $review->moderator_edited_at;

        $admin = $this->makeUser([Role::ADMIN]);

        $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Second content',
            'gender' => 'boy',
            'is_published' => '0',
        ])->assertSessionHasNoErrors();

        $review->refresh();
        $this->assertTrue((bool) $review->is_moderator_edited);
        $this->assertNotNull($review->moderator_edited_at);
        $this->assertTrue($review->moderator_edited_at->greaterThan($firstTimestamp));
    }

    // -----------------------------------------------------------------------
    // 7. Legacy review without ReviewConsent
    // -----------------------------------------------------------------------

    public function test_admin_can_publish_legacy_review_without_review_consent(): void
    {
        $review = $this->makeReview(['content' => 'Legacy content']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Legacy content updated',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cabinet.admin.content'));

        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent);
        $this->assertDatabaseMissing('review_consents', ['review_id' => $review->id]);
    }

    public function test_manager_can_publish_legacy_review_without_review_consent(): void
    {
        $review = $this->makeReview(['content' => 'Legacy content']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Legacy content updated',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $response->assertRedirect(route('cabinet.manager.content'));

        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent);
        $this->assertDatabaseMissing('review_consents', ['review_id' => $review->id]);
    }

    // -----------------------------------------------------------------------
    // 8. Blank publication_conditions
    // -----------------------------------------------------------------------

    public function test_admin_publish_with_blank_conditions_does_not_require_satisfaction(): void
    {
        $review = $this->makeReview(['content' => 'Blank conditions content']);
        $this->makeConsent($review, ['publication_conditions' => '']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Blank conditions content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent->publication_conditions_satisfied_at);
    }

    public function test_manager_publish_with_blank_conditions_does_not_require_satisfaction(): void
    {
        $review = $this->makeReview(['content' => 'Blank conditions content']);
        $this->makeConsent($review, ['publication_conditions' => '  ']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Blank conditions content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasNoErrors();
        $review->refresh();
        $this->assertTrue((bool) $review->is_published);
        $this->assertNull($review->reviewConsent->publication_conditions_satisfied_at);
    }

    // -----------------------------------------------------------------------
    // 9. Publish blocked without fresh confirmation
    // -----------------------------------------------------------------------

    public function test_admin_publish_is_blocked_without_fresh_confirmation_when_conditions_exist(): void
    {
        $review = $this->makeReview(['content' => 'Gated content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Gated content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertSame('Gated content', $review->content);
        $this->assertNull($consent->publication_conditions_satisfied_at);
    }

    public function test_manager_publish_is_blocked_without_fresh_confirmation_when_conditions_exist(): void
    {
        $review = $this->makeReview(['content' => 'Gated content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Gated content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertSame('Gated content', $review->content);
        $this->assertNull($consent->publication_conditions_satisfied_at);
    }

    // -----------------------------------------------------------------------
    // 10. Fresh confirmation permits publication
    // -----------------------------------------------------------------------

    public function test_admin_fresh_confirmation_permits_publication(): void
    {
        $review = $this->makeReview(['content' => 'Confirmed content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Confirmed content',
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

    public function test_manager_fresh_confirmation_permits_publication(): void
    {
        $review = $this->makeReview(['content' => 'Confirmed content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Confirmed content',
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

    // -----------------------------------------------------------------------
    // 11. Stale confirmation + content change (published state) rejected atomically
    // -----------------------------------------------------------------------

    public function test_admin_stale_confirmation_with_content_change_rejects_when_staying_published(): void
    {
        $review = $this->makeReview(['content' => 'Old content', 'is_published' => true]);
        $consent = $this->makeConsent($review, [
            'publication_conditions' => 'Remove phone numbers.',
            'publication_conditions_satisfied_at' => now()->subDay(),
        ]);
        $oldSatisfiedAt = $consent->publication_conditions_satisfied_at;
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'New content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');

        $review->refresh();
        $consent->refresh();
        $this->assertSame('Old content', $review->content);
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
        $this->assertTrue((bool) $review->is_published);
        $this->assertNotNull($consent->publication_conditions_satisfied_at);
        $this->assertTrue($consent->publication_conditions_satisfied_at->equalTo($oldSatisfiedAt));
    }

    public function test_manager_stale_confirmation_with_content_change_rejects_when_staying_published(): void
    {
        $review = $this->makeReview(['content' => 'Old content', 'is_published' => true]);
        $consent = $this->makeConsent($review, [
            'publication_conditions' => 'Remove phone numbers.',
            'publication_conditions_satisfied_at' => now()->subDay(),
        ]);
        $oldSatisfiedAt = $consent->publication_conditions_satisfied_at;
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'New content',
            'gender' => 'boy',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');

        $review->refresh();
        $consent->refresh();
        $this->assertSame('Old content', $review->content);
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
        $this->assertTrue((bool) $review->is_published);
        $this->assertNotNull($consent->publication_conditions_satisfied_at);
        $this->assertTrue($consent->publication_conditions_satisfied_at->equalTo($oldSatisfiedAt));
    }

    // -----------------------------------------------------------------------
    // 12. Content change while explicitly unpublished clears stale timestamp
    // -----------------------------------------------------------------------

    public function test_admin_content_change_with_explicit_unpublish_clears_stale_timestamp(): void
    {
        $review = $this->makeReview(['content' => 'Old content', 'is_published' => true]);
        $consent = $this->makeConsent($review, [
            'publication_conditions' => 'Remove phone numbers.',
            'publication_conditions_satisfied_at' => now()->subDay(),
        ]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'New content',
            'gender' => 'boy',
            // is_published omitted -> resolves to false
        ]);

        $response->assertSessionHasNoErrors();

        $review->refresh();
        $consent->refresh();
        $this->assertSame('New content', $review->content);
        $this->assertFalse((bool) $review->is_published);
        $this->assertTrue((bool) $review->is_moderator_edited);
        $this->assertNotNull($review->moderator_edited_at);
        $this->assertNull($consent->publication_conditions_satisfied_at);
    }

    // -----------------------------------------------------------------------
    // 13. Content change + fresh confirmation + publish
    // -----------------------------------------------------------------------

    public function test_admin_content_change_with_fresh_confirmation_publishes(): void
    {
        $review = $this->makeReview(['content' => 'Old content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'New content',
            'gender' => 'boy',
            'is_published' => '1',
            'publication_conditions_satisfied' => '1',
        ]);

        $response->assertSessionHasNoErrors();

        $review->refresh();
        $consent->refresh();
        $this->assertSame('New content', $review->content);
        $this->assertTrue((bool) $review->is_published);
        $this->assertTrue((bool) $review->is_moderator_edited);
        $this->assertNotNull($review->moderator_edited_at);
        $this->assertNotNull($consent->publication_conditions_satisfied_at);
    }

    // -----------------------------------------------------------------------
    // 14. Invalid confirmation values
    // -----------------------------------------------------------------------

    public function test_present_but_unaccepted_confirmation_value_fails_accepted_validation(): void
    {
        // is_published stays false here on purpose: the publish gate itself
        // would not reject this request (no publication attempted), so a
        // rejection can only come from the `accepted` validation rule on
        // publication_conditions_satisfied itself, isolating that rule from
        // the separate publish-gate ValidationException.
        $review = $this->makeReview(['content' => 'Same content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->put(route('cabinet.admin.reviews.update', $review), [
            'name' => $review->name,
            'title' => 'Title',
            'content' => 'Same content',
            'gender' => 'boy',
            'is_published' => '0',
            'publication_conditions_satisfied' => '0',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');

        $review->refresh();
        $consent->refresh();
        $this->assertFalse((bool) $review->is_published);
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
        $this->assertNull($consent->publication_conditions_satisfied_at);
    }

    // -----------------------------------------------------------------------
    // 15. Failed gated request does not partially persist any C4 state
    // -----------------------------------------------------------------------

    public function test_manager_failed_publish_gate_does_not_partially_persist_state(): void
    {
        $review = $this->makeReview(['content' => 'Untouched content', 'is_published' => false]);
        $consent = $this->makeConsent($review, ['publication_conditions' => 'Remove phone numbers.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->put(route('cabinet.manager.reviews.update', $review), [
            'name' => 'Tampered Name',
            'title' => 'Tampered Title',
            'content' => 'Attempted new content',
            'gender' => 'girl',
            'is_published' => '1',
        ]);

        $response->assertSessionHasErrors('publication_conditions_satisfied');

        $review->refresh();
        $consent->refresh();
        $this->assertSame('Untouched content', $review->content);
        $this->assertFalse((bool) $review->is_moderator_edited);
        $this->assertNull($review->moderator_edited_at);
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
            'name' => 'Moderation Enforcement Tourist',
            'title' => null,
            'content' => 'Default moderation enforcement content.',
            'image' => null,
            'is_published' => false,
        ], $overrides));
    }

    private function makeConsent(Reviews $review, array $overrides = []): ReviewConsent
    {
        $satisfiedAt = array_key_exists('publication_conditions_satisfied_at', $overrides)
            ? $overrides['publication_conditions_satisfied_at']
            : null;
        unset($overrides['publication_conditions_satisfied_at']);

        $consent = ReviewConsent::create(array_merge([
            'review_id' => $review->id,
            'evidence_id' => (string) \Illuminate\Support\Str::uuid(),
            'consent_full_name' => 'Evidence Full Name',
            'consent_email' => 'evidence@example.com',
            'user_agreement_accepted_at' => now(),
            'personal_data_consent_accepted_at' => now(),
            'review_publication_consent_accepted_at' => now(),
            'user_agreement_version' => 'moderation-enforcement-test-user-agreement',
            'personal_data_consent_version' => 'moderation-enforcement-test-personal-data-consent',
            'review_publication_consent_version' => 'moderation-enforcement-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'moderation-enforcement-test-payload-' . $review->id),
        ], $overrides));

        // publication_conditions_satisfied_at is intentionally excluded from
        // ReviewConsent::$fillable, so it must be set via direct attribute
        // assignment rather than mass assignment.
        $consent->publication_conditions_satisfied_at = $satisfiedAt;
        $consent->save();

        return $consent;
    }
}
