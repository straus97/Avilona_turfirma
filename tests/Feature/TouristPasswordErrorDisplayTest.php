<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use DOMDocument;
use DOMElement;
use DOMXPath;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Tests\TestCase;

/**
 * AO-FIX-02 (AO-11) — ошибки смены пароля на странице настроек туриста.
 *
 * PasswordController::update() валидирует в именованный error bag
 * `updatePassword`. Форма смены пароля обязана читать именно этот bag, а
 * ошибки других форм страницы (default bag) не должны попадать под поля пароля.
 */
class TouristPasswordErrorDisplayTest extends TestCase
{
    use RefreshDatabase;

    private const OLD_PASSWORD = 'Old-Passw0rd-AO11';
    private const NEW_PASSWORD = 'New-Passw0rd-AO11';

    public function test_successful_change_updates_hash_and_shows_status_without_errors(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->submitPasswordForm($tourist, [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $tourist->fresh()->password));
        $this->assertStringContainsString('Пароль успешно изменен!', $html);
        $this->assertSame(0, $this->passwordForm($html)->query('.//*[contains(@class,"invalid-feedback")]')->length);
        $this->assertSame(0, $this->passwordForm($html)->query('.//*[contains(@class,"is-invalid")]')->length);
        $this->assertPlaintextAbsent($html);
    }

    public function test_incorrect_current_password_is_shown_under_current_password_input(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->submitPasswordForm($tourist, [
            'current_password' => 'Wrong-Passw0rd-AO11',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $this->assertFieldFeedback($html, 'current_password');
        $this->assertFieldHasNoFeedback($html, 'password');
        $this->assertPasswordUnchanged($tourist);
        $this->assertPlaintextAbsent($html, ['Wrong-Passw0rd-AO11']);
    }

    public function test_mismatched_confirmation_is_shown_under_new_password_input(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->submitPasswordForm($tourist, [
            'current_password' => self::OLD_PASSWORD,
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => 'Different-Passw0rd-AO11',
        ]);

        $this->assertFieldFeedback($html, 'password');
        $this->assertFieldHasNoFeedback($html, 'current_password');
        $this->assertPasswordUnchanged($tourist);
        $this->assertPlaintextAbsent($html, ['Different-Passw0rd-AO11']);
    }

    public function test_invalid_new_password_is_shown_under_new_password_input(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->submitPasswordForm($tourist, [
            'current_password' => self::OLD_PASSWORD,
            'password' => 'short1',
            'password_confirmation' => 'short1',
        ]);

        $this->assertFieldFeedback($html, 'password');
        $this->assertFieldHasNoFeedback($html, 'current_password');
        $this->assertPasswordUnchanged($tourist);
        $this->assertPlaintextAbsent($html, ['short1']);
    }

    public function test_missing_values_are_reported_next_to_each_required_input(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->submitPasswordForm($tourist, []);

        $this->assertFieldFeedback($html, 'current_password');
        $this->assertFieldFeedback($html, 'password');
        $this->assertPasswordUnchanged($tourist);
    }

    public function test_errors_live_in_the_named_bag_not_in_the_default_bag(): void
    {
        $tourist = $this->makeTourist();

        $this->actingAs($tourist)
            ->from(route('cabinet.settings'))
            ->put(route('password.update'), [
                'current_password' => 'Wrong-Passw0rd-AO11',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('cabinet.settings'))
            ->assertSessionHasErrorsIn('updatePassword', ['current_password']);

        $this->assertFalse(session('errors')->getBag('default')->any());
        $this->assertPasswordUnchanged($tourist);
    }

    public function test_delete_account_error_does_not_leak_under_password_form_inputs(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->actingAs($tourist)
            ->from(route('cabinet.settings'))
            ->followingRedirects()
            ->delete(route('cabinet.settings.destroy-account'), ['password' => 'Wrong-Passw0rd-AO11'])
            ->assertOk()
            ->getContent();

        // Ошибка удаления аккаунта (default bag, ключ `password`) не должна
        // отображаться под полем «Новый пароль» формы смены пароля.
        $form = $this->passwordForm($html);
        $this->assertSame(0, $form->query('.//*[contains(@class,"invalid-feedback")]')->length);
        $this->assertSame(0, $form->query('.//*[contains(@class,"is-invalid")]')->length);
        $this->assertNotNull(User::query()->find($tourist->id));
    }

    public function test_password_errors_do_not_reach_notification_or_delete_forms(): void
    {
        $tourist = $this->makeTourist();

        $html = $this->submitPasswordForm($tourist, [
            'current_password' => 'Wrong-Passw0rd-AO11',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ]);

        $xpath = $this->xpath($html);
        $outside = $xpath->query('//form[not(@id="passwordForm")]//*[contains(@class,"is-invalid") or contains(@class,"invalid-feedback")]');
        $this->assertSame(0, $outside->length);
    }

    public function test_guest_cannot_use_password_update_or_settings_page(): void
    {
        $this->put(route('password.update'), [
            'current_password' => 'x',
            'password' => self::NEW_PASSWORD,
            'password_confirmation' => self::NEW_PASSWORD,
        ])->assertRedirect(route('login'));

        $this->get(route('cabinet.settings'))->assertRedirect(route('login'));
    }

    public function test_non_tourist_does_not_get_tourist_password_form(): void
    {
        $manager = $this->makeUser(Role::MANAGER);

        $response = $this->actingAs($manager)->get(route('cabinet.settings'));

        $response->assertRedirect();
        $this->assertStringNotContainsString('id="passwordForm"', (string) $response->getContent());
    }

    public function test_forced_password_change_flow_is_unchanged(): void
    {
        $tourist = $this->makeTourist(['password_change_required' => true]);

        $this->actingAs($tourist)->get(route('cabinet.settings'))
            ->assertRedirect(route('password.change'));

        // Неверный текущий пароль: ошибка в default bag (контракт формы принудительной смены).
        $this->actingAs($tourist)
            ->from(route('password.change'))
            ->post(route('password.change.update'), [
                'current_password' => 'Wrong-Passw0rd-AO11',
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('password.change'))
            ->assertSessionHasErrors('current_password');

        $this->assertTrue((bool) $tourist->fresh()->password_change_required);
        $this->assertPasswordUnchanged($tourist);

        $this->actingAs($tourist)
            ->post(route('password.change.update'), [
                'current_password' => self::OLD_PASSWORD,
                'password' => self::NEW_PASSWORD,
                'password_confirmation' => self::NEW_PASSWORD,
            ])
            ->assertRedirect(route('cabinet.dashboard'))
            ->assertSessionHasNoErrors();

        $fresh = $tourist->fresh();
        $this->assertFalse((bool) $fresh->password_change_required);
        $this->assertTrue(Hash::check(self::NEW_PASSWORD, $fresh->password));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /**
     * Отправляет форму смены пароля со страницы настроек и возвращает HTML
     * страницы после редиректа.
     */
    private function submitPasswordForm(User $user, array $payload): string
    {
        return $this->actingAs($user)
            ->from(route('cabinet.settings'))
            ->followingRedirects()
            ->put(route('password.update'), $payload)
            ->assertOk()
            ->getContent();
    }

    private function assertFieldFeedback(string $html, string $field): void
    {
        $input = $this->xpath($html)->query('//form[@id="passwordForm"]//input[@id="' . $field . '"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $input, "Input #{$field} not found");
        $this->assertStringContainsString('is-invalid', $input->getAttribute('class'), "#{$field} is not marked invalid");

        $feedback = $input->nextSibling;
        while ($feedback !== null && ! ($feedback instanceof DOMElement)) {
            $feedback = $feedback->nextSibling;
        }
        $this->assertInstanceOf(DOMElement::class, $feedback, "No feedback element after #{$field}");
        $this->assertStringContainsString('invalid-feedback', $feedback->getAttribute('class'));
        $this->assertNotSame('', trim($feedback->textContent), "Empty feedback for #{$field}");
    }

    private function assertFieldHasNoFeedback(string $html, string $field): void
    {
        $input = $this->xpath($html)->query('//form[@id="passwordForm"]//input[@id="' . $field . '"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $input);
        $this->assertStringNotContainsString('is-invalid', $input->getAttribute('class'), "#{$field} unexpectedly invalid");
    }

    private function assertPasswordUnchanged(User $user): void
    {
        $fresh = $user->fresh();
        $this->assertTrue(Hash::check(self::OLD_PASSWORD, $fresh->password));
        $this->assertFalse(Hash::check(self::NEW_PASSWORD, $fresh->password));
    }

    /**
     * @param list<string> $extra
     */
    private function assertPlaintextAbsent(string $html, array $extra = []): void
    {
        foreach (array_merge([self::OLD_PASSWORD, self::NEW_PASSWORD], $extra) as $secret) {
            $this->assertStringNotContainsString($secret, $html);
            $this->assertStringNotContainsString($secret, json_encode(session()->all()));
        }
    }

    private function passwordForm(string $html): DOMXPath
    {
        $xpath = $this->xpath($html);
        $form = $xpath->query('//form[@id="passwordForm"]')->item(0);
        $this->assertInstanceOf(DOMElement::class, $form, 'Password form not found');

        $doc = new DOMDocument();
        $doc->appendChild($doc->importNode($form, true));

        return new DOMXPath($doc);
    }

    private function xpath(string $html): DOMXPath
    {
        $doc = new DOMDocument();
        $previous = libxml_use_internal_errors(true);
        $doc->loadHTML('<?xml encoding="utf-8" ?>' . $html);
        libxml_clear_errors();
        libxml_use_internal_errors($previous);

        return new DOMXPath($doc);
    }

    private function makeTourist(array $attributes = []): User
    {
        return $this->makeUser(Role::TOURIST, $attributes);
    }

    private function makeUser(string $roleName, array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create(array_merge([
            'password' => Hash::make(self::OLD_PASSWORD),
            'password_change_required' => false,
        ], $attributes));
        $user->roles()->attach($role->id);

        return $user;
    }
}
