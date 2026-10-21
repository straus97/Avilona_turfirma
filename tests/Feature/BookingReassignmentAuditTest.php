<?php

namespace Tests\Feature;

use App\Events\ManagerAssigned;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Bookings\BookingAssignment;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use InvalidArgumentException;
use RuntimeException;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I2B, G7 — смена ответственного по заявке: только администратор, обязательная причина,
 * атомарная запись аудита, защита от устаревшей формы и повторов. Синтетические данные.
 */
class BookingReassignmentAuditTest extends TestCase
{
    use BuildsAoaI2bFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $a;
    private User $b;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager', ['name' => 'Менеджер Альфа']);
        $this->b = $this->userWith('manager', ['name' => 'Менеджер Бета']);
        $this->client = $this->userWith('tourist');
    }

    private function payload(Booking $booking, User $to, array $overrides = []): array
    {
        return array_merge([
            'manager_id' => $to->id,
            'expected_manager_id' => $booking->manager_id,
            'reason_code' => 'workload',
        ], $overrides);
    }

    private function events(Booking $booking)
    {
        return AuditEvent::query()->where('subject_type', 'booking')->where('subject_id', $booking->id)->get();
    }

    public function test_only_admin_can_reassign_a_booking(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);

        foreach ([$this->a, $this->b, $this->client] as $actor) {
            $this->actingAs($actor)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b))->assertForbidden();
        }

        $this->assertSame($this->a->id, $booking->fresh()->manager_id);
        $this->assertCount(0, $this->events($booking));
    }

    public function test_admin_reassignment_records_who_what_when_and_why(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b, [
            'reason_code' => 'absence', 'reason' => 'Основной менеджер в отпуске до конца месяца',
        ]))->assertSessionHasNoErrors()->assertRedirect(route('bookings.show', $booking));

        $this->assertSame($this->b->id, $booking->fresh()->manager_id);

        $event = $this->events($booking)->sole();
        $this->assertSame(AuditEventType::BookingReassigned->value, $event->event_type);
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame($booking->id, $event->subject_id);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame($this->a->id, $event->metadata['previous_manager_id']);
        $this->assertSame($this->b->id, $event->metadata['new_manager_id']);
        $this->assertSame('absence', $event->metadata['reason_code']);
        $this->assertSame('Основной менеджер в отпуске до конца месяца', $event->metadata['reason']);

        // История видна администратору на карточке заявки.
        $this->actingAs($this->admin)->get(route('bookings.show', $booking))
            ->assertOk()->assertSee('История ответственных')->assertSee('Менеджер Альфа')->assertSee('Менеджер Бета')
            ->assertSee('Отсутствие сотрудника');
    }

    public function test_reason_is_required_when_changing_an_existing_assignee(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b, ['reason_code' => '']))
            ->assertSessionHasErrors('reason_code');
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b, ['reason_code' => 'invented']))
            ->assertSessionHasErrors('reason_code');
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b, ['reason_code' => 'other', 'reason' => '']))
            ->assertSessionHasErrors('reason');
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b, ['reason' => 'ок']))
            ->assertSessionHasErrors('reason');

        $this->assertSame($this->a->id, $booking->fresh()->manager_id);
        $this->assertCount(0, $this->events($booking));
    }

    public function test_unsafe_reason_text_is_rejected_and_nothing_changes(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);

        foreach ([
            'Клиент просил звонить на +7 (912) 345-67-89',
            'Связаться: 89123456789',
            'Писать на client.person@example.test',
            'Файл C:\\clients\\passport.pdf',
            'Токен abcdefghijklmnopqrstuvwxyz0123456789ABCDEF',
        ] as $unsafe) {
            $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b, [
                'reason_code' => 'other', 'reason' => $unsafe,
            ]))->assertSessionHasErrors('reason');
        }

        $this->assertSame($this->a->id, $booking->fresh()->manager_id);
        $this->assertCount(0, $this->events($booking));
    }

    public function test_audit_failure_rolls_the_assignment_back(): void
    {
        $booking = $this->bookingOf($this->client, $this->a, Booking::STATUS_CONFIRMED);

        $this->app->instance(AuditRecorder::class, new class extends AuditRecorder
        {
            public function record($type, $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });

        try {
            $this->app->make(BookingAssignment::class)->assign($booking, $this->admin, $this->b->id, $this->a->id, 'workload', null);
            $this->fail('Ожидалось исключение аудита.');
        } catch (RuntimeException) {
            // ожидаемо
        }

        $this->assertSame($this->a->id, $booking->fresh()->manager_id, 'Смена откатана вместе с аудитом');
        $this->assertSame(Booking::STATUS_CONFIRMED, $booking->fresh()->status);
    }

    public function test_stale_form_is_rejected_instead_of_overwriting_a_newer_assignment(): void
    {
        $c = $this->userWith('manager');
        $booking = $this->bookingOf($this->client, $this->a);

        // Форма открыта, когда ответственный — A; параллельно администратор передал заявку B.
        $stalePayload = $this->payload($booking, $c);
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $stalePayload)->assertSessionHasErrors('manager_id');

        $this->assertSame($this->b->id, $booking->fresh()->manager_id);
        $this->assertCount(1, $this->events($booking));
    }

    public function test_missing_expected_field_is_rejected(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);
        $payload = $this->payload($booking, $this->b);
        unset($payload['expected_manager_id']);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $payload)->assertSessionHasErrors('expected_manager_id');
        $this->assertSame($this->a->id, $booking->fresh()->manager_id);
    }

    public function test_repeating_the_same_request_is_harmless_and_does_not_duplicate_audit_or_notification(): void
    {
        Event::fake([ManagerAssigned::class]);
        $booking = $this->bookingOf($this->client, $this->a);
        $payload = $this->payload($booking, $this->b);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $payload)->assertSessionHasNoErrors();
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $payload)->assertSessionHasNoErrors();

        $this->assertSame($this->b->id, $booking->fresh()->manager_id);
        $this->assertCount(1, $this->events($booking));
        Event::assertDispatchedTimes(ManagerAssigned::class, 1);
    }

    public function test_reassignment_preserves_status_payment_client_chat_and_history_rows(): void
    {
        $booking = $this->bookingOf($this->client, $this->a, Booking::STATUS_CONFIRMED, ['total_price' => 234567.89, 'paid_amount' => 1000]);
        Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $this->client->id, 'receiver_id' => $this->a->id, 'message' => 'Старая переписка', 'is_read' => false]);
        $other = $this->bookingOf($this->client, $this->a, Booking::STATUS_PROGRESS);
        $before = $booking->fresh()->only(['status', 'user_id', 'total_price', 'departure_city', 'destination_country', 'nights', 'adults']);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b))->assertSessionHasNoErrors();

        $this->assertSame($before, $booking->fresh()->only(array_keys($before)));
        $this->assertSame(1, Message::query()->where('booking_id', $booking->id)->count());
        $this->assertSame($this->a->id, $other->fresh()->manager_id, 'Другие заявки не затронуты');
    }

    public function test_manager_access_follows_the_new_responsibility(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);
        Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $this->client->id, 'receiver_id' => $this->a->id, 'message' => 'ТЕКСТ-ПЕРЕПИСКИ-123', 'is_read' => false]);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b))->assertSessionHasNoErrors();

        $this->actingAs($this->a)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($this->a)->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertDontSee('ТЕКСТ-ПЕРЕПИСКИ-123');
        $this->actingAs($this->a)->postJson(route('messages.store'), ['booking_id' => $booking->id, 'receiver_id' => $this->client->id, 'message' => 'x'])->assertForbidden();

        $this->actingAs($this->b)->get(route('bookings.show', $booking))->assertOk();
        $this->actingAs($this->b)->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertSee('ТЕКСТ-ПЕРЕПИСКИ-123');
    }

    public function test_admin_chat_write_rule_is_not_broadened_by_reassignment(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->b))->assertSessionHasNoErrors();

        // Администратор читает любую переписку, но писать может только как назначенный ответственный.
        $this->actingAs($this->admin)->postJson(route('messages.store'), ['booking_id' => $booking->id, 'receiver_id' => $this->client->id, 'message' => 'x'])->assertForbidden();
        $this->actingAs($this->admin)->get(route('cabinet.admin.chats', ['bookingId' => $booking->id]))->assertOk();
        $this->assertSame(0, Message::query()->where('sender_id', $this->admin->id)->count());
    }

    public function test_initial_assignment_needs_no_reason_and_is_audited_as_assignment(): void
    {
        $booking = $this->bookingOf($this->client, null, Booking::STATUS_NEW);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), [
            'manager_id' => $this->a->id, 'expected_manager_id' => '',
        ])->assertSessionHasNoErrors();

        $fresh = $booking->fresh();
        $this->assertSame($this->a->id, $fresh->manager_id);
        $this->assertSame(Booking::STATUS_PROGRESS, $fresh->status);

        $event = $this->events($booking)->sole();
        $this->assertSame(AuditEventType::BookingAssigned->value, $event->event_type);
        $this->assertNull($event->metadata['previous_manager_id']);
        $this->assertArrayNotHasKey('reason_code', $event->metadata);
    }

    public function test_ineligible_target_is_rejected_without_audit(): void
    {
        $booking = $this->bookingOf($this->client, $this->a);
        $inactive = $this->userWith('manager', ['is_active' => false]);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $inactive))->assertSessionHasErrors('manager_id');
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $booking), $this->payload($booking, $this->client))->assertSessionHasErrors('manager_id');

        $this->assertSame($this->a->id, $booking->fresh()->manager_id);
        $this->assertCount(0, $this->events($booking));
    }

    public function test_audit_metadata_rejects_phone_numbers_but_keeps_dates_and_plain_text(): void
    {
        foreach (['+7 (912) 345-67-89', '8 912 345 67 89', '89123456789', 'тел. 912.345.67.89 после 18'] as $phone) {
            try {
                AuditMetadata::validate(['reason' => $phone]);
                $this->fail("Телефон не отклонён: {$phone}");
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(
            ['reason' => 'С 2026-10-21 до 25.10.2026, заявка 12345, 3 взрослых'],
            AuditMetadata::validate(['reason' => 'С 2026-10-21 до 25.10.2026, заявка 12345, 3 взрослых'])
        );
    }

    public function test_inquiry_reassignment_reason_also_rejects_phone_numbers(): void
    {
        $inquiry = $this->inquiryFor($this->a);

        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), [
            'assigned_to' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Звонить клиенту +7 912 345 67 89',
        ])->assertSessionHasErrors('reason');

        $this->assertSame($this->a->id, $inquiry->fresh()->assigned_to);
    }
}
