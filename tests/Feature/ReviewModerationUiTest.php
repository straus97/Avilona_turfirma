<?php

namespace Tests\Feature;

use App\Models\ReviewConsent;
use App\Models\Reviews;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\MessageBag;
use Illuminate\Support\ViewErrorBag;
use Tests\TestCase;

/**
 * Stage 13 C4B2: Admin/Manager moderation UI presentation.
 *
 * Проверяет только представление (Blade) экрана редактирования отзыва:
 * неизменяемое отображение имени автора, блок условий и запретов публикации,
 * безопасное состояние транзитного чекбокса подтверждения и отображение
 * ошибки валидации. Серверная логика уже покрыта
 * ReviewModerationEnforcementTest.php и здесь повторно не проверяется.
 */
class ReviewModerationUiTest extends TestCase
{
    use RefreshDatabase;

    private const CONFIRMATION_TEXT = 'Условия и запреты публикации проверены и соблюдены в итоговом тексте отзыва.';

    private const CONFIRMATION_HEADING = 'Условия и запреты публикации, указанные автором';

    // -----------------------------------------------------------------------
    // 1, 2. Author name display-only
    // -----------------------------------------------------------------------

    public function test_admin_author_name_is_display_only(): void
    {
        $review = $this->makeReview(['name' => 'Иван Тестовый']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('Иван Тестовый');
        $this->assertStringNotContainsString('name="name"', $response->getContent());
    }

    public function test_manager_author_name_is_display_only(): void
    {
        $review = $this->makeReview(['name' => 'Иван Тестовый']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('Иван Тестовый');
        $this->assertStringNotContainsString('name="name"', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // 3, 4. Conditions block with confirmation checkbox
    // -----------------------------------------------------------------------

    public function test_admin_conditions_block_renders_with_confirmation_checkbox(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee(self::CONFIRMATION_HEADING);
        $response->assertSee('Не публиковать номер телефона.');
        $response->assertSee(self::CONFIRMATION_TEXT);
        $this->assertConfirmationCheckboxContract($response->getContent());
    }

    public function test_manager_conditions_block_renders_with_confirmation_checkbox(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee(self::CONFIRMATION_HEADING);
        $response->assertSee('Не публиковать номер телефона.');
        $response->assertSee(self::CONFIRMATION_TEXT);
        $this->assertConfirmationCheckboxContract($response->getContent());
    }

    // -----------------------------------------------------------------------
    // 5. HTML escaping of publication_conditions
    // -----------------------------------------------------------------------

    public function test_admin_publication_conditions_are_escaped(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => '<script>alert(1)</script>']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    public function test_manager_publication_conditions_are_escaped(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => '<script>alert(1)</script>']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('<script>alert(1)</script>');
        $this->assertStringNotContainsString('<script>alert(1)</script>', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // 6. No ReviewConsent
    // -----------------------------------------------------------------------

    public function test_admin_no_conditions_block_when_review_consent_is_null(): void
    {
        $review = $this->makeReview();
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee(self::CONFIRMATION_HEADING);
        $this->assertStringNotContainsString('publication_conditions_satisfied', $response->getContent());
    }

    public function test_manager_no_conditions_block_when_review_consent_is_null(): void
    {
        $review = $this->makeReview();
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee(self::CONFIRMATION_HEADING);
        $this->assertStringNotContainsString('publication_conditions_satisfied', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // 7. Blank publication_conditions
    // -----------------------------------------------------------------------

    public function test_admin_no_conditions_block_when_publication_conditions_is_blank(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => '   ']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee(self::CONFIRMATION_HEADING);
        $this->assertStringNotContainsString('publication_conditions_satisfied', $response->getContent());
    }

    public function test_manager_no_conditions_block_when_publication_conditions_is_blank(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => '']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee(self::CONFIRMATION_HEADING);
        $this->assertStringNotContainsString('publication_conditions_satisfied', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // 8. Stored satisfaction timestamp does not pre-check the checkbox
    // -----------------------------------------------------------------------

    public function test_admin_checkbox_is_not_prechecked_from_stored_satisfaction_timestamp(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'publication_conditions' => 'Не публиковать номер телефона.',
            'publication_conditions_satisfied_at' => now()->subDay(),
        ]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $this->assertConfirmationCheckboxContract($response->getContent(), checked: false);
    }

    public function test_manager_checkbox_is_not_prechecked_from_stored_satisfaction_timestamp(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'publication_conditions' => 'Не публиковать номер телефона.',
            'publication_conditions_satisfied_at' => now()->subDay(),
        ]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $this->assertConfirmationCheckboxContract($response->getContent(), checked: false);
    }

    // -----------------------------------------------------------------------
    // 9. Old input does not auto-check the checkbox
    // -----------------------------------------------------------------------

    public function test_admin_checkbox_is_not_restored_checked_from_old_input(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['_old_input' => ['publication_conditions_satisfied' => '1']])
            ->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $this->assertConfirmationCheckboxContract($response->getContent(), checked: false);
    }

    public function test_manager_checkbox_is_not_restored_checked_from_old_input(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)
            ->withSession(['_old_input' => ['publication_conditions_satisfied' => '1']])
            ->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $this->assertConfirmationCheckboxContract($response->getContent(), checked: false);
    }

    // -----------------------------------------------------------------------
    // 10. Validation error rendering
    // -----------------------------------------------------------------------

    public function test_admin_validation_error_is_rendered_near_confirmation_checkbox(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)
            ->withSession(['errors' => $this->makePublicationConditionsErrorBag()])
            ->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('Перед публикацией подтвердите, что условия и запреты публикации соблюдены в итоговом тексте отзыва.');
    }

    public function test_manager_validation_error_is_rendered_near_confirmation_checkbox(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)
            ->withSession(['errors' => $this->makePublicationConditionsErrorBag()])
            ->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertSee('Перед публикацией подтвердите, что условия и запреты публикации соблюдены в итоговом тексте отзыва.');
    }

    // -----------------------------------------------------------------------
    // 11. Existing moderation fields remain
    // -----------------------------------------------------------------------

    public function test_admin_existing_moderation_fields_still_render(): void
    {
        $review = $this->makeReview(['title' => 'Заголовок', 'content' => 'Текст отзыва']);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $this->assertStringContainsString('name="title"', $response->getContent());
        $this->assertStringContainsString('name="content"', $response->getContent());
        $this->assertStringContainsString('name="gender"', $response->getContent());
        $this->assertStringContainsString('name="is_published"', $response->getContent());
    }

    public function test_manager_existing_moderation_fields_still_render(): void
    {
        $review = $this->makeReview(['title' => 'Заголовок', 'content' => 'Текст отзыва']);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $this->assertStringContainsString('name="title"', $response->getContent());
        $this->assertStringContainsString('name="content"', $response->getContent());
        $this->assertStringContainsString('name="gender"', $response->getContent());
        $this->assertStringContainsString('name="is_published"', $response->getContent());
    }

    // -----------------------------------------------------------------------
    // 12. Private consent identity values are not exposed
    // -----------------------------------------------------------------------

    public function test_admin_private_consent_identity_fields_are_not_exposed(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'publication_conditions' => 'Не публиковать номер телефона.',
            'consent_full_name' => 'Секретное Полное Имя',
            'consent_email' => 'secret-evidence@example.com',
        ]);
        $admin = $this->makeUser([Role::ADMIN]);

        $response = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee('Секретное Полное Имя');
        $response->assertDontSee('secret-evidence@example.com');
    }

    public function test_manager_private_consent_identity_fields_are_not_exposed(): void
    {
        $review = $this->makeReview();
        $this->makeConsent($review, [
            'publication_conditions' => 'Не публиковать номер телефона.',
            'consent_full_name' => 'Секретное Полное Имя',
            'consent_email' => 'secret-evidence@example.com',
        ]);
        $manager = $this->makeUser([Role::MANAGER]);

        $response = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $review));

        $response->assertOk();
        $response->assertDontSee('Секретное Полное Имя');
        $response->assertDontSee('secret-evidence@example.com');
    }

    // -----------------------------------------------------------------------
    // 13. Admin/Manager parity
    // -----------------------------------------------------------------------

    public function test_admin_and_manager_confirmation_wording_and_field_name_match(): void
    {
        $adminReview = $this->makeReview();
        $this->makeConsent($adminReview, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $admin = $this->makeUser([Role::ADMIN]);

        $managerReview = $this->makeReview();
        $this->makeConsent($managerReview, ['publication_conditions' => 'Не публиковать номер телефона.']);
        $manager = $this->makeUser([Role::MANAGER]);

        $adminResponse = $this->actingAs($admin)->get(route('cabinet.admin.reviews.edit', $adminReview));
        $managerResponse = $this->actingAs($manager)->get(route('cabinet.manager.reviews.edit', $managerReview));

        $adminResponse->assertOk();
        $managerResponse->assertOk();

        $adminResponse->assertSee(self::CONFIRMATION_TEXT);
        $managerResponse->assertSee(self::CONFIRMATION_TEXT);

        $this->assertConfirmationCheckboxContract($adminResponse->getContent(), checked: false);
        $this->assertConfirmationCheckboxContract($managerResponse->getContent(), checked: false);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    /**
     * Extracts the opening <input> tag for the transient
     * publication_conditions_satisfied checkbox and asserts its semantic
     * contract (type/name/id/value, and optionally checked state), tolerant
     * of harmless attribute-class whitespace differences (e.g. the trailing
     * space left by `@error(...) is-invalid @enderror` when no error is
     * present).
     */
    private function assertConfirmationCheckboxContract(string $html, ?bool $checked = null): void
    {
        $found = preg_match(
            '/<input\b[^>]*\bname="publication_conditions_satisfied"[^>]*>/i',
            $html,
            $matches
        );

        $this->assertSame(1, $found, 'The publication_conditions_satisfied checkbox <input> tag was not found.');

        $tag = $matches[0];

        $this->assertStringContainsString('type="checkbox"', $tag);
        $this->assertStringContainsString('id="publication_conditions_satisfied"', $tag);
        $this->assertStringContainsString('value="1"', $tag);

        if ($checked === false) {
            $this->assertDoesNotMatchRegularExpression('/\bchecked\b/i', $tag);
        } elseif ($checked === true) {
            $this->assertMatchesRegularExpression('/\bchecked\b/i', $tag);
        }
    }

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
            'name' => 'Moderation UI Tourist',
            'title' => null,
            'content' => 'Default moderation UI content.',
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
            'user_agreement_version' => 'moderation-ui-test-user-agreement',
            'personal_data_consent_version' => 'moderation-ui-test-personal-data-consent',
            'review_publication_consent_version' => 'moderation-ui-test-review-publication-consent',
            'publication_scope' => ['name', 'content'],
            'publication_conditions' => null,
            'review_payload_sha256' => hash('sha256', 'moderation-ui-test-payload-' . $review->id),
        ], $overrides));

        // publication_conditions_satisfied_at is intentionally excluded from
        // ReviewConsent::$fillable, so it must be set via direct attribute
        // assignment rather than mass assignment.
        $consent->publication_conditions_satisfied_at = $satisfiedAt;
        $consent->save();

        return $consent;
    }

    private function makePublicationConditionsErrorBag(): ViewErrorBag
    {
        $bag = new MessageBag([
            'publication_conditions_satisfied' => 'Перед публикацией подтвердите, что условия и запреты публикации соблюдены в итоговом тексте отзыва.',
        ]);

        return (new ViewErrorBag())->put('default', $bag);
    }
}
