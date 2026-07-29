<?php

namespace Tests\Feature;

use App\Mail\BookingStatusChanged as BookingStatusChangedMail;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

/**
 * Проверяет, что Booking::transitionTo() не превращает уже успешно
 * сохранённый переход статуса в HTTP-ошибку, если реальный слушатель
 * BookingStatusChanged падает при отправке письма. Симметрично уже
 * защищённым сайтам диспатча (BookingCreated, ManagerAssigned,
 * NewMessageReceived), сбой логируется и проглатывается.
 *
 * Важно: здесь НЕ используется Event::fake(). Событие и реальный слушатель
 * должны действительно выполниться, иначе тест не проверяет то, ради чего
 * он написан — подмену допускается делать только для Mail/Log facade.
 */
class BookingStatusChangeDispatchResilienceTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Сбой письма владельцу не портит успешный ответ и не откатывает статус
    // -----------------------------------------------------------------------

    public function test_owner_mail_dispatch_failure_preserves_successful_transition_response(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        Mail::shouldReceive('to')
            ->with($owner->email)
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success', 'Заявка отменена');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CANCELLED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 2. Сбой письма назначенному менеджеру не портит успешный ответ и не
    //    откатывает статус (владелец при этом успешно получает письмо)
    // -----------------------------------------------------------------------

    public function test_manager_mail_dispatch_failure_preserves_successful_transition_response(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        // Письмо владельцу проходит нормально...
        Mail::shouldReceive('to')
            ->with($owner->email)
            ->once()
            ->andReturn(new class {
                public function queue($mailable): void
                {
                }
            });

        // ...а письмо менеджеру падает.
        Mail::shouldReceive('to')
            ->with($manager->email)
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        $this->actingAs($manager)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success', 'Заявка подтверждена');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);
    }

    // -----------------------------------------------------------------------
    // 3. Сбой диспатча логируется ровно один раз с booking_id и классом исключения
    // -----------------------------------------------------------------------

    public function test_dispatch_failure_is_logged_with_booking_id_and_exception_class(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        Mail::shouldReceive('to')
            ->with($owner->email)
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        Log::shouldReceive('error')
            ->once()
            ->with(
                'BookingStatusChanged dispatch failed',
                \Mockery::on(function (array $context) use ($booking): bool {
                    return ($context['booking_id'] ?? null) === $booking->id
                        && ($context['exception'] ?? null) === \RuntimeException::class;
                })
            );

        $this->actingAs($admin)
            ->post(route('bookings.cancel', $booking))
            ->assertRedirect(route('bookings.show', $booking));
    }

    // -----------------------------------------------------------------------
    // 4. Регресс: без сбоя письмо по-прежнему ставится в очередь каждому
    //    подходящему получателю, а переход статуса по-прежнему успешен
    // -----------------------------------------------------------------------

    public function test_successful_transition_still_queues_mail_for_all_eligible_recipients(): void
    {
        Mail::fake();

        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $this->actingAs($manager)
            ->post(route('bookings.confirm', $booking))
            ->assertRedirect(route('bookings.show', $booking))
            ->assertSessionHas('success', 'Заявка подтверждена');

        $this->assertDatabaseHas('bookings', [
            'id'     => $booking->id,
            'status' => Booking::STATUS_CONFIRMED,
        ]);

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($owner->email) && $mail->recipient->id === $owner->id
        );

        Mail::assertQueued(
            BookingStatusChangedMail::class,
            fn (BookingStatusChangedMail $mail): bool =>
                $mail->hasTo($manager->email) && $mail->recipient->id === $manager->id
        );

        Mail::assertQueuedCount(2);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

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

    private function makeBooking(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => $status,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => '2026-08-15',
                'nights'              => 7,
                'adults'              => 2,
                'children'            => 0,
            ])
        );
    }
}
