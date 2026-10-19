<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Listeners\SendBookingCreatedNotification;
use App\Mail\AdminBookingCreated;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * AO-01: письмо администратору о новой заявке (emails.admin.booking-created)
 * должно корректно выводить возрасты детей. Booking::$casts приводит
 * children_ages к array, поэтому шаблон не должен вызывать json_decode()
 * для уже декодированного значения.
 *
 * Письмо рендерится по реальному пути: слушатель SendBookingCreatedNotification
 * ставит AdminBookingCreated в очередь, после чего поставленный Mailable
 * рендерится. Mail::fake() гарантирует, что реальная отправка не происходит.
 */
class AdminBookingCreatedEmailRenderingTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
    }

    public function test_booking_without_children_renders_without_children_block(): void
    {
        $html = $this->renderAdminMailFor(['children' => 0, 'children_ages' => null]);

        $this->assertStringContainsString('Взрослых: 2', $html);
        $this->assertStringNotContainsString('Детей:', $html);
        $this->assertStringNotContainsString('возраст:', $html);
    }

    public function test_children_ages_are_ignored_when_children_count_is_zero(): void
    {
        $html = $this->renderAdminMailFor(['children' => 0, 'children_ages' => [5, 8]]);

        $this->assertStringNotContainsString('Детей:', $html);
        $this->assertStringNotContainsString('возраст:', $html);
    }

    public function test_one_child_with_age_renders_age(): void
    {
        $html = $this->renderAdminMailFor(['children' => 1, 'children_ages' => [7]]);

        $this->assertStringContainsString('Детей: 1', $html);
        $this->assertSame(1, preg_match('/\(возраст:\s*7\s*\)/u', $html));
    }

    public function test_multiple_children_render_all_ages_in_order(): void
    {
        $html = $this->renderAdminMailFor(['children' => 3, 'children_ages' => [3, 8, 12]]);

        $this->assertStringContainsString('Детей: 3', $html);
        $this->assertSame(1, preg_match('/\(возраст:\s*3, 8, 12\s*\)/u', $html));
    }

    public function test_child_with_age_zero_is_rendered(): void
    {
        $html = $this->renderAdminMailFor(['children' => 2, 'children_ages' => [0, 5]]);

        $this->assertSame(1, preg_match('/\(возраст:\s*0, 5\s*\)/u', $html));
    }

    public function test_children_with_null_ages_render_without_exception_or_age_block(): void
    {
        $html = $this->renderAdminMailFor(['children' => 2, 'children_ages' => null]);

        $this->assertStringContainsString('Детей: 2', $html);
        $this->assertStringNotContainsString('возраст:', $html);
    }

    public function test_children_with_empty_ages_array_render_without_exception_or_age_block(): void
    {
        $html = $this->renderAdminMailFor(['children' => 2, 'children_ages' => []]);

        $this->assertStringContainsString('Детей: 2', $html);
        $this->assertStringNotContainsString('возраст:', $html);
    }

    public function test_ages_array_with_only_blank_entries_renders_without_age_block(): void
    {
        $html = $this->renderAdminMailFor(['children' => 2, 'children_ages' => [null, '']]);

        $this->assertStringContainsString('Детей: 2', $html);
        $this->assertStringNotContainsString('возраст:', $html);
    }

    public function test_age_values_are_html_escaped(): void
    {
        $html = $this->renderAdminMailFor([
            'children' => 1,
            'children_ages' => ['<script>alert(1)</script>'],
        ]);

        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_rendering_does_not_send_any_real_email(): void
    {
        $this->renderAdminMailFor(['children' => 1, 'children_ages' => [4]]);

        Mail::assertNotSent(AdminBookingCreated::class);
        Mail::assertQueued(AdminBookingCreated::class, 1);
    }

    /**
     * Создаёт заявку с указанными полями, прогоняет её через слушатель и
     * возвращает HTML письма администратору, поставленного в очередь.
     *
     * @param array<string, mixed> $attributes
     */
    private function renderAdminMailFor(array $attributes): string
    {
        $owner = $this->makeUser(Role::TOURIST);
        $this->makeUser(Role::ADMIN);

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

        (new SendBookingCreatedNotification())->handle(
            new BookingCreated($booking->fresh())
        );

        $html = null;

        Mail::assertQueued(AdminBookingCreated::class, function (AdminBookingCreated $mail) use (&$html): bool {
            $html = $mail->render();

            return true;
        });

        $this->assertIsString($html);

        return $html;
    }

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }
}
