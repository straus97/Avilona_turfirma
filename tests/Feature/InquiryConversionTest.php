<?php

namespace Tests\Feature;

use App\Mail\BookingCreated as BookingCreatedMail;
use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\InquiryContact;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVisibility;
use App\Services\Audit\AuditRecorder;
use App\Services\Bookings\BookingAssignment;
use App\Services\Inquiries\InquiryAssignment;
use App\Services\Inquiries\InquiryAssignmentException;
use App\Services\Inquiries\InquiryClientAssociation;
use App\Services\Inquiries\InquiryClientAssociationException;
use App\Services\Inquiries\InquiryClosure;
use App\Services\Inquiries\InquiryClosureException;
use App\Services\Inquiries\InquiryConversion;
use App\Services\Inquiries\InquiryConversionException;
use App\Services\Inquiries\InquiryWorkloadSummary;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-05-I1 — explicit conversion of an INDEPENDENT Inquiry into exactly one Booking
 * (in_progress → converted): authority, eligibility, Booking contract, atomicity, idempotency,
 * converted-state privacy, audit, notifications, UI and counters. Synthetic data, SQLite :memory:
 * (true parallel behaviour is verified on a disposable MySQL, see the AOA-05-I1 reference).
 */
class InquiryConversionTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const CLIENT_NAME = 'Синтетический Клиент Оформления';
    private const CLIENT_EMAIL = 'convert.client@example.test';
    private const CLIENT_PHONE = '+7 900 555-03-04';
    private const INTERNAL_NOTE = 'Внутренняя заметка только для сотрудников';

    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;
    private User $client;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->admin = $this->userWith('admin', ['name' => 'Админ Синтетический']);
        $this->a = $this->userWith('manager', ['name' => 'Анна Синтетическая']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Синтетический']);
        $this->tourist = $this->userWith('tourist', ['name' => 'Турист Посторонний']);
        $this->client = $this->userWith('tourist', [
            'name' => self::CLIENT_NAME, 'email' => self::CLIENT_EMAIL, 'phone' => self::CLIENT_PHONE,
        ]);
    }

    /** Самостоятельное обращение менеджера с привязанным клиентом и заполненным черновиком. */
    private function ready(?User $manager = null, ?User $client = null): Inquiry
    {
        $inquiry = $this->independentInquiry($manager ?? $this->a, null, $client ?? $this->client);

        DB::table('inquiries')->where('id', $inquiry->id)->update([
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'departure_city' => 'Москва',
            'date_from' => now()->addMonths(2)->toDateString(),
            'date_to' => now()->addMonths(2)->addDays(5)->toDateString(),
            'nights_min' => 7,
            'nights_max' => 9,
            'adults' => 2,
            'children' => 1,
            'children_ages' => json_encode([6]),
            'preferences' => 'Отель у моря, питание всё включено',
            'internal_notes' => self::INTERNAL_NOTE,
            'draft_revision' => 3,
        ]);

        return $inquiry->refresh();
    }

    private function convertUrl(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.convert', $inquiry);
    }

    private function show(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private const BOOKING_FIELDS = [
        'departure_city', 'destination_country', 'destination_city', 'start_date', 'start_date_end',
        'nights', 'nights_max', 'adults', 'children', 'children_ages', 'notes', 'manager_notes',
    ];

    /** Trip fields in `override` go under booking[...] exactly as the form posts them; others stay top-level. */
    private function payload(Inquiry $inquiry, array $override = []): array
    {
        $booking = [
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(),
            'start_date_end' => now()->addMonths(2)->addDays(5)->toDateString(),
            'nights' => 7,
            'nights_max' => 9,
            'adults' => 2,
            'children' => 1,
            'children_ages' => '6',
            'notes' => 'Отель у моря',
            'manager_notes' => '',
        ];
        $top = [
            'expected_status' => $inquiry->status,
            'expected_owner_id' => $inquiry->assigned_to,
            'expected_client_id' => $inquiry->client_user_id,
            'confirm_conversion' => '1',
        ];

        foreach ($override as $key => $value) {
            if (in_array($key, self::BOOKING_FIELDS, true)) {
                $booking[$key] = $value;
            } else {
                $top[$key] = $value;
            }
        }

        return $top + ['booking' => $booking];
    }

    private function submit(?User $actor, Inquiry $inquiry, array $override = [])
    {
        $request = $actor === null ? $this : $this->actingAs($actor);

        return $request->post($this->convertUrl($inquiry), $this->payload($inquiry, $override));
    }

    private function convertService(User $actor, Inquiry $inquiry, array $override = []): array
    {
        $p = $this->payload($inquiry->refresh(), $override);
        $details = $p['booking'];
        $details['children_ages'] = array_values(array_filter(array_map('trim', explode(',', (string) $details['children_ages']))));

        return app(InquiryConversion::class)->convert(
            $inquiry,
            $actor,
            $details,
            (string) $p['expected_status'],
            $p['expected_owner_id'] === null ? null : (int) $p['expected_owner_id'],
            $p['expected_client_id'] === null ? null : (int) $p['expected_client_id'],
        );
    }

    private function events()
    {
        return AuditEvent::query()->where('event_type', AuditEventType::InquiryConverted->value)->orderBy('id');
    }

    private function assertNothingConverted(Inquiry $inquiry, int $bookings = 0): void
    {
        $inquiry->refresh();
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->status);
        $this->assertNull($inquiry->booking_id);
        $this->assertNull($inquiry->converted_at);
        $this->assertNull($inquiry->converted_by);
        $this->assertSame($bookings, Booking::query()->withTrashed()->count());
        $this->assertSame(0, $this->events()->count());
    }

    // ---- Gate B: authority and the Booking contract --------------------------------------------

    public function test_responsible_manager_converts_and_booking_follows_the_contract(): void
    {
        $inquiry = $this->ready();
        (new InquiryContact())->forceFill(['inquiry_id' => $inquiry->id, 'contact_name' => 'Контакт Не Аккаунт', 'revision' => 1])->save();
        $before = $inquiry->only(['origin', 'contact_channel', 'created_by', 'assigned_to', 'incoming_inquiry_id', 'client_user_id', 'client_linked_by', 'draft_revision', 'destination_country', 'adults', 'preferences', 'internal_notes']);

        $this->submit($this->a, $inquiry)->assertRedirect($this->show($inquiry))->assertSessionHas('success')->assertSessionHasNoErrors();

        $booking = Booking::query()->sole();
        $this->assertSame($this->client->id, $booking->user_id, 'the explicitly linked Tourist owns the Booking');
        $this->assertSame($this->a->id, $booking->manager_id, 'the current responsible employee handles it');
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
        $this->assertNull($booking->tour_id, 'no fabricated Tour');
        $this->assertNull($booking->total_price, 'no invented price');
        $this->assertSame('0.00', (string) $booking->paid_amount, 'no invented payment');
        $this->assertSame('Москва', $booking->departure_city);
        $this->assertSame('Турция', $booking->destination_country);
        $this->assertSame('Анталья', $booking->destination_city);
        $this->assertSame(7, (int) $booking->nights);
        $this->assertSame(9, (int) $booking->nights_max);
        $this->assertSame(2, (int) $booking->adults);
        $this->assertSame(1, (int) $booking->children);
        $this->assertEquals([6], $booking->children_ages);
        $this->assertStringNotContainsString(self::INTERNAL_NOTE, (string) $booking->notes.(string) $booking->manager_notes, 'internal draft notes never travel to the Booking');

        $inquiry->refresh();
        $this->assertSame(Inquiry::STATUS_CONVERTED, $inquiry->status);
        $this->assertSame($booking->id, $inquiry->booking_id);
        $this->assertNotNull($inquiry->converted_at);
        $this->assertSame($this->a->id, $inquiry->converted_by);
        $this->assertSame($before, $inquiry->only(array_keys($before)), 'provenance, creator, assignee, client and draft are preserved');
        $this->assertNotNull(InquiryContact::query()->find($inquiry->id));
        $this->assertSame(0, IncomingInquiry::query()->count());
        $this->assertSame(1, User::query()->whereKey($this->client->id)->count());

        $event = $this->events()->sole();
        $this->assertSame($this->a->id, $event->actor_id);
        $this->assertSame('inquiry', $event->subject_type);
        $this->assertSame($inquiry->id, $event->subject_id);
        $this->assertEquals([
            'previous_status' => 'in_progress',
            'new_status' => 'converted',
            'booking_id' => $booking->id,
            'assignee_id' => $this->a->id,
            'client_id' => $this->client->id,
        ], $event->metadata);
        $raw = json_encode($event->getAttributes());
        foreach ([self::CLIENT_NAME, self::CLIENT_EMAIL, self::CLIENT_PHONE, self::INTERNAL_NOTE, 'Контакт Не Аккаунт', 'Отель'] as $secret) {
            $this->assertStringNotContainsString($secret, $raw, 'audit evidence carries identifiers only');
        }
    }

    public function test_admin_converts_a_managers_inquiry_and_the_booking_stays_with_the_responsible_manager(): void
    {
        $inquiry = $this->ready();

        $this->submit($this->admin, $inquiry)->assertRedirect($this->show($inquiry))->assertSessionHas('success');

        $booking = Booking::query()->sole();
        $this->assertSame($this->a->id, $booking->manager_id, 'the Booking follows the responsible employee, not the converting Admin');
        $inquiry->refresh();
        $this->assertSame($this->admin->id, $inquiry->converted_by);
        $this->assertSame($this->a->id, $inquiry->created_by, 'historical creator is not replaced');
        $this->assertSame($this->a->id, $this->events()->sole()->metadata['assignee_id']);
        $this->assertSame($this->admin->id, $this->events()->sole()->actor_id);
    }

    public function test_admin_owned_inquiry_converts_to_an_admin_handled_booking(): void
    {
        $inquiry = $this->independentInquiry($this->admin, null, $this->client);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['destination_country' => 'Турция']);
        $inquiry->refresh();

        $this->submit($this->admin, $inquiry)->assertSessionHas('success');

        $this->assertSame($this->admin->id, Booking::query()->sole()->manager_id);
    }

    public function test_foreign_former_and_unrelated_actors_cannot_convert(): void
    {
        $inquiry = $this->ready();

        $this->submit($this->b, $inquiry)->assertNotFound();
        $this->submit($this->tourist, $inquiry)->assertForbidden();
        $this->submit($this->client, $inquiry)->assertForbidden();
        $this->assertNothingConverted($inquiry);

        // A former assignee (after reassignment) loses the right at once; the creator never had it.
        app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, 'Передача коллеге на время отпуска');
        $inquiry->refresh();
        $this->submit($this->a, $inquiry, ['expected_owner_id' => $this->a->id])->assertNotFound();
        $this->assertNothingConverted($inquiry);

        $this->submit($this->b, $inquiry)->assertSessionHas('success');
        $this->assertSame($this->b->id, Booking::query()->sole()->manager_id);
    }

    public function test_guest_is_redirected_to_login(): void
    {
        $inquiry = $this->ready();

        $this->post($this->convertUrl($inquiry), $this->payload($inquiry))->assertRedirect(route('login'));
        $this->assertNothingConverted($inquiry);
    }

    public function test_linked_external_inquiry_is_not_convertible_here(): void
    {
        [, $linked] = $this->linkedPair($this->a, $this->a, null, $this->client);

        $this->submit($this->a, $linked)->assertNotFound();
        $this->submit($this->admin, $linked)->assertNotFound();
        $this->assertSame(0, Booking::query()->count());

        $this->expectException(InquiryConversionException::class);
        $this->convertService($this->admin, $linked);
    }

    // ---- Eligibility ---------------------------------------------------------------------------

    public function test_missing_client_is_a_distinct_refusal_and_never_creates_a_client(): void
    {
        $inquiry = $this->independentInquiry($this->a);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['destination_country' => 'Турция']);
        (new InquiryContact())->forceFill([
            'inquiry_id' => $inquiry->id, 'contact_name' => self::CLIENT_NAME, 'contact_phone' => self::CLIENT_PHONE, 'revision' => 1,
        ])->save();
        $users = User::query()->count();

        $this->submit($this->a, $inquiry->refresh())
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHasErrors(InquiryConversionException::FIELD_CLIENT);

        $this->assertSame($users, User::query()->count(), 'no Tourist is created or inferred from contact data');
        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertNothingConverted($inquiry);
    }

    public function test_unavailable_clients_are_refused(): void
    {
        $inactive = $this->userWith('tourist', ['is_active' => false]);
        $notTourist = $this->userWith('manager');

        foreach ([$inactive, $notTourist] as $bad) {
            $inquiry = $this->ready(null, $this->client);
            DB::table('inquiries')->where('id', $inquiry->id)->update(['client_user_id' => $bad->id]);

            $this->submit($this->a, $inquiry->refresh())->assertSessionHasErrors(InquiryConversionException::FIELD_CLIENT);
            $this->assertSame(0, Booking::query()->count());
            DB::table('inquiries')->where('id', $inquiry->id)->update(['client_user_id' => $this->client->id]);
        }
    }

    public function test_pending_verification_request_is_not_bypassed(): void
    {
        $inquiry = $this->independentInquiry($this->a);
        DB::table('inquiries')->where('id', $inquiry->id)->update(['destination_country' => 'Турция']);
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), [
            'client_name' => 'Клиент Запроса', 'client_email' => 'unknown.client@example.test', 'client_phone' => '+7 900 111-22-33',
        ])->assertSessionHasNoErrors();
        $this->assertSame(1, ClientVerificationRequest::query()->where('status', 'pending')->count());

        // Without a linked client the conversion is refused as «missing client».
        $this->submit($this->a, $inquiry->refresh())->assertSessionHasErrors(InquiryConversionException::FIELD_CLIENT);

        // Even with an Admin-linked client, the still-pending request is a hard stop (as for closure).
        app(InquiryClientAssociation::class)->linkVisible($inquiry, $this->admin, $this->client->id, null);
        $inquiry->refresh();
        $this->submit($this->a, $inquiry)->assertSessionHasErrors(InquiryConversionException::FIELD_CLIENT);
        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->status);

        // The card explains the blocker and disables the action.
        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->assertSee('ожидает решения администратора');
    }

    public function test_closed_and_new_inquiries_are_refused(): void
    {
        $closed = $this->ready();
        app(InquiryClosure::class)->close($closed, $this->a, 'in_progress', $this->a->id, 'Клиент решил не ехать в этом сезоне');

        $this->submit($this->a, $closed->refresh(), ['expected_status' => 'closed'])->assertSessionHasErrors('conversion');
        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(Inquiry::STATUS_CLOSED, $closed->refresh()->status);
        $this->assertNull($closed->booking_id);
    }

    public function test_assignee_without_booking_eligibility_blocks_the_conversion(): void
    {
        $inquiry = $this->ready();
        DB::table('users')->where('id', $this->a->id)->update(['is_active' => false]);

        // A deactivated responsible account is also a deactivated actor: no authority at all.
        $this->submit($this->admin, $inquiry)->assertSessionHasErrors('conversion');
        $this->assertNothingConverted($inquiry);
    }

    // ---- Validation, spoofing, stale forms -----------------------------------------------------

    public function test_invalid_missing_and_spoofed_input_never_converts(): void
    {
        $inquiry = $this->ready();

        $this->submit($this->a, $inquiry, ['confirm_conversion' => null])->assertSessionHasErrors('confirm_conversion');
        $this->submit($this->a, $inquiry, ['start_date' => now()->subDay()->toDateString()])->assertSessionHasErrors('booking.start_date');
        $this->submit($this->a, $inquiry, ['destination_country' => ''])->assertSessionHasErrors('booking.destination_country');
        $this->submit($this->a, $inquiry, ['adults' => 0])->assertSessionHasErrors('booking.adults');
        $this->submit($this->a, $inquiry, ['children_ages' => 'abc'])->assertSessionHasErrors('booking.children_ages.0');

        foreach (['user_id' => $this->tourist->id, 'manager_id' => $this->b->id, 'status' => 'confirmed', 'tour_id' => 1, 'total_price' => '1000', 'paid_amount' => '1000', 'booking_id' => 7, 'client_user_id' => $this->tourist->id] as $field => $value) {
            $this->submit($this->a, $inquiry, [$field => $value])->assertSessionHasErrors('request');
        }
        // Spoofed keys nested inside booking[...] are rejected as well.
        $nested = $this->payload($inquiry);
        $nested['booking']['manager_id'] = $this->b->id;
        $this->actingAs($this->a)->post($this->convertUrl($inquiry), $nested)->assertSessionHasErrors('request');

        $this->assertNothingConverted($inquiry);
        $this->assertSame($this->client->id, $inquiry->refresh()->client_user_id);
    }

    public function test_stale_forms_are_refused_without_overwriting_anything(): void
    {
        $inquiry = $this->ready();

        $this->submit($this->a, $inquiry, ['expected_owner_id' => $this->b->id])->assertSessionHas('error');
        $this->submit($this->a, $inquiry, ['expected_client_id' => $this->tourist->id])->assertSessionHas('error');
        $this->submit($this->a, $inquiry, ['expected_client_id' => null])->assertSessionHas('error');
        $this->submit($this->a, $inquiry, ['expected_status' => 'new'])->assertSessionHas('error');
        $this->assertNothingConverted($inquiry);

        $this->actingAs($this->a)->post($this->convertUrl($inquiry), array_diff_key($this->payload($inquiry), ['expected_client_id' => 1]))
            ->assertSessionHasErrors('expected_client_id');
        $this->assertNothingConverted($inquiry);
    }

    public function test_changes_between_form_and_submit_win_over_the_form(): void
    {
        // Reassignment after the Admin opened the form: Admin's stale submission is refused.
        $inquiry = $this->ready();
        $form = $this->payload($inquiry);
        app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, 'Передача коллеге на время отпуска');
        $this->actingAs($this->admin)->post($this->convertUrl($inquiry), $form)->assertSessionHas('error');
        $this->assertNothingConverted($inquiry);

        // Client unlinked after the form: refused (no client), Admin may relink and retry.
        app(InquiryClientAssociation::class)->unlink($inquiry->refresh(), $this->admin, $this->client->id, 'Привязан по ошибке, нужен другой клиент');
        $this->actingAs($this->b)->post($this->convertUrl($inquiry), $this->payload($inquiry, ['expected_client_id' => $this->client->id]))->assertSessionHas('error');
        $this->assertNothingConverted($inquiry);

        // Closure first, conversion second.
        app(InquiryClientAssociation::class)->linkVisible($inquiry->refresh(), $this->admin, $this->client->id, null);
        app(InquiryClosure::class)->close($inquiry->refresh(), $this->b, 'in_progress', $this->b->id, 'Клиент решил не ехать в этом сезоне');
        $this->submit($this->b, $inquiry->refresh(), ['expected_status' => 'in_progress'])->assertSessionHasErrors('conversion');
        $this->assertSame(0, Booking::query()->count());
    }

    public function test_account_changes_between_form_and_submit(): void
    {
        $inquiry = $this->ready();

        DB::table('users')->where('id', $this->client->id)->update(['is_active' => false]);
        $this->submit($this->a, $inquiry)->assertSessionHasErrors(InquiryConversionException::FIELD_CLIENT);
        DB::table('users')->where('id', $this->client->id)->update(['is_active' => true]);

        // The acting manager was deactivated after the form was shown.
        DB::table('users')->where('id', $this->a->id)->update(['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        try {
            $this->convertService($this->a, $inquiry);
        } finally {
            $this->assertNothingConverted($inquiry);
        }
    }

    public function test_deleted_client_account_leaves_no_client_to_convert(): void
    {
        $inquiry = $this->ready();
        $this->client->delete();

        $this->assertNull($inquiry->refresh()->client_user_id, 'the database detaches a deleted account');
        $this->submit($this->a, $inquiry, ['expected_client_id' => $this->client->id])->assertSessionHas('error');
        $this->assertNothingConverted($inquiry);

        $this->submit($this->a, $inquiry, ['expected_client_id' => null])->assertSessionHasErrors(InquiryConversionException::FIELD_CLIENT);
        $this->assertNothingConverted($inquiry);
    }

    // ---- Gate B: one Inquiry, one Booking; retries; atomicity ----------------------------------

    public function test_repeated_submission_is_idempotent_and_stable(): void
    {
        $inquiry = $this->ready();

        $first = $this->convertService($this->a, $inquiry);
        $second = $this->convertService($this->a, $inquiry);
        $third = $this->convertService($this->admin, $inquiry);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertFalse($third['created']);
        $this->assertSame($first['booking']->id, $second['booking']->id);
        $this->assertSame($first['booking']->id, $third['booking']->id);
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, $this->events()->count(), 'a retry writes no second event');

        // Browser replay (double click / resubmitted form): stable message, still one Booking, no applied values.
        $this->submit($this->a, $inquiry->refresh(), ['destination_country' => 'Греция', 'expected_status' => 'in_progress'])
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'уже оформлено'));
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame('Турция', Booking::query()->sole()->destination_country);
        Mail::assertQueued(BookingCreatedMail::class, 1);
    }

    public function test_replay_by_a_user_who_lost_access_exposes_nothing(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);
        $bookingId = Booking::query()->sole()->id;

        // The inquiry is reassigned afterwards (Admin may still reassign a converted record? no: not in progress).
        $this->expectException(InquiryAssignmentException::class);
        try {
            app(InquiryAssignment::class)->assign($inquiry->refresh(), $this->admin, $this->b->id, $this->a->id, 'Передача коллеге на время отпуска');
        } finally {
            // A foreign Manager's replay is a plain 404 — no redirect to, nor mention of, the Booking.
            $response = $this->submit($this->b, $inquiry->refresh(), ['expected_status' => 'in_progress']);
            $response->assertNotFound();
            $this->assertStringNotContainsString("bookings/{$bookingId}", (string) $response->getContent());
        }
    }

    public function test_booking_creation_failure_rolls_everything_back(): void
    {
        $inquiry = $this->ready();

        // A NOT NULL violation in the Booking INSERT (required trip field missing) surfaces as a database failure.
        try {
            $this->convertService($this->a, $inquiry, ['departure_city' => null]);
            $this->fail('the failure must propagate');
        } catch (\Illuminate\Database\QueryException $e) {
            $this->assertStringContainsString('departure_city', $e->getMessage());
        }

        $this->assertNothingConverted($inquiry);
    }

    public function test_audit_failure_rolls_back_the_booking_and_the_conversion(): void
    {
        $inquiry = $this->ready();
        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('simulated audit failure');
            }
        });

        try {
            $this->convertService($this->a, $inquiry);
            $this->fail('the failure must propagate');
        } catch (RuntimeException $e) {
            $this->assertSame('simulated audit failure', $e->getMessage());
        }

        $this->assertNothingConverted($inquiry);
        Mail::assertNothingQueued();
    }

    public function test_database_refuses_a_second_booking_for_one_inquiry_and_a_shared_booking(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);
        $bookingId = Booking::query()->sole()->id;

        $other = $this->ready();
        $this->expectException(\Illuminate\Database\UniqueConstraintViolationException::class);
        DB::table('inquiries')->where('id', $other->id)->update(['booking_id' => $bookingId, 'status' => 'converted', 'converted_at' => now()]);
    }

    public function test_no_operator_booking_payment_or_settlement_is_created(): void
    {
        $tables = array_map(fn ($t) => $t->name ?? $t['name'], \Illuminate\Support\Facades\Schema::getTables());
        $this->assertSame([], array_values(array_filter($tables, fn ($t) => preg_match('/operator_booking|payment|settlement|receipt|ledger/i', (string) $t) === 1)), 'this slice owns no such tables');

        $counts = fn () => [DB::table('bookings')->count(), DB::table('users')->count(), DB::table('tours')->count(), DB::table('incoming_inquiries')->count(), DB::table('booking_documents')->count()];
        $inquiry = $this->ready();
        $before = $counts();

        $this->convertService($this->a, $inquiry);

        $after = $counts();
        $this->assertSame($before[0] + 1, $after[0]);
        $this->assertSame(array_slice($before, 1), array_slice($after, 1), 'no user, tour, incoming inquiry or document is created');
        $this->assertSame('0.00', (string) Booking::query()->sole()->paid_amount);
    }

    // ---- Gate C: converted-state semantics and privacy -----------------------------------------

    public function test_converted_inquiry_is_terminal_and_refuses_every_edit(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);
        $inquiry->refresh();
        $snapshot = $inquiry->only(['status', 'booking_id', 'assigned_to', 'client_user_id', 'draft_revision', 'destination_country', 'closed_at']);

        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => $inquiry->draft_revision, 'destination_country' => 'Греция'])->assertSessionHasErrors();
        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.contact.update', $inquiry), ['revision' => 0, 'contact_name' => 'Новое имя'])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_owner_id' => $this->a->id, 'reason' => 'Передача коллеге на время отпуска',
        ])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.client.unlink', $inquiry), [
            'expected_client_id' => $this->client->id, 'reason' => 'Привязан по ошибке, нужен другой клиент',
        ])->assertSessionHasErrors();
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.close', $inquiry), [
            'expected_status' => 'converted', 'expected_owner_id' => $this->a->id, 'reason' => 'Клиент решил не ехать в этом сезоне',
        ])->assertSessionHasErrors();

        $this->assertSame($snapshot, $inquiry->refresh()->only(array_keys($snapshot)));
        $this->assertSame(1, Booking::query()->count());
        $this->assertSame(1, AuditEvent::query()->where('event_type', '!=', AuditEventType::InquiryCreated->value)->count(), 'only the conversion event exists');
    }

    public function test_converted_inquiry_stays_readable_under_inquiry_authority(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->assertSee('Обращение оформлено в заявку');
        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk();
        $this->actingAs($this->b)->get($this->show($inquiry))->assertNotFound();
        $this->actingAs($this->tourist)->get($this->show($inquiry))->assertForbidden();
    }

    public function test_booking_not_the_converted_inquiry_grants_client_visibility(): void
    {
        $inquiry = $this->ready();
        $sees = fn (User $u) => ClientVisibility::touristsVisibleTo($u)->whereKey($this->client->id)->exists();

        $this->assertTrue($sees($this->a), 'open inquiry grants the client to its responsible manager');
        $this->assertFalse($sees($this->b));

        $this->convertService($this->a, $inquiry);
        $booking = Booking::query()->sole();

        // The Booking (manager A) now carries the entitlement; the converted inquiry alone would not.
        $this->assertTrue($sees($this->a));
        $this->assertFalse($sees($this->b));

        // Booking later reassigned to B: A keeps NO derived profile access (no other basis), B gains it.
        app(BookingAssignment::class)->assign($booking, $this->admin, $this->b->id, $this->a->id, 'workload', null);
        $this->assertFalse($sees($this->a), 'former inquiry assignee loses the Tourist profile once the Booking moved');
        $this->assertTrue($sees($this->b));
        $this->assertTrue($sees($this->admin));
        $this->assertFalse($sees($this->tourist));

        // The historical card stays readable for A, without a link or the client's profile.
        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()
            ->assertSee('Профиль клиента вам недоступен')
            ->assertSee('ссылка недоступна')
            ->assertDontSee(self::CLIENT_NAME)
            ->assertDontSee(self::CLIENT_EMAIL);
    }

    public function test_converted_state_alone_never_grants_and_other_bases_are_preserved(): void
    {
        $sees = fn (User $u) => ClientVisibility::touristsVisibleTo($u)->whereKey($this->client->id)->exists();

        // A converted inquiry whose Booking is deleted (soft) grants nothing.
        $converted = $this->ready();
        $this->convertService($this->a, $converted);
        Booking::query()->sole()->delete();
        $this->assertFalse($sees($this->a));

        // Another OPEN inquiry for the same client keeps the legitimate basis.
        $open = $this->ready($this->a);
        $this->assertTrue($sees($this->a));
        DB::table('inquiries')->where('id', $open->id)->update(['status' => 'closed', 'closed_at' => now()]);
        $this->assertFalse($sees($this->a), 'closed still grants nothing (AOA-04-I6)');

        // A legitimate Booking elsewhere is an independent basis.
        $this->bookingOf($this->client, $this->a);
        $this->assertTrue($sees($this->a));
        $this->assertFalse($sees($this->b));
    }

    public function test_booking_access_chat_and_documents_stay_isolated_after_conversion(): void
    {
        $inquiry = $this->ready();
        $other = $this->bookingOf($this->client, $this->b);
        $this->convertService($this->a, $inquiry);
        $booking = Booking::query()->where('manager_id', $this->a->id)->sole();

        $this->actingAs($this->a)->get(route('bookings.show', $booking))->assertOk();
        $this->actingAs($this->b)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($this->a)->get(route('bookings.show', $other))->assertForbidden();
        $this->actingAs($this->client)->get(route('bookings.show', $booking))->assertOk();
        $this->actingAs($this->tourist)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($this->b)->post(route('bookings.documents.store', $booking), [])->assertForbidden();
        $this->actingAs($this->a)->get(route('bookings.show', $booking))->assertDontSee(self::INTERNAL_NOTE);
    }

    // ---- Notifications ------------------------------------------------------------------------

    public function test_exactly_one_booking_created_notice_with_fake_transports_and_failure_does_not_roll_back(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);

        Mail::assertQueued(BookingCreatedMail::class, 1);
        Mail::assertQueued(\App\Mail\AdminBookingCreated::class, 1);
        Mail::assertNothingSent();

        // A failing listener (SMTP down) is logged and swallowed: the Booking stays.
        $second = $this->ready($this->b);
        \Illuminate\Support\Facades\Event::listen(\App\Events\BookingCreated::class, function (): never {
            throw new RuntimeException('simulated notifier failure');
        });
        $result = $this->convertService($this->b, $second);

        $this->assertTrue($result['created']);
        $this->assertSame(Inquiry::STATUS_CONVERTED, $second->refresh()->status);
        $this->assertSame(2, Booking::query()->count());
    }

    // ---- Gate D: UI, navigation, counters ------------------------------------------------------

    public function test_eligible_card_explains_the_conversion_and_shows_who_is_who(): void
    {
        $inquiry = $this->ready();

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()
            ->assertSee('Оформить заявку')
            ->assertSee('создаёт одну заявку')
            ->assertSee(self::CLIENT_NAME)
            ->assertSee('Анна Синтетическая')
            ->assertSee('name="confirm_conversion"', false)
            ->assertSee('name="booking[destination_city]" value="Анталья"', false)
            ->assertSee('Отель у моря, питание всё включено')
            ->assertDontSee('id="agency-inquiry-convert-blocked"', false);

        // Pre-fill from the draft never carries internal notes into the conversion form's client-visible field.
        $html = $this->actingAs($this->a)->get($this->show($inquiry))->getContent();
        $form = substr($html, (int) strpos($html, 'id="agency-inquiry-convert"'), 9000);
        $this->assertStringNotContainsString(self::INTERNAL_NOTE, $form);
    }

    public function test_card_without_client_blocks_and_explains_without_leaking(): void
    {
        $inquiry = $this->independentInquiry($this->a);

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()
            ->assertSee('id="agency-inquiry-convert-blocked"', false)
            ->assertSee('сначала привяжите к обращению существующий аккаунт клиента')
            ->assertSee('disabled', false);
    }

    public function test_converted_card_is_read_only_with_an_authorized_booking_link(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);
        $booking = Booking::query()->sole();

        foreach ([$this->a, $this->admin] as $actor) {
            $response = $this->actingAs($actor)->get($this->show($inquiry))->assertOk();
            $response->assertSee('Обращение оформлено в заявку')
                ->assertSee('Конвертировано в заявку')
                ->assertSee('href="'.route('bookings.show', $booking).'"', false)
                ->assertDontSee('id="agency-inquiry-convert"', false)
                ->assertDontSee('Сохранить черновик')
                ->assertDontSee('Закрыть обращение без заявки')
                ->assertDontSee('Снять привязку клиента');
        }

        // The link target is itself authorised by BookingPolicy.
        $this->actingAs($this->a)->get(route('bookings.show', $booking))->assertOk();

        // A soft-deleted Booking is reported as deleted, not linked.
        $booking->delete();
        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()
            ->assertSee('Созданная заявка удалена')
            ->assertDontSee('id="agency-inquiry-booking-link"', false);
        $this->assertSame(Inquiry::STATUS_CONVERTED, $inquiry->refresh()->status);
    }

    public function test_hard_deleting_the_client_account_keeps_a_readable_converted_record(): void
    {
        $inquiry = $this->ready();
        $this->convertService($this->a, $inquiry);
        $this->assertSame(1, Booking::query()->count());

        // Existing product design: deleting a Tourist account cascades to their Bookings (bookings.user_id) and the
        // Inquiry link is detached (SET NULL). The Inquiry keeps its terminal status and history and must not break.
        $this->client->delete();

        $inquiry->refresh();
        $this->assertSame(0, Booking::query()->withTrashed()->count());
        $this->assertSame(Inquiry::STATUS_CONVERTED, $inquiry->status);
        $this->assertNull($inquiry->booking_id);
        $this->assertNull($inquiry->client_user_id);
        $this->assertSame(1, $this->events()->count(), 'the audit evidence of the conversion is kept');

        foreach ([$this->a, $this->admin] as $actor) {
            $this->actingAs($actor)->get($this->show($inquiry))->assertOk()
                ->assertSee('Созданная заявка удалена')
                ->assertDontSee('id="agency-inquiry-booking-link"', false);
        }
        $this->assertSame(1, InquiryWorkloadSummary::forIndependent($this->a)['converted']);

        // A retry stays safe: no second Booking can appear for the record.
        $this->submit($this->a, $inquiry, ['expected_client_id' => null])->assertSessionHasErrors('conversion');
        $this->assertSame(0, Booking::query()->withTrashed()->count());
        $this->assertSame(1, $this->events()->count());
    }

    public function test_counters_show_converted_and_stay_scoped_and_arithmetic(): void
    {
        $open = $this->ready();
        $toConvert = $this->ready();
        $closedInquiry = $this->ready();
        app(InquiryClosure::class)->close($closedInquiry, $this->a, 'in_progress', $this->a->id, 'Клиент решил не ехать в этом сезоне');
        $this->convertService($this->a, $toConvert);

        $foreign = $this->ready($this->b);
        $this->convertService($this->b, $foreign);
        $this->linkedPair($this->a, $this->a);

        $this->assertSame(['total' => 3, 'in_progress' => 1, 'converted' => 1, 'closed' => 1], InquiryWorkloadSummary::forIndependent($this->a));
        $this->assertSame(['total' => 1, 'in_progress' => 0, 'converted' => 1, 'closed' => 0], InquiryWorkloadSummary::forIndependent($this->b));
        $this->assertSame(['total' => 4, 'in_progress' => 1, 'converted' => 2, 'closed' => 1], InquiryWorkloadSummary::forIndependent($this->admin));
        $this->assertSame(['total' => 0, 'in_progress' => 0, 'converted' => 0, 'closed' => 0], InquiryWorkloadSummary::forIndependent($this->tourist));

        foreach ([$this->a, $this->b, $this->admin] as $actor) {
            $s = InquiryWorkloadSummary::forIndependent($actor);
            $this->assertSame($s['total'], $s['in_progress'] + $s['converted'] + $s['closed']);
        }

        $page = $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries'))->assertOk();
        $page->assertSee('data-summary="converted">1<', false)->assertSee('Конвертировано');
        $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries', ['status' => 'converted']))->assertOk()->assertSee('Конвертировано в заявку');
        $this->assertTrue($open->refresh()->isOpen());
    }

    // ---- Interplay with earlier AOA slices ----------------------------------------------------

    public function test_closed_inquiry_visibility_rule_is_unchanged_and_open_still_grants(): void
    {
        $inquiry = $this->ready();
        $sees = fn (User $u) => ClientVisibility::touristsVisibleTo($u)->whereKey($this->client->id)->exists();

        $this->assertTrue($sees($this->a));
        app(InquiryClosure::class)->close($inquiry, $this->a, 'in_progress', $this->a->id, 'Клиент решил не ехать в этом сезоне');
        $this->assertFalse($sees($this->a));
    }

    public function test_conversion_audit_uses_the_registered_type_and_label(): void
    {
        $this->assertSame('inquiry.converted', AuditEventType::InquiryConverted->value);
        $this->assertSame('Обращение оформлено как заявка', AuditEventType::InquiryConverted->label());
    }

    public function test_service_refuses_linked_inquiry_and_unauthorized_actor_before_any_write(): void
    {
        $inquiry = $this->ready();

        try {
            $this->convertService($this->b, $inquiry);
            $this->fail('foreign manager must be refused');
        } catch (AuthorizationException) {
            $this->assertNothingConverted($inquiry);
        }

        try {
            $this->convertService($this->tourist, $inquiry);
            $this->fail('tourist must be refused');
        } catch (AuthorizationException) {
            $this->assertNothingConverted($inquiry);
        }
    }
}
