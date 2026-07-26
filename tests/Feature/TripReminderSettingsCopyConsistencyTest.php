<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: копия trip_reminders на странице настроек туриста должна отражать
 * реальную политику SendTripReminders (окна 1/3/7/14 дней), а не устаревшее
 * «за 7 дней». Тест не переисследует сам SendTripReminders (это покрыто
 * TripReminderEmailPreferenceTest) — только копию и состояние чекбокса на
 * странице cabinet.settings.
 */
class TripReminderSettingsCopyConsistencyTest extends TestCase
{
    use RefreshDatabase;

    private const NEW_COPY = 'Напоминания за 14 и 7 дней, за 3 дня и за 1 день до вылета';
    private const OLD_COPY = 'Напоминание за 7 дней до вылета';

    // -----------------------------------------------------------------------
    // 1-3. Туристская страница настроек открывается и содержит новую копию,
    // но не содержит устаревшую
    // -----------------------------------------------------------------------

    public function test_tourist_settings_page_shows_updated_reminder_copy(): void
    {
        $tourist = $this->makeTourist();

        $response = $this->actingAs($tourist)->get(route('cabinet.settings'));

        $response->assertOk();
        $response->assertSee(self::NEW_COPY);
        $response->assertDontSee(self::OLD_COPY);
    }

    // -----------------------------------------------------------------------
    // 4. Контрол trip_reminders сохраняет id и name
    // -----------------------------------------------------------------------

    public function test_trip_reminders_control_keeps_id_and_name(): void
    {
        $tourist = $this->makeTourist();

        $response = $this->actingAs($tourist)->get(route('cabinet.settings'));

        $response->assertOk();
        $response->assertSee('id="tripReminders"', false);
        $response->assertSee('name="trip_reminders"', false);
    }

    // -----------------------------------------------------------------------
    // 5. Настройки null/по умолчанию — чекбокс отмечен
    // -----------------------------------------------------------------------

    public function test_default_null_settings_keep_trip_reminders_checked(): void
    {
        $tourist = $this->makeTourist();

        $this->assertNull($tourist->notification_settings);

        $response = $this->actingAs($tourist)->get(route('cabinet.settings'));

        $response->assertOk();
        $this->assertTripRemindersChecked($response->getContent());
    }

    // -----------------------------------------------------------------------
    // 6. trip_reminders=false — чекбокс присутствует, но не отмечен
    // -----------------------------------------------------------------------

    public function test_persisted_trip_reminders_false_leaves_checkbox_unchecked(): void
    {
        $tourist = $this->makeTourist();

        $tourist->forceFill([
            'notification_settings' => json_encode(['trip_reminders' => false]),
        ])->save();

        $response = $this->actingAs($tourist)->get(route('cabinet.settings'));

        $response->assertOk();
        $response->assertSee('id="tripReminders"', false);
        $this->assertTripRemindersNotChecked($response->getContent());
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeTourist(): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => Role::TOURIST],
            ['description' => Role::availableRoles()[Role::TOURIST] ?? Role::TOURIST]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    private function assertTripRemindersChecked(string $html): void
    {
        $this->assertMatchesRegularExpression(
            '/id="tripReminders"[^>]*name="trip_reminders"[^>]*checked/',
            $html
        );
    }

    private function assertTripRemindersNotChecked(string $html): void
    {
        $this->assertDoesNotMatchRegularExpression(
            '/id="tripReminders"[^>]*name="trip_reminders"[^>]*checked/',
            $html
        );
    }
}
