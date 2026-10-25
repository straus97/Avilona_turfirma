<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use App\Policies\InquiryPolicy;
use App\Services\Accounts\ClientVisibility;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryAssignment;
use App\Services\Inquiries\InquiryAssignmentException;
use App\Support\Audit\AuditEventType;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I2 — Admin-only assignment / reassignment of an INDEPENDENT Inquiry through the real routes,
 * with durable audit history. Synthetic data, SQLite :memory:.
 */
class InquiryAssignmentTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const REASON = 'Менеджер ушёл в отпуск, передаём коллеге';

    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager', ['name' => 'Анна Синтетическая']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Синтетический']);
        $this->tourist = $this->userWith('tourist');
    }

    private function show(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private function assignUrl(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.assign', $inquiry);
    }

    private function mine(?User $assignee = null): Inquiry
    {
        return Inquiry::factory()->managerCreated($assignee ?? $this->a)->create()->refresh();
    }

    private function submit(User $actor, Inquiry $inquiry, ?User $to, ?User $expected, string $reason = self::REASON)
    {
        return $this->actingAs($actor)->post($this->assignUrl($inquiry), [
            'assignee_id' => $to?->id,
            'expected_assignee_id' => $expected?->id,
            'reason' => $reason,
        ]);
    }

    private function events(?Inquiry $inquiry = null)
    {
        return AuditEvent::query()
            ->whereIn('event_type', [AuditEventType::InquiryAssigned->value, AuditEventType::InquiryReassigned->value])
            ->when($inquiry, fn ($q) => $q->where('subject_type', 'inquiry')->where('subject_id', $inquiry->id))
            ->orderBy('id');
    }

    // ---- Admin reassignment ------------------------------------------------------------------

    public function test_admin_reassigns_a_manual_inquiry_and_provenance_is_preserved(): void
    {
        $inquiry = $this->mine();
        $before = $inquiry->only(['origin', 'contact_channel', 'created_by', 'status', 'incoming_inquiry_id', 'booking_id', 'client_user_id', 'draft_revision']);
        $bookings = Booking::query()->count();
        $incoming = IncomingInquiry::query()->count();

        $this->submit($this->admin, $inquiry, $this->b, $this->a)
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame($this->b->id, $inquiry->assigned_to);
        $this->assertSame($before, $inquiry->only(array_keys($before)), 'creator, origin, channel, status and links must not change');
        $this->assertSame($this->a->id, $inquiry->created_by);
        $this->assertSame($bookings, Booking::query()->count());
        $this->assertSame($incoming, IncomingInquiry::query()->count());

        $event = $this->events($inquiry)->sole();
        $this->assertSame(AuditEventType::InquiryReassigned->value, $event->event_type);
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame(
            ['previous_assignee_id' => $this->a->id, 'new_assignee_id' => $this->b->id, 'reason' => self::REASON],
            $event->metadata
        );
    }

    public function test_admin_may_assign_to_another_admin_by_the_existing_eligibility_rule(): void
    {
        $inquiry = $this->mine();
        $otherAdmin = $this->userWith('admin');

        $this->submit($this->admin, $inquiry, $otherAdmin, $this->a)->assertSessionHas('success');

        $this->assertSame($otherAdmin->id, $inquiry->refresh()->assigned_to);
        // An Admin assignee is not a "responsible manager" - Manager scoping is unaffected.
        $this->assertFalse($this->a->can('view', $inquiry));
        $this->assertFalse($this->b->can('view', $inquiry));
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('ineligibleTargets')]
    public function test_ineligible_targets_are_rejected_without_a_trace(string $kind): void
    {
        $inquiry = $this->mine();
        $target = match ($kind) {
            'tourist' => $this->tourist,
            'inactive_manager' => $this->userWith('manager', ['is_active' => false]),
            'no_role' => User::factory()->create(),
            'missing' => null,
        };

        $request = $this->actingAs($this->admin)->post($this->assignUrl($inquiry), [
            'assignee_id' => $target?->id ?? 999999,
            'expected_assignee_id' => $this->a->id,
            'reason' => self::REASON,
        ]);

        $request->assertRedirect($this->show($inquiry))->assertSessionHasErrors('assignee_id');
        $this->assertSame($this->a->id, $inquiry->refresh()->assigned_to);
        $this->assertSame(0, $this->events()->count());
    }

    public static function ineligibleTargets(): array
    {
        return ['tourist' => ['tourist'], 'inactive manager' => ['inactive_manager'], 'no role' => ['no_role'], 'missing user' => ['missing']];
    }

    // ---- Reason ------------------------------------------------------------------------------

    #[\PHPUnit\Framework\Attributes\DataProvider('badReasons')]
    public function test_reason_is_required_bounded_and_screened(string $reason): void
    {
        $inquiry = $this->mine();

        $this->submit($this->admin, $inquiry, $this->b, $this->a, $reason)->assertSessionHasErrors('reason');

        $this->assertSame($this->a->id, $inquiry->refresh()->assigned_to);
        $this->assertSame(0, $this->events()->count());
    }

    public static function badReasons(): array
    {
        return [
            'empty' => [''],
            'blank' => ['     '],
            'too short' => ['да'],
            'too long' => [str_repeat('а', InquiryAssignment::REASON_MAX_LENGTH + 1)],
            'huge' => [str_repeat('а', 5000)],
            'email' => ['Клиент пишет на ivan.petrov@example.test про тур'],
            'phone' => ['Звонить клиенту +7 (900) 123-45-67 срочно'],
            'path' => ['Паспорт лежит в C:\\scans\\passport.pdf'],
            'secret-like' => ['Пароль клиента abcdefghijklmnopqrstuvwxyz0123456789'],
        ];
    }

    public function test_reason_at_the_limits_is_accepted_and_whitespace_is_normalised(): void
    {
        $inquiry = $this->mine();
        $max = str_repeat('б', InquiryAssignment::REASON_MAX_LENGTH);

        $this->submit($this->admin, $inquiry, $this->b, $this->a, $max)->assertSessionHas('success');
        $this->assertSame($max, $this->events($inquiry)->sole()->metadata['reason']);

        $this->submit($this->admin, $inquiry, $this->a, $this->b, "  Возврат \n\n  после   отпуска  ")->assertSessionHas('success');
        $this->assertSame('Возврат после отпуска', $this->events($inquiry)->reorder('id', 'desc')->first()->metadata['reason']);
    }

    // ---- Who may do it -----------------------------------------------------------------------

    public function test_manager_cannot_assign_reassign_or_claim_through_the_endpoint(): void
    {
        $own = $this->mine($this->a);
        $foreign = $this->mine($this->b);
        $other = $this->userWith('manager');

        // Own inquiry: visible, but no right (403). Foreign: indistinguishable from missing (404).
        $this->submit($this->a, $own, $other, $this->a)->assertForbidden();
        $this->submit($this->a, $own, $this->a, $this->a)->assertForbidden();
        $this->submit($this->a, $foreign, $this->a, $this->b)->assertNotFound();
        $this->actingAs($this->a)->post($this->assignUrl($foreign), [])->assertNotFound();
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.assign', 999999), [])->assertNotFound();

        $this->assertSame($this->a->id, $own->refresh()->assigned_to);
        $this->assertSame($this->b->id, $foreign->refresh()->assigned_to);
        $this->assertSame(0, $this->events()->count());
    }

    public function test_manager_cannot_pick_an_employee_through_the_draft_endpoint(): void
    {
        $own = $this->mine($this->a);

        foreach (['assigned_to' => $this->b->id, 'assignee_id' => $this->b->id, 'created_by' => $this->b->id] as $field => $value) {
            $this->actingAs($this->a)
                ->put(route('cabinet.manager.agency-inquiries.draft.update', $own), ['revision' => 0, $field => $value])
                ->assertSessionHasErrors();
        }

        $own->refresh();
        $this->assertSame($this->a->id, $own->assigned_to);
        $this->assertSame($this->a->id, $own->created_by);
        $this->assertSame(0, $this->events()->count());
    }

    public function test_tourist_roleless_user_and_guest_are_denied(): void
    {
        $inquiry = $this->mine();
        $payload = ['assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => self::REASON];

        $this->submit($this->tourist, $inquiry, $this->b, $this->a)->assertForbidden();
        $this->submit(User::factory()->create(), $inquiry, $this->b, $this->a)->assertForbidden();

        auth()->logout();
        parent::post($this->assignUrl($inquiry), $payload)->assertRedirect(route('login'));

        $this->assertSame($this->a->id, $inquiry->refresh()->assigned_to);
        $this->assertSame(0, $this->events()->count());
    }

    public function test_policy_allows_assignment_to_admins_only(): void
    {
        $policy = new InquiryPolicy();
        $inquiry = $this->mine();
        $adminManager = $this->userWith('admin');
        $adminManager->roles()->attach(\App\Models\Role::firstOrCreate(['name' => 'manager'], ['description' => 'm'])->id);

        $this->assertTrue($policy->assign($this->admin, $inquiry));
        $this->assertTrue($policy->assign($adminManager->refresh(), $inquiry));
        $this->assertFalse($policy->assign($this->a, $inquiry));
        $this->assertFalse($policy->assign($this->tourist, $inquiry));
        $this->assertFalse($policy->assign(User::factory()->create(), $inquiry));
    }

    public function test_service_rechecks_the_actor_under_lock(): void
    {
        $inquiry = $this->mine();
        $service = app(InquiryAssignment::class);

        $demoted = $this->userWith('admin');
        DB::table('role_user')->where('user_id', $demoted->id)->delete();

        try {
            $service->assign($inquiry, $demoted->refresh(), $this->b->id, $this->a->id, self::REASON);
            $this->fail('A de-roled admin must not assign.');
        } catch (AuthorizationException) {
        }

        $deactivated = $this->userWith('admin', ['is_active' => false]);
        $this->expectException(AuthorizationException::class);
        try {
            $service->assign($inquiry, $deactivated, $this->b->id, $this->a->id, self::REASON);
        } finally {
            $this->assertSame($this->a->id, $inquiry->refresh()->assigned_to);
            $this->assertSame(0, $this->events()->count());
        }
    }

    // ---- Access after the transfer -----------------------------------------------------------

    public function test_old_assignee_loses_and_new_assignee_gains_access_immediately(): void
    {
        $client = $this->tourist;
        $inquiry = $this->independentInquiry($this->a, null, $client);
        $otherBooking = $this->bookingOf($client, $this->a);
        $inquiry->forceFill(['internal_notes' => 'Заметка для передачи'])->save();

        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($client->id)->exists());
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->b)->whereKey($client->id)->exists());

        $this->submit($this->admin, $inquiry, $this->b, $this->a)->assertSessionHas('success');

        // Former assignee: detail, draft, list, client entitlement - all gone.
        $this->actingAs($this->a)->get($this->show($inquiry))->assertNotFound();
        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => 0, 'preferences' => 'X'])->assertNotFound();
        $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->assertDontSee($this->show($inquiry));
        $this->assertFalse(Inquiry::query()->visibleTo($this->a)->whereKey($inquiry->id)->exists());

        // New assignee: detail, draft save and list.
        $this->actingAs($this->b)->get($this->show($inquiry))->assertOk()->assertSee('Заметка для передачи');
        $this->actingAs($this->b)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->assertSee($this->show($inquiry));
        $this->actingAs($this->b)
            ->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => 0, 'preferences' => 'Новый менеджер'])
            ->assertSessionHas('success');
        $this->assertSame('Новый менеджер', $inquiry->refresh()->preferences);

        // The independent Inquiry's client is now a visible client of B (same meaning as for A before) - and A keeps
        // visibility only through the unrelated Booking, never through the transferred Inquiry.
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->b)->whereKey($client->id)->exists());
        $this->assertSame($this->a->id, $otherBooking->refresh()->manager_id, 'the Booking is not reassigned');

        // B did not gain the unrelated Booking, its chat or documents.
        $this->actingAs($this->b)->get(route('bookings.show', $otherBooking))->assertForbidden();
        $this->actingAs($this->b)->get(route('cabinet.manager.chat', ['bookingId' => $otherBooking->id]))->assertNotFound();

        // Admin oversight unchanged.
        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk();
    }

    public function test_client_visibility_through_the_inquiry_moves_with_the_transfer(): void
    {
        $client = $this->tourist;
        $inquiry = $this->independentInquiry($this->a, null, $client);

        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($client->id)->exists());

        $this->submit($this->admin, $inquiry, $this->b, $this->a);

        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->a)->whereKey($client->id)->exists(), 'A must not keep the client through a transferred Inquiry');
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->b)->whereKey($client->id)->exists());
    }

    public function test_reassignment_races_with_an_open_draft_form_of_the_former_assignee(): void
    {
        $inquiry = $this->mine($this->a);
        $form = $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->getContent();
        $this->assertStringContainsString('name="revision" value="0"', $form);
        $this->assertStringNotContainsString('История назначений', $form);

        $this->submit($this->admin, $inquiry, $this->b, $this->a)->assertSessionHas('success');

        // The stale tab of A now submits with the right revision: no write, no existence leak.
        $this->actingAs($this->a)
            ->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => 0, 'preferences' => 'Правка со старой вкладки'])
            ->assertNotFound();

        $inquiry->refresh();
        $this->assertNull($inquiry->preferences);
        $this->assertSame(0, (int) $inquiry->draft_revision);
        $this->assertSame(0, AuditEvent::query()->where('event_type', AuditEventType::InquiryDraftUpdated->value)->count());
    }

    // ---- Idempotency, staleness --------------------------------------------------------------

    public function test_repeating_the_same_transfer_is_a_safe_noop(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->admin, $inquiry, $this->b, $this->a)->assertSessionHas('success');
        $assignedAt = $inquiry->refresh()->assigned_at;

        // Double submit of the same stale form, and a deliberate "assign the current person".
        $second = $this->submit($this->admin, $inquiry, $this->b, $this->a);
        $second->assertRedirect($this->show($inquiry))->assertSessionHas('success');
        $this->assertStringContainsString('изменений нет', session('success'));
        $this->submit($this->admin, $inquiry, $this->b, $this->b)->assertSessionHas('success');

        $this->assertSame(1, $this->events($inquiry)->count());
        $this->assertEquals($assignedAt, $inquiry->refresh()->assigned_at);
        $this->assertSame($this->b->id, $inquiry->assigned_to);
    }

    public function test_a_stale_admin_tab_cannot_overwrite_a_more_recent_reassignment(): void
    {
        $inquiry = $this->mine();
        $c = $this->userWith('manager');

        // Both Admin tabs were rendered while A was responsible. Admin 1 hands over to B first.
        $this->submit($this->admin, $inquiry, $this->b, $this->a)->assertSessionHas('success');

        $second = $this->userWith('admin');
        $this->submit($second, $inquiry, $c, $this->a)
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHas('error');

        $this->assertSame($this->b->id, $inquiry->refresh()->assigned_to);
        $events = $this->events($inquiry)->get();
        $this->assertCount(1, $events);
        $this->assertSame($this->admin->id, $events->first()->actor_id);
    }

    public function test_missing_expected_assignee_field_is_refused(): void
    {
        $inquiry = $this->mine();

        $this->actingAs($this->admin)
            ->post($this->assignUrl($inquiry), ['assignee_id' => $this->b->id, 'reason' => self::REASON])
            ->assertSessionHasErrors('expected_assignee_id');

        $this->assertSame($this->a->id, $inquiry->refresh()->assigned_to);
    }

    // ---- Initial assignment after the assignee account was deleted ---------------------------

    public function test_inquiry_orphaned_by_a_deleted_assignee_can_be_assigned_and_keeps_its_author(): void
    {
        $inquiry = $this->mine($this->a);
        $this->a->delete();

        $inquiry->refresh();
        $this->assertNull($inquiry->assigned_to, 'the database nulls the assignee when the account is deleted');
        $this->assertNull($inquiry->created_by, 'the database nulls the creator when the account is deleted');

        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->assertSee('не назначен')->assertSee('Назначить сотрудника');

        $this->submit($this->admin, $inquiry, $this->b, null)->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame($this->b->id, $inquiry->assigned_to);
        $this->assertNull($inquiry->created_by, 'authorship is never rewritten');
        $this->assertSame(Inquiry::ORIGIN_MANAGER_CREATED, $inquiry->origin);

        $event = $this->events($inquiry)->sole();
        $this->assertSame(AuditEventType::InquiryAssigned->value, $event->event_type);
        $this->assertNull($event->metadata['previous_assignee_id']);
        $this->assertSame($this->b->id, $event->metadata['new_assignee_id']);

        $this->actingAs($this->b)->get($this->show($inquiry))->assertOk();
    }

    public function test_the_creator_of_an_existing_inquiry_still_cannot_be_changed_or_cleared_by_the_application(): void
    {
        $inquiry = $this->mine($this->a);

        foreach ([null, $this->b->id] as $creator) {
            try {
                $inquiry->refresh()->forceFill(['created_by' => $creator])->save();
                $this->fail('created_by of an existing inquiry must be immutable for application code');
            } catch (InquiryInvariantViolation) {
            }
        }

        $this->assertSame($this->a->id, $inquiry->refresh()->created_by);
    }

    // ---- State and provenance guards ---------------------------------------------------------

    public function test_linked_external_inquiries_are_not_reassigned_by_the_new_domain(): void
    {
        [$source, $linked] = $this->linkedPair($this->a, $this->a);
        $legacyBefore = $source->only(['assigned_to', 'workflow_state', 'booking_id', 'client_user_id']);
        $inquiryBefore = $linked->only(['assigned_to', 'status', 'origin', 'created_by']);
        $incoming = IncomingInquiry::query()->count();

        // Route: linked records are not part of this screen at all (404 for everyone).
        $this->submit($this->admin, $linked, $this->b, $this->a)->assertNotFound();
        $this->submit($this->a, $linked, $this->b, $this->a)->assertNotFound();

        // Service: fail closed even if called directly.
        try {
            app(InquiryAssignment::class)->assign($linked, $this->admin, $this->b->id, $this->a->id, self::REASON);
            $this->fail('A linked external Inquiry must not be reassigned by the new domain.');
        } catch (InquiryAssignmentException $e) {
            $this->assertStringContainsString('Входящие обращения', $e->getMessage());
        }

        $this->assertSame($legacyBefore, $source->refresh()->only(array_keys($legacyBefore)));
        $this->assertSame($inquiryBefore, $linked->refresh()->only(array_keys($inquiryBefore)));
        $this->assertSame($incoming, IncomingInquiry::query()->count());
        $this->assertSame(0, $this->events()->count());

        // The legacy E5 reassignment still works and the Inquiry side stays closed on divergence (AOA-03-I3).
        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.reassign', $source), [
            'assigned_to' => $this->b->id,
            'reason' => 'Передача по рабочему процессу E5 для проверки',
            'expected_assigned_to' => $this->a->id,
        ]);
        $this->assertSame($this->a->id, $linked->refresh()->assigned_to, 'the new domain does not mirror the legacy change');
    }

    public function test_unassigned_new_or_closed_or_converted_inquiries_are_not_assignable(): void
    {
        $service = app(InquiryAssignment::class);

        // Independent but in the invalid "new" state (impossible through the services; written raw on purpose).
        $new = $this->mine();
        DB::table('inquiries')->where('id', $new->id)->update(['status' => 'new', 'assigned_to' => null]);

        $closed = $this->mine();
        $closed->forceFill(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()])->save();

        foreach ([$new->refresh(), $closed->refresh()] as $row) {
            $before = $row->only(['status', 'assigned_to']);
            try {
                $service->assign($row, $this->admin, $this->b->id, $row->assigned_to, self::REASON);
                $this->fail('A non-in-progress inquiry must not be assigned.');
            } catch (InquiryAssignmentException $e) {
                $this->assertStringContainsString('не находится в работе', $e->getMessage());
            }
            $this->assertSame($before, $row->refresh()->only(['status', 'assigned_to']));
        }

        $this->assertSame(0, $this->events()->count());
        $this->actingAs($this->admin)->get($this->show($closed))->assertOk()->assertSee('не находится в работе')->assertDontSee('Передать обращение', false);
    }

    // ---- Audit atomicity ---------------------------------------------------------------------

    public function test_audit_failure_rolls_the_reassignment_back(): void
    {
        $inquiry = $this->mine();

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });

        try {
            app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, self::REASON);
            $this->fail('The reassignment persisted although the audit event failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $fresh = Inquiry::query()->find($inquiry->id);
        $this->assertSame($this->a->id, $fresh->assigned_to);
        $this->assertEquals($inquiry->assigned_at, $fresh->assigned_at);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_rejected_attempts_leave_no_audit_event(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->admin, $inquiry, $this->tourist, $this->a);
        $this->submit($this->admin, $inquiry, $this->b, $this->a, 'x');
        $this->submit($this->admin, $inquiry, $this->b, $this->b); // stale

        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertSame($this->a->id, $inquiry->refresh()->assigned_to);
    }

    // ---- UI and history ----------------------------------------------------------------------

    public function test_admin_card_shows_assignment_controls_eligible_staff_and_real_history_only(): void
    {
        $inquiry = $this->mine();
        $inactive = $this->userWith('manager', ['name' => 'Неактивный Сотрудник', 'is_active' => false]);

        $html = $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->getContent();

        $this->assertStringContainsString('Ответственный менеджер', $html);
        $this->assertStringContainsString('Передать обращение', $html);
        $this->assertStringContainsString('Причина переназначения', $html);
        $this->assertStringContainsString('История назначений', $html);
        $this->assertStringContainsString('Изменений ответственного пока не записано', $html);
        $this->assertStringContainsString('Борис Синтетический (менеджер)', $html);
        $this->assertStringNotContainsString('Неактивный Сотрудник', $html);
        $this->assertStringNotContainsString($this->tourist->name . ' (', $html);
        $this->assertStringContainsString('name="expected_assignee_id" value="' . $this->a->id . '"', $html);
        $this->assertStringContainsString('паспортные данные', $html);
        $this->assertDoesNotMatchRegularExpression('/<option value="' . $this->a->id . '"/', $html, 'the current assignee is not offered');

        // A transfer made behind the audit's back (raw SQL) must not appear as history.
        DB::table('inquiries')->where('id', $inquiry->id)->update(['assigned_to' => $this->b->id]);
        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->assertSee('Изменений ответственного пока не записано');
    }

    public function test_history_is_ordered_newest_first_and_does_not_invent_names_or_reasons(): void
    {
        $inquiry = $this->mine();
        $c = $this->userWith('manager', ['name' => 'Виктор Временный']);

        $this->submit($this->admin, $inquiry, $this->b, $this->a, 'Первая причина передачи');
        $this->submit($this->admin, $inquiry, $c, $this->b, 'Вторая причина передачи');
        $this->submit($this->admin, $inquiry, $this->a, $c, 'Третья причина передачи');

        $history = app(InquiryAssignment::class)->history($inquiry);
        $this->assertSame(['Третья причина передачи', 'Вторая причина передачи', 'Первая причина передачи'], $history->pluck('reason')->all());
        $this->assertSame([$this->a->name, $c->name, $this->b->name], $history->pluck('new')->all());
        $this->assertSame([$c->name, $this->b->name, $this->a->name], $history->pluck('previous')->all());
        $this->assertSame([$this->admin->name], $history->pluck('actor')->unique()->all());

        // Deleting an account must not invent a name.
        $c->delete();
        $html = $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->getContent();
        $this->assertStringContainsString('учётная запись удалена', $html);
        $this->assertStringNotContainsString('Виктор Временный', $html);
        $this->assertStringContainsString('Третья причина передачи', $html);
        $this->assertLessThan(strpos($html, 'Первая причина передачи'), strpos($html, 'Третья причина передачи'));
    }

    public function test_history_and_controls_are_admin_only_and_reasons_stay_internal(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->admin, $inquiry, $this->b, $this->a, 'Внутренняя причина передачи');

        $html = $this->actingAs($this->b)->get($this->show($inquiry))->assertOk()->getContent();

        foreach (['История назначений', 'Внутренняя причина передачи', 'Передать обращение', 'Причина переназначения', 'Назначить сотрудника', 'expected_assignee_id', $this->assignUrl($inquiry)] as $needle) {
            $this->assertStringNotContainsString($needle, $html, "Manager view leaks admin-only [$needle]");
        }
        $this->assertStringContainsString('Борис Синтетический', $html, 'the current assignee name is visible where already authorised');

        // The former assignee gets no data at all.
        $this->actingAs($this->a)->get($this->show($inquiry))->assertNotFound()->assertDontSee('Внутренняя причина передачи');
        // A Manager error response carries no foreign details either.
        $this->submit($this->a, $inquiry, $this->a, $this->b)->assertNotFound()->assertDontSee('Внутренняя причина передачи')->assertDontSee('Борис');
    }

    public function test_pages_do_not_expose_user_models_or_contact_data(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->admin, $inquiry, $this->b, $this->a);

        $html = $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->getContent();

        foreach ([$this->a, $this->b, $this->admin] as $user) {
            $this->assertStringNotContainsString($user->email, $html);
            $this->assertStringNotContainsString((string) $user->password, $html);
        }
        $this->assertStringNotContainsString('"remember_token"', $html);
        $this->assertStringNotContainsString('previous_assignee_id', $html, 'raw audit JSON must not be rendered');

        $this->assertArrayNotHasKey('assignee', $inquiry->refresh()->load('assignee', 'creator')->toArray());
        $this->assertArrayNotHasKey('creator', $inquiry->toArray());
    }

    public function test_audit_event_type_labels_exist_for_assignment_events(): void
    {
        $this->assertSame('Обращение назначено', AuditEventType::InquiryAssigned->label());
        $this->assertSame('Обращение переназначено', AuditEventType::InquiryReassigned->label());
    }
}
