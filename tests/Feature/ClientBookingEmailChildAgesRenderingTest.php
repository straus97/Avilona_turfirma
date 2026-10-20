<?php

namespace Tests\Feature;

use App\Mail\BookingCreated;
use App\Mail\TripReminder;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AO-FIX-03: клиентские письма (emails.bookings.created и
 * emails.bookings.trip-reminder) не должны падать с TypeError, когда
 * children_ages содержит null. BookingController валидирует
 * children_ages.* как nullable|integer, поэтому такие записи допустимы.
 *
 * Неизвестный возраст пропускается и не выдумывается; количество детей
 * ("Детей: N") остаётся фактическим. Письма только рендерятся, реальная
 * отправка исключена через Mail::fake().
 */
class ClientBookingEmailChildAgesRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    /**
     * @return array<string, array{0: string}>
     */
    public static function mailTypes(): array
    {
        return [
            'booking created' => ['created'],
            'trip reminder'   => ['reminder'],
        ];
    }

    #[DataProvider('mailTypes')]
    public function test_null_children_ages_render_children_count_without_age_block(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 2, 'children_ages' => null]);

        $this->assertStringContainsString('Детей:</strong> 2', $html);
        $this->assertStringNotContainsString('Возраст:', $html);
    }

    #[DataProvider('mailTypes')]
    public function test_empty_children_ages_render_children_count_without_age_block(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 2, 'children_ages' => []]);

        $this->assertStringContainsString('Детей:</strong> 2', $html);
        $this->assertStringNotContainsString('Возраст:', $html);
    }

    #[DataProvider('mailTypes')]
    public function test_only_null_age_renders_without_exception_and_without_age_block(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 1, 'children_ages' => [null]]);

        $this->assertStringContainsString('Детей:</strong> 1', $html);
        $this->assertStringNotContainsString('Возраст:', $html);
        // Неизвестный возраст не превращается в "0 лет".
        $this->assertStringNotContainsString('0 лет', $html);
    }

    #[DataProvider('mailTypes')]
    public function test_null_entry_between_valid_ages_is_skipped_without_invented_age_or_stray_punctuation(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 3, 'children_ages' => [3, null, 8]]);

        // Количество детей фактическое, а не число известных возрастов.
        $this->assertStringContainsString('Детей:</strong> 3', $html);
        $this->assertSame(1, preg_match('/Возраст:\s*3 года, 8 лет\s*<\/small>/u', $html));
        $this->assertStringNotContainsString('0 лет', $html);
        $this->assertDoesNotMatchRegularExpression('/,\s*,/u', $this->ageBlock($html));
        $this->assertDoesNotMatchRegularExpression('/,\s*<\/small>/u', $html);
    }

    #[DataProvider('mailTypes')]
    public function test_age_zero_is_a_valid_age_and_is_rendered(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 1, 'children_ages' => [0]]);

        $this->assertSame(1, preg_match('/Возраст:\s*0 лет\s*<\/small>/u', $html));
    }

    #[DataProvider('mailTypes')]
    public function test_numeric_string_ages_are_rendered_with_correct_plural(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 2, 'children_ages' => ['3', '12']]);

        $this->assertSame(1, preg_match('/Возраст:\s*3 года, 12 лет\s*<\/small>/u', $html));
    }

    #[DataProvider('mailTypes')]
    public function test_single_child_keeps_normal_wording(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 1, 'children_ages' => [1]]);

        $this->assertStringContainsString('Детей:</strong> 1', $html);
        $this->assertSame(1, preg_match('/Возраст:\s*1 год\s*<\/small>/u', $html));
    }

    #[DataProvider('mailTypes')]
    public function test_multiple_children_keep_normal_wording_and_order(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 4, 'children_ages' => [2, 5, 11, 21]]);

        $this->assertStringContainsString('Детей:</strong> 4', $html);
        $this->assertSame(1, preg_match('/Возраст:\s*2 года, 5 лет, 11 лет, 21 год\s*<\/small>/u', $html));
    }

    #[DataProvider('mailTypes')]
    public function test_ages_are_ignored_when_children_count_is_zero(string $mail): void
    {
        $html = $this->renderMail($mail, ['children' => 0, 'children_ages' => [3, null]]);

        $this->assertStringNotContainsString('Детей:', $html);
        $this->assertStringNotContainsString('Возраст:', $html);
    }

    #[DataProvider('mailTypes')]
    public function test_non_numeric_age_values_are_not_rendered_and_never_unescaped(string $mail): void
    {
        $html = $this->renderMail($mail, [
            'children'      => 2,
            'children_ages' => ['<script>alert(1)</script>', 6],
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('alert(1)', $html);
        $this->assertSame(1, preg_match('/Возраст:\s*6 лет\s*<\/small>/u', $html));
    }

    public function test_rendering_does_not_send_any_real_email(): void
    {
        $this->renderMail('created', ['children' => 1, 'children_ages' => [null]]);
        $this->renderMail('reminder', ['children' => 1, 'children_ages' => [null]]);

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
    }

    public function test_trip_reminder_subject_is_unaffected_by_child_ages(): void
    {
        $booking = $this->makeBooking(['children' => 2, 'children_ages' => [null, null]]);

        $mail = new TripReminder($booking, 3);

        $this->assertSame('Напоминание о поездке через 3 дня - Авилона', $mail->envelope()->subject);
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function renderMail(string $mail, array $attributes): string
    {
        $booking = $this->makeBooking($attributes);

        $mailable = $mail === 'created'
            ? new BookingCreated($booking)
            : new TripReminder($booking, 7);

        return $mailable->render();
    }

    /** Фрагмент письма, относящийся к возрастам детей. */
    private function ageBlock(string $html): string
    {
        $this->assertSame(1, preg_match('/Возраст:.*?<\/small>/su', $html, $matches));

        return $matches[0];
    }

    /**
     * @param array<string, mixed> $attributes
     */
    private function makeBooking(array $attributes): Booking
    {
        $role = Role::query()->firstOrCreate(
            ['name' => Role::TOURIST],
            ['description' => Role::availableRoles()[Role::TOURIST] ?? Role::TOURIST]
        );

        $owner = User::factory()->create();
        $owner->roles()->attach($role->id);

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
