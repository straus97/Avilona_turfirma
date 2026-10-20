<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AO-FIX-04: блок «Возраст детей» на странице заявки (bookings.show) не должен
 * печатать мусор для неизвестных возрастов. BookingController валидирует
 * children_ages.* как nullable|integer, поэтому null-элементы допустимы;
 * раньше они превращались в висящее « лет» и ломали запятые.
 *
 * Неизвестный возраст пропускается и не выдумывается; количество детей
 * ("Туристы: ... N ребёнка") остаётся фактическим. Страница рендерится через
 * реальный авторизованный маршрут, данные заявки в БД не меняются.
 */
class BookingShowChildAgesRenderingTest extends TestCase
{
    use RefreshDatabase;

    private const AGES_LABEL = 'Возраст детей';

    /**
     * @return array<string, array{0: mixed}>
     */
    public static function agesWithoutAnyKnownAge(): array
    {
        return [
            'null'                  => [null],
            'empty array'           => [[]],
            'single null'           => [[null]],
            'only nulls and blanks' => [[null, '', '  ']],
            'only invalid entries'  => [['abc', '3.5', -1, true, [7]]],
        ];
    }

    #[DataProvider('agesWithoutAnyKnownAge')]
    public function test_children_without_known_ages_render_no_age_row_and_keep_children_count(mixed $ages): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 2, 'children_ages' => $ages]);

        $html = $this->getShowHtml($owner, $booking);

        $this->assertStringContainsString('2 взрослых, 2 ребёнка', $html);
        $this->assertStringNotContainsString(self::AGES_LABEL, $html);
        $this->assertStringNotContainsString('0 лет', $html);
        $this->assertStringNotContainsString('>лет<', $html);
    }

    public function test_no_children_render_no_age_row_even_if_ages_are_stored(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 0, 'children_ages' => [5]]);

        $html = $this->getShowHtml($owner, $booking);

        $this->assertStringContainsString('2 взрослых', $html);
        $this->assertStringNotContainsString('ребёнок', $html);
        $this->assertStringNotContainsString(self::AGES_LABEL, $html);
    }

    public function test_null_entry_between_valid_ages_is_skipped_without_stray_text_or_punctuation(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 3, 'children_ages' => [3, null, 8]]);

        $html = $this->getShowHtml($owner, $booking);

        $this->assertSame('3 года, 8 лет', $this->agesValue($html));
        $this->assertStringContainsString('2 взрослых, 3 ребёнка', $html);
    }

    public function test_leading_and_trailing_null_entries_are_skipped(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 3, 'children_ages' => [null, 5, null]]);

        $this->assertSame('5 лет', $this->agesValue($this->getShowHtml($owner, $booking)));
    }

    public function test_age_zero_is_preserved(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 1, 'children_ages' => [0]]);

        $this->assertSame('0 лет', $this->agesValue($this->getShowHtml($owner, $booking)));
    }

    public function test_numeric_string_ages_are_preserved(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 2, 'children_ages' => ['3', '12']]);

        $this->assertSame('3 года, 12 лет', $this->agesValue($this->getShowHtml($owner, $booking)));
    }

    public function test_multiple_valid_ages_use_correct_russian_plural_forms(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, [
            'children'      => 6,
            'children_ages' => [1, 2, 5, 11, 17, 4],
        ]);

        $this->assertSame(
            '1 год, 2 года, 5 лет, 11 лет, 17 лет, 4 года',
            $this->agesValue($this->getShowHtml($owner, $booking))
        );
    }

    public function test_mixed_valid_and_invalid_entries_keep_only_valid_ages(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, [
            'children'      => 6,
            'children_ages' => [null, '7', 'abc', 0, '', 3.5, '1'],
        ]);

        $this->assertSame('7 лет, 0 лет, 1 год', $this->agesValue($this->getShowHtml($owner, $booking)));
    }

    public function test_non_numeric_age_text_is_never_echoed_into_the_page(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, [
            'children'      => 2,
            'children_ages' => ['<script>alert(1)</script>', 4],
        ]);

        $html = $this->getShowHtml($owner, $booking);

        $this->assertSame('4 года', $this->agesValue($html));
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
    }

    public function test_age_label_is_html_escaped_by_the_facts_component(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 1, 'children_ages' => [9]]);

        $html = $this->getShowHtml($owner, $booking);

        $this->assertMatchesRegularExpression(
            '/<dd class="booking-fact__value[^>]*>9 лет<\/dd>/u',
            $html
        );
    }

    public function test_booking_data_is_not_modified_by_rendering(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 3, 'children_ages' => [3, null, 8]]);

        $this->getShowHtml($owner, $booking);

        $this->assertSame([3, null, 8], $booking->fresh()->children_ages);
        $this->assertSame(3, $booking->fresh()->children);
    }

    public function test_manager_and_admin_see_the_same_age_row(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, [
            'manager_id'    => $manager->id,
            'children'      => 3,
            'children_ages' => [3, null, 8],
        ]);

        $this->assertSame('3 года, 8 лет', $this->agesValue($this->getShowHtml($manager, $booking)));
        $this->assertSame('3 года, 8 лет', $this->agesValue($this->getShowHtml($admin, $booking)));
    }

    public function test_unrelated_tourist_is_still_forbidden(): void
    {
        $owner    = $this->makeUser(Role::TOURIST);
        $stranger = $this->makeUser(Role::TOURIST);
        $booking  = $this->makeBooking($owner, ['children' => 2, 'children_ages' => [3, 8]]);

        $this->actingAs($stranger)
            ->get(route('bookings.show', $booking))
            ->assertForbidden();
    }

    public function test_guest_is_still_redirected_to_login(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $booking = $this->makeBooking($owner, ['children' => 2, 'children_ages' => [3, 8]]);

        $this->get(route('bookings.show', $booking))->assertRedirect();
    }

    /** Текст значения строки «Возраст детей» (без обёртки dd). */
    private function agesValue(string $html): string
    {
        $this->assertSame(
            1,
            preg_match(
                '/<dt class="booking-fact__label">' . self::AGES_LABEL . '<\/dt>\s*<dd class="booking-fact__value[^>]*>(.*?)<\/dd>/su',
                $html,
                $matches
            ),
            'Строка «' . self::AGES_LABEL . '» не найдена на странице заявки.'
        );

        return $matches[1];
    }

    private function getShowHtml(User $user, Booking $booking): string
    {
        $response = $this->actingAs($user)->get(route('bookings.show', $booking));
        $response->assertOk();

        return $response->getContent();
    }

    private function makeUser(string $roleName): User
    {
        $user = User::factory()->create();
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeBooking(User $owner, array $attributes): Booking
    {
        $booking = Booking::withoutEvents(
            fn (): Booking => Booking::query()->create(array_merge([
                'user_id'             => $owner->id,
                'status'              => Booking::STATUS_NEW,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => '2026-08-15',
                'nights'              => 7,
                'adults'              => 2,
            ], $attributes))
        );

        return $booking->fresh();
    }
}
