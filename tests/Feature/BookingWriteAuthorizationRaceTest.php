<?php

namespace Tests\Feature;

use App\Events\BookingStatusChanged;
use App\Events\ManagerAssigned;
use App\Events\NewMessageReceived;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use App\Services\Bookings\BookingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I4 (I3-F1): запись по заявке заново проверяется под блокировкой строки.
 *
 * Гонка моделируется детерминированно в одном процессе: сразу после ПЕРВОГО чтения
 * заявки запросом (привязка маршрута / первичная проверка прав) администратор
 * переназначает её через настоящий BookingAssignment. Дальше запрос прежнего
 * ответственного продолжается со своим устаревшим представлением о заявке.
 * Настоящая конкурентность (разные процессы и соединения MySQL) проверяется отдельно.
 */
class BookingWriteAuthorizationRaceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAoaI2bFixtures;

    private User $admin;
    private User $managerA;
    private User $managerB;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();

        // Выборочно: модельные события (retrieved) должны работать как в боевом коде.
        Event::fake([BookingStatusChanged::class, ManagerAssigned::class, NewMessageReceived::class]);

        $this->admin = $this->userWith(Role::ADMIN);
        $this->managerA = $this->userWith(Role::MANAGER);
        $this->managerB = $this->userWith(Role::MANAGER);
        $this->tourist = $this->userWith(Role::TOURIST);
    }

    /** После первого чтения заявки запросом администратор переназначает её на $to (один раз). */
    private function reassignAfterFirstRead(Booking $booking, User $to): void
    {
        $fired = false;
        $adminId = $this->admin->id;
        $fromId = (int) $booking->manager_id;

        Booking::retrieved(function (Booking $read) use (&$fired, $booking, $to, $adminId, $fromId): void {
            if ($fired || $read->id !== $booking->id) {
                return;
            }
            $fired = true;

            app(BookingAssignment::class)->assign(
                $booking,
                User::findOrFail($adminId),
                $to->id,
                $fromId,
                'workload',
                null,
            );
        });
    }

    // -----------------------------------------------------------------------
    // Положительные контроли (гонки нет)
    // -----------------------------------------------------------------------

    public function test_current_manager_can_confirm_cancel_and_complete(): void
    {
        $confirm = $this->bookingOf($this->tourist, $this->managerA);
        $this->actingAs($this->managerA)->post(route('bookings.confirm', $confirm))->assertRedirect(route('bookings.show', $confirm));
        $this->assertSame(Booking::STATUS_CONFIRMED, $confirm->refresh()->status);

        $this->actingAs($this->managerA)->post(route('bookings.complete', $confirm))->assertRedirect(route('bookings.show', $confirm));
        $this->assertSame(Booking::STATUS_COMPLETED, $confirm->refresh()->status);

        $cancel = $this->bookingOf($this->tourist, $this->managerA);
        $this->actingAs($this->managerA)->post(route('bookings.cancel', $cancel))->assertRedirect(route('bookings.show', $cancel));
        $this->assertSame(Booking::STATUS_CANCELLED, $cancel->refresh()->status);

        Event::assertDispatchedTimes(BookingStatusChanged::class, 3);
    }

    public function test_current_manager_update_applies_status_and_fields_with_one_event(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS, ['total_price' => 100000]);

        $this->actingAs($this->managerA)->put(route('bookings.update', $booking), [
            'status' => Booking::STATUS_CONFIRMED,
            'manager_notes' => 'Синтетическая заметка',
            'total_price' => 123000,
        ])->assertRedirect(route('bookings.show', $booking));

        $booking->refresh();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertSame('Синтетическая заметка', $booking->manager_notes);
        $this->assertSame('123000.00', (string) $booking->total_price);
        Event::assertDispatchedTimes(BookingStatusChanged::class, 1);
    }

    public function test_admin_can_act_on_any_booking_and_unrelated_manager_is_denied(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);

        $this->actingAs($this->managerB)->post(route('bookings.confirm', $booking))->assertForbidden();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->refresh()->status);

        $this->actingAs($this->admin)->post(route('bookings.confirm', $booking))->assertRedirect();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        $this->assertSame($this->managerA->id, $booking->manager_id, 'Админ не меняет ответственного, подтверждая заявку.');
    }

    public function test_new_manager_can_act_after_reassignment(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        app(BookingAssignment::class)->assign($booking, $this->admin, $this->managerB->id, $this->managerA->id, 'workload', null);

        $this->actingAs($this->managerB)->post(route('bookings.confirm', $booking))->assertRedirect();
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        $this->assertSame($this->managerB->id, $booking->manager_id);
    }

    // -----------------------------------------------------------------------
    // I3-F1: прежний ответственный не может записать ничего после переназначения
    // -----------------------------------------------------------------------

    public function test_confirm_race_former_manager_is_denied_and_nothing_changes(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->post(route('bookings.confirm', $booking))->assertForbidden();

        $booking->refresh();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
        $this->assertSame($this->managerB->id, $booking->manager_id);
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_cancel_race_former_manager_is_denied_and_seats_are_untouched(): void
    {
        $tour = Tour::factory()->create(['available_seats' => 10, 'max_tourists' => 20]);
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS, ['tour_id' => $tour->id, 'adults' => 2]);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->post(route('bookings.cancel', $booking))->assertForbidden();

        $this->assertSame(Booking::STATUS_PROGRESS, $booking->refresh()->status);
        $this->assertSame(10, $tour->refresh()->available_seats);
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_complete_race_former_manager_is_denied_and_nothing_changes(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_CONFIRMED);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->post(route('bookings.complete', $booking))->assertForbidden();

        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->refresh()->status);
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_update_race_former_manager_cannot_change_status_notes_or_price(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS, ['total_price' => 100000]);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->put(route('bookings.update', $booking), [
            'status' => Booking::STATUS_CONFIRMED,
            'manager_notes' => 'Запись прежнего ответственного',
            'total_price' => 1,
        ])->assertForbidden();

        $booking->refresh();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
        $this->assertNull($booking->manager_notes);
        $this->assertSame('100000.00', (string) $booking->total_price);
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_update_race_without_status_change_cannot_edit_fields(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS, ['total_price' => 100000]);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->put(route('bookings.update', $booking), [
            'status' => Booking::STATUS_PROGRESS,
            'manager_notes' => 'Запись прежнего ответственного',
        ])->assertForbidden();

        $this->assertNull($booking->refresh()->manager_notes);
    }

    public function test_tourist_cancel_races_with_assignment_and_is_denied(): void
    {
        // Турист вправе отменить только заявку без ответственного; заявку взяли в работу между
        // первичной проверкой и записью — отмена туриста не должна пройти.
        $booking = $this->bookingOf($this->tourist, null, Booking::STATUS_NEW);
        $fired = false;
        $adminId = $this->admin->id;
        $managerBId = $this->managerB->id;
        Booking::retrieved(function (Booking $read) use (&$fired, $booking, $adminId, $managerBId): void {
            if ($fired || $read->id !== $booking->id) {
                return;
            }
            $fired = true;
            app(BookingAssignment::class)->assign($booking, User::findOrFail($adminId), $managerBId, null, null, null);
        });

        $this->actingAs($this->tourist)->post(route('bookings.cancel', $booking))->assertForbidden();

        $booking->refresh();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
        $this->assertSame($this->managerB->id, $booking->manager_id);
    }

    // -----------------------------------------------------------------------
    // Правила переходов сохранены и проверяются по актуальному состоянию
    // -----------------------------------------------------------------------

    public function test_invalid_transition_is_still_rejected_without_change(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_CANCELLED);

        $this->actingAs($this->managerA)->from(route('bookings.show', $booking))
            ->post(route('bookings.confirm', $booking))
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_transition_is_revalidated_against_current_status_under_lock(): void
    {
        $tour = Tour::factory()->create(['available_seats' => 10, 'max_tourists' => 20]);
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS, ['tour_id' => $tour->id, 'adults' => 2]);

        // Между первичной проверкой и записью заявку отменили другим запросом.
        $fired = false;
        Booking::retrieved(function (Booking $read) use (&$fired, $booking): void {
            if ($fired || $read->id !== $booking->id) {
                return;
            }
            $fired = true;
            Booking::withoutEvents(fn () => Booking::query()->whereKey($booking->id)->update(['status' => Booking::STATUS_CANCELLED]));
        });

        $this->actingAs($this->managerA)->from(route('bookings.show', $booking))
            ->post(route('bookings.confirm', $booking))
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_CANCELLED, $booking->refresh()->status);
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    public function test_update_with_invalid_target_status_is_rejected_by_validation(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS);

        $this->actingAs($this->managerA)->put(route('bookings.update', $booking), ['status' => Booking::STATUS_COMPLETED])
            ->assertSessionHasErrors('status');

        $this->assertSame(Booking::STATUS_PROGRESS, $booking->refresh()->status);
    }

    public function test_update_rolls_back_status_when_seat_restoration_fails(): void
    {
        $tour = Tour::factory()->create(['available_seats' => 20, 'max_tourists' => 20]);
        $booking = $this->bookingOf($this->tourist, $this->managerA, Booking::STATUS_PROGRESS, ['tour_id' => $tour->id, 'adults' => 2, 'total_price' => 100000]);

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($this->managerA)->put(route('bookings.update', $booking), [
                'status' => Booking::STATUS_CANCELLED,
                'manager_notes' => 'Не должно сохраниться',
            ]);
            $this->fail('Ожидалось исключение восстановления мест.');
        } catch (\DomainException) {
            // seats would exceed max_tourists
        }

        $booking->refresh();
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
        $this->assertNull($booking->manager_notes, 'Заметки не должны сохраниться при откате перехода.');
        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    // -----------------------------------------------------------------------
    // Чат: store
    // -----------------------------------------------------------------------

    public function test_manager_can_send_message_while_authorized(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);

        $this->actingAs($this->managerA)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $this->tourist->id,
            'message' => 'Синтетическое сообщение',
        ])->assertOk()->assertJsonPath('success', true);

        $this->assertSame(1, Message::query()->where('booking_id', $booking->id)->count());
        Event::assertDispatchedTimes(NewMessageReceived::class, 1);
    }

    public function test_chat_store_race_former_manager_cannot_create_message(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $this->tourist->id,
            'message' => 'Сообщение прежнего ответственного',
        ])->assertForbidden();

        $this->assertSame(0, Message::query()->count());
        Event::assertNotDispatched(NewMessageReceived::class);
    }

    public function test_chat_store_race_attachment_is_not_left_behind(): void
    {
        Storage::fake('local');
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->managerA)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $this->tourist->id,
            'attachment' => UploadedFile::fake()->create('synthetic.pdf', 10, 'application/pdf'),
        ])->assertForbidden();

        $this->assertSame(0, Message::query()->count());
        $this->assertSame([], Storage::disk('local')->allFiles('messages'), 'Отклонённое вложение не должно оставаться на диске.');
        Event::assertNotDispatched(NewMessageReceived::class);
    }

    public function test_chat_store_race_tourist_message_to_former_manager_is_rejected(): void
    {
        // Турист пишет ответственному A; пока запрос идёт, заявку переназначили на B.
        // Получатель A больше не участник — сообщение (и уведомление A) создаваться не должны.
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->reassignAfterFirstRead($booking, $this->managerB);

        $this->actingAs($this->tourist)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $this->managerA->id,
            'message' => 'Сообщение прежнему ответственному',
        ])->assertStatus(422);

        $this->assertSame(0, Message::query()->count());
        Event::assertNotDispatched(NewMessageReceived::class);
    }

    public function test_tourist_can_message_current_manager_after_reassignment(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        app(BookingAssignment::class)->assign($booking, $this->admin, $this->managerB->id, $this->managerA->id, 'workload', null);

        $this->actingAs($this->tourist)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $this->managerB->id,
            'message' => 'Синтетическое сообщение',
        ])->assertOk();

        $this->assertSame($this->managerB->id, Message::query()->firstOrFail()->receiver_id);
        Event::assertDispatchedTimes(NewMessageReceived::class, 1);
    }

    public function test_admin_chat_write_contract_is_unchanged(): void
    {
        $unassigned = $this->bookingOf($this->tourist, $this->managerA);
        $this->actingAs($this->admin)->postJson(route('messages.store'), [
            'booking_id' => $unassigned->id,
            'receiver_id' => $this->tourist->id,
            'message' => 'Админ не назначен',
        ])->assertForbidden();

        $own = $this->bookingOf($this->tourist, $this->admin);
        $this->actingAs($this->admin)->postJson(route('messages.store'), [
            'booking_id' => $own->id,
            'receiver_id' => $this->tourist->id,
            'message' => 'Админ назначен',
        ])->assertOk();

        $this->assertSame(1, Message::query()->count());
        $this->actingAs($this->admin)->getJson(route('messages.index', ['booking_id' => $unassigned->id]))->assertOk();
    }
}
