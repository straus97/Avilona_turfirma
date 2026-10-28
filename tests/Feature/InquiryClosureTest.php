<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\InquiryContact;
use App\Models\User;
use App\Services\Accounts\ClientVerificationException;
use App\Services\Accounts\ClientVerificationService;
use App\Services\Accounts\ClientVisibility;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryAssignment;
use App\Services\Inquiries\InquiryAssignmentException;
use App\Services\Inquiries\InquiryClosure;
use App\Services\Inquiries\InquiryClosureException;
use App\Services\Inquiries\InquiryWorkloadSummary;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I5 — closing an INDEPENDENT Inquiry without a Booking (in_progress → closed) with a required
 * reason and durable audit, plus authorization-scoped list counters. Synthetic data, SQLite :memory:.
 */
class InquiryClosureTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const REASON = 'Клиент решил не ехать в этом сезоне';

    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        $this->admin = $this->userWith('admin', ['name' => 'Админ Синтетический']);
        $this->a = $this->userWith('manager', ['name' => 'Анна Синтетическая']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Синтетический']);
        $this->tourist = $this->userWith('tourist', ['name' => 'Турист Синтетический']);
    }

    private function mine(?User $manager = null): Inquiry
    {
        return Inquiry::factory()->managerCreated($manager ?? $this->a)->create()->refresh();
    }

    private function show(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private function closeUrl(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.close', $inquiry);
    }

    private function submit(User $actor, Inquiry $inquiry, array $override = [])
    {
        return $this->actingAs($actor)->post($this->closeUrl($inquiry), array_merge([
            'expected_status' => $inquiry->status,
            'expected_owner_id' => $inquiry->assigned_to,
            'reason' => self::REASON,
        ], $override));
    }

    private function events(?Inquiry $inquiry = null)
    {
        return AuditEvent::query()
            ->where('event_type', AuditEventType::InquiryClosed->value)
            ->when($inquiry, fn ($q) => $q->where('subject_type', 'inquiry')->where('subject_id', $inquiry->id))
            ->orderBy('id');
    }

    // ---- Gate A: closure -----------------------------------------------------------------------

    public function test_responsible_manager_closes_and_provenance_is_preserved(): void
    {
        $inquiry = $this->mine();
        (new InquiryContact())->forceFill(['inquiry_id' => $inquiry->id, 'contact_name' => 'Синтетический Клиент', 'revision' => 1])->save();
        $before = $inquiry->only(['origin', 'contact_channel', 'created_by', 'assigned_to', 'incoming_inquiry_id', 'booking_id', 'client_user_id', 'draft_revision']);
        $bookings = Booking::query()->count();
        $incoming = IncomingInquiry::query()->count();

        $this->submit($this->a, $inquiry)->assertRedirect($this->show($inquiry))->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame(Inquiry::STATUS_CLOSED, $inquiry->status);
        $this->assertNotNull($inquiry->closed_at);
        $this->assertSame($this->a->id, $inquiry->closed_by);
        $this->assertSame(self::REASON, $inquiry->close_reason);
        $this->assertSame($before, $inquiry->only(array_keys($before)));
        $this->assertSame($bookings, Booking::query()->count());
        $this->assertSame($incoming, IncomingInquiry::query()->count());
        $this->assertNotNull(InquiryContact::query()->find($inquiry->id), 'contact record must be kept');
        $this->assertTrue(User::query()->whereKey($this->tourist->id)->exists());

        $event = $this->events($inquiry)->sole();
        $this->assertSame($this->a->id, $event->actor_id);
        $this->assertNotNull($event->occurred_at);
        $this->assertSame([
            'previous_status' => 'in_progress',
            'new_status' => 'closed',
            'assignee_id' => $this->a->id,
            'reason' => self::REASON,
        ], $event->metadata);
    }

    public function test_admin_closes_any_independent_inquiry(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->admin, $inquiry)->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame(Inquiry::STATUS_CLOSED, $inquiry->status);
        $this->assertSame($this->admin->id, $inquiry->closed_by);
        $this->assertSame($this->a->id, $inquiry->assigned_to, 'responsibility must not change');
        $this->assertSame($this->admin->id, $this->events($inquiry)->sole()->actor_id);
    }

    public function test_foreign_manager_tourist_and_guest_are_refused_without_a_trace(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->b, $inquiry)->assertNotFound();
        $this->submit($this->tourist, $inquiry)->assertForbidden();
        auth()->logout();
        $this->post($this->closeUrl($inquiry), ['reason' => self::REASON])->assertRedirect();

        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->refresh()->status);
        $this->assertSame(0, $this->events()->count());
    }

    public function test_former_assignee_gets_404_and_cannot_close_with_a_stale_form(): void
    {
        $inquiry = $this->mine();
        $staleForm = ['expected_status' => $inquiry->status, 'expected_owner_id' => $this->a->id];

        app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, 'Передача коллеге на время отпуска');

        $this->actingAs($this->a)->post($this->closeUrl($inquiry), $staleForm + ['reason' => self::REASON])->assertNotFound();
        $this->actingAs($this->a)->get($this->show($inquiry))->assertNotFound();
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->refresh()->status);
        $this->assertSame(0, $this->events()->count());
    }

    public function test_linked_external_inquiry_is_refused_by_route_and_by_service(): void
    {
        [$source, $linked] = $this->linkedPair($this->a, $this->a);

        $this->submit($this->admin, $linked)->assertNotFound();
        $this->submit($this->a, $linked)->assertNotFound();

        try {
            app(InquiryClosure::class)->close($linked, $this->admin, $linked->status, $linked->assigned_to, self::REASON);
            $this->fail('A linked inquiry must not be closed through the independent workflow.');
        } catch (InquiryClosureException $e) {
            $this->assertStringContainsString('Входящие обращения', $e->getMessage());
        }

        $this->assertSame($linked->status, $linked->refresh()->status);
        $this->assertSame(0, $this->events()->count());
        $this->assertSame($source->workflow_state, $source->refresh()->workflow_state);
    }

    public function test_reason_is_required_bounded_and_screened(): void
    {
        $inquiry = $this->mine();

        foreach ([
            'missing' => null,
            'blank' => '   ',
            'short' => 'нет',
            'long' => str_repeat('а', InquiryClosure::REASON_MAX_LENGTH + 1),
            'email' => 'Клиент написал на client.person@example.test и отказался',
            'phone' => 'Клиент позвонил с номера +7 900 123-45-67 и отказался',
        ] as $case => $reason) {
            $data = ['expected_status' => 'in_progress', 'expected_owner_id' => $this->a->id];
            if ($reason !== null) {
                $data['reason'] = $reason;
            }
            $this->actingAs($this->a)->post($this->closeUrl($inquiry), $data)->assertSessionHasErrors('reason');
            $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->refresh()->status, $case);
        }

        $this->assertSame(0, $this->events()->count());
    }

    public function test_invalid_transitions_are_rejected(): void
    {
        $inquiry = $this->mine();
        \DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => 'new', 'assigned_to' => null]);
        $inquiry->refresh();

        $this->expectException(InquiryClosureException::class);
        try {
            app(InquiryClosure::class)->close($inquiry, $this->admin, 'new', null, self::REASON);
        } finally {
            $this->assertSame('new', $inquiry->refresh()->status);
            $this->assertSame(0, $this->events()->count());
        }
    }

    public function test_duplicate_submission_writes_one_event(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->a, $inquiry)->assertSessionHas('success');
        $first = $inquiry->refresh()->closed_at;

        $this->actingAs($this->a)->post($this->closeUrl($inquiry), [
            'expected_status' => 'in_progress', 'expected_owner_id' => $this->a->id, 'reason' => 'Другая причина закрытия',
        ])->assertRedirect($this->show($inquiry))->assertSessionHas('error');

        $inquiry->refresh();
        $this->assertSame(1, $this->events($inquiry)->count());
        $this->assertSame(self::REASON, $inquiry->close_reason, 'the first reason must not be overwritten');
        $this->assertEquals($first, $inquiry->closed_at);
    }

    public function test_stale_ownership_and_stale_status_do_not_close(): void
    {
        $inquiry = $this->mine();

        $this->actingAs($this->admin)->post($this->closeUrl($inquiry), [
            'expected_status' => 'in_progress', 'expected_owner_id' => $this->b->id, 'reason' => self::REASON,
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->post($this->closeUrl($inquiry), [
            'expected_status' => 'new', 'expected_owner_id' => $this->a->id, 'reason' => self::REASON,
        ])->assertSessionHas('error');
        $this->actingAs($this->admin)->post($this->closeUrl($inquiry), ['expected_status' => 'in_progress', 'reason' => self::REASON])
            ->assertSessionHasErrors('expected_owner_id');

        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->refresh()->status);
        $this->assertSame(0, $this->events()->count());
    }

    public function test_request_cannot_spoof_status_or_closer(): void
    {
        $inquiry = $this->mine();

        $this->actingAs($this->a)->post($this->closeUrl($inquiry), [
            'expected_status' => 'in_progress', 'expected_owner_id' => $this->a->id, 'reason' => self::REASON,
            'status' => 'converted', 'closed_by' => $this->b->id, 'booking_id' => 1, 'assigned_to' => $this->b->id,
        ]);

        $inquiry->refresh();
        $this->assertSame(Inquiry::STATUS_CLOSED, $inquiry->status);
        $this->assertSame($this->a->id, $inquiry->closed_by);
        $this->assertNull($inquiry->booking_id);
        $this->assertSame($this->a->id, $inquiry->assigned_to);
    }

    public function test_audit_failure_rolls_the_closure_back(): void
    {
        $inquiry = $this->mine();

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });

        try {
            app(InquiryClosure::class)->close($inquiry, $this->a, 'in_progress', $this->a->id, self::REASON);
            $this->fail('The closure persisted although the audit event failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $fresh = Inquiry::query()->find($inquiry->id);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->closed_at);
        $this->assertNull($fresh->closed_by);
        $this->assertNull($fresh->close_reason);
    }

    public function test_service_rechecks_authority_for_a_deactivated_or_former_actor(): void
    {
        $inquiry = $this->mine();

        $this->expectException(AuthorizationException::class);
        try {
            app(InquiryClosure::class)->close($inquiry, $this->b, 'in_progress', $this->a->id, self::REASON);
        } finally {
            $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->refresh()->status);
        }
    }

    // ---- Gate B: pending verification and closed-record guards ----------------------------------

    private function requestVerification(Inquiry $inquiry): ClientVerificationRequest
    {
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), [
            'client_name' => 'Клиент Запроса', 'client_email' => 'unknown.client@example.test', 'client_phone' => '+7 900 111-22-33',
        ])->assertSessionHasNoErrors();

        return ClientVerificationRequest::query()->where('agency_inquiry_id', $inquiry->id)->firstOrFail();
    }

    public function test_pending_verification_blocks_closure_until_rejected(): void
    {
        $inquiry = $this->mine();
        $request = $this->requestVerification($inquiry);

        $this->submit($this->a, $inquiry)->assertSessionHasErrors('verification');
        $this->submit($this->admin, $inquiry)->assertSessionHasErrors('verification');
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->refresh()->status);
        $this->assertSame(0, $this->events()->count());

        app(ClientVerificationService::class)->reject($request, $this->admin, 'Данные клиента не подтвердились');
        $this->submit($this->a, $inquiry)->assertSessionHas('success');
        $this->assertSame(Inquiry::STATUS_CLOSED, $inquiry->refresh()->status);
    }

    public function test_admin_cannot_resolve_a_request_against_an_already_closed_inquiry(): void
    {
        // Closed first (request submitted, rejected, then a later request is impossible after closure).
        $inquiry = $this->mine();
        $request = $this->requestVerification($inquiry);
        app(ClientVerificationService::class)->reject($request, $this->admin, 'Данные клиента не подтвердились');
        $this->submit($this->a, $inquiry)->assertSessionHas('success');

        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), [
            'client_name' => 'Клиент Запроса', 'client_email' => 'second.client@example.test', 'client_phone' => '+7 900 111-22-34',
        ]);
        $this->assertSame(1, ClientVerificationRequest::query()->where('agency_inquiry_id', $inquiry->id)->count(), 'no new request against a closed inquiry');

        // A legacy pending row against a closed inquiry (e.g. created before closure shipped) is not actionable.
        $stale = ClientVerificationRequest::query()->where('agency_inquiry_id', $inquiry->id)->first();
        \DB::table('client_verification_requests')->where('id', $stale->id)->update(['status' => 'pending']);

        $this->expectException(ClientVerificationException::class);
        app(ClientVerificationService::class)->approveNew($stale->refresh(), $this->admin);
    }

    public function test_closed_inquiry_refuses_draft_contact_client_and_reassignment_changes(): void
    {
        $client = $this->userWith('tourist', ['name' => 'Привязанный Турист']);
        $inquiry = $this->mine();
        \DB::table('inquiries')->where('id', $inquiry->id)->update(['client_user_id' => $client->id]);
        $inquiry->refresh();
        $this->submit($this->a, $inquiry)->assertSessionHas('success');
        $inquiry->refresh();

        $snapshot = $inquiry->only(['destination_country', 'draft_revision', 'client_user_id', 'assigned_to', 'status']);
        $events = AuditEvent::query()->count();

        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => (int) $inquiry->draft_revision, 'destination_country' => 'Турция']);
        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.contact.update', $inquiry), ['revision' => 0, 'contact_name' => 'Новый Контакт', 'contact_phone' => '', 'contact_messenger' => '']);
        $this->actingAs($this->a)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 0]);
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.client.unlink', $inquiry), ['expected_client_id' => $client->id, 'reason' => 'Ошибочная привязка клиента']);
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), ['assignee_id' => $this->b->id, 'expected_owner_id' => $this->a->id, 'reason' => 'Передача закрытого обращения']);
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.client.verification', $inquiry), ['client_name' => 'Клиент', 'client_email' => 'x.client@example.test', 'client_phone' => '+7 900 000-00-01']);

        $inquiry->refresh();
        $this->assertSame($snapshot, $inquiry->only(array_keys($snapshot)));
        $this->assertNull(InquiryContact::query()->find($inquiry->id));
        $this->assertSame(0, ClientVerificationRequest::query()->count());
        $this->assertSame($events, AuditEvent::query()->count(), 'refused edits must leave no audit events');

        $this->expectException(InquiryAssignmentException::class);
        app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, 'Передача закрытого обращения');
    }

    public function test_closed_card_is_read_only_with_evidence_and_hidden_from_others(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry);

        $page = $this->actingAs($this->a)->get($this->show($inquiry))->assertOk();
        $page->assertSee('Обращение закрыто без заявки')->assertSee(self::REASON)->assertSee('Анна Синтетическая');
        $page->assertDontSee('Закрыть обращение без заявки</button>', false)->assertDontSee('Сохранить черновик');
        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->assertSee(self::REASON);

        $this->actingAs($this->b)->get($this->show($inquiry))->assertNotFound();
        $this->actingAs($this->tourist)->get($this->show($inquiry))->assertForbidden();
    }

    public function test_open_card_offers_closure_and_old_records_get_no_invented_evidence(): void
    {
        $open = $this->mine();
        $this->actingAs($this->a)->get($this->show($open))->assertOk()->assertSee('Закрыть обращение без заявки');

        $old = Inquiry::factory()->managerCreated($this->a)->create();
        \DB::table('inquiries')->where('id', $old->id)->update(['status' => 'closed', 'closed_at' => now(), 'close_reason' => null]);
        $this->actingAs($this->a)->get($this->show($old))->assertOk()->assertSee('Причина закрытия не записана');
    }

    public function test_client_visibility_after_closure_is_unchanged_and_follows_assignment(): void
    {
        $client = $this->userWith('tourist', ['name' => 'Видимый Клиент']);
        $inquiry = $this->mine();
        \DB::table('inquiries')->where('id', $inquiry->id)->update(['client_user_id' => $client->id]);

        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($client->id)->exists());
        $this->submit($this->a, $inquiry->refresh());

        // Closing neither grants nor revokes: the current assignee keeps the existing relationship, others get none.
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($client->id)->exists());
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->b)->whereKey($client->id)->exists());
    }

    // ---- Gate C: counters -----------------------------------------------------------------------

    public function test_counters_are_scoped_aggregated_and_exclude_linked_inquiries(): void
    {
        $mineOpen = $this->mine();
        $mineClosed = $this->mine();
        $this->submit($this->a, $mineClosed);
        $this->mine($this->b);
        $bClosed = $this->mine($this->b);
        $this->submit($this->b, $bClosed);
        $this->mine($this->b);
        $this->linkedPair($this->a, $this->a);

        $this->assertSame(['total' => 2, 'in_progress' => 1, 'closed' => 1], InquiryWorkloadSummary::forIndependent($this->a));
        $this->assertSame(['total' => 3, 'in_progress' => 2, 'closed' => 1], InquiryWorkloadSummary::forIndependent($this->b));
        $this->assertSame(['total' => 5, 'in_progress' => 3, 'closed' => 2], InquiryWorkloadSummary::forIndependent($this->admin));
        $this->assertSame(['total' => 0, 'in_progress' => 0, 'closed' => 0], InquiryWorkloadSummary::forIndependent($this->tourist));

        // A former assignee loses the count together with the record.
        app(InquiryAssignment::class)->assign($mineOpen, $this->admin, $this->b->id, $this->a->id, 'Передача коллеге на время отпуска');
        $this->assertSame(['total' => 1, 'in_progress' => 0, 'closed' => 1], InquiryWorkloadSummary::forIndependent($this->a));
    }

    public function test_list_shows_counters_independent_of_filters_and_spoofing(): void
    {
        $this->mine();
        $closed = $this->mine();
        $this->submit($this->a, $closed);
        $this->mine($this->b);
        $this->mine($this->b);
        $this->mine($this->b);

        $page = $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries', ['status' => 'closed', 'assignee' => $this->b->id]))->assertOk();
        $html = $page->getContent();
        $this->assertMatchesRegularExpression('/data-summary="total">2</', $html);
        $this->assertMatchesRegularExpression('/data-summary="in_progress">1</', $html);
        $this->assertMatchesRegularExpression('/data-summary="closed">1</', $html);
        $this->assertSame(1, $page->viewData('inquiries')->total(), 'rows follow the status filter; the foreign assignee filter is ignored for managers');

        $admin = $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries', ['status' => 'closed']))->assertOk()->getContent();
        $this->assertMatchesRegularExpression('/data-summary="total">5</', $admin);
        $this->assertMatchesRegularExpression('/data-summary="closed">1</', $admin);
    }

    public function test_summary_data_contains_only_counts_and_pagination_keeps_working(): void
    {
        foreach (range(1, 23) as $i) {
            $this->mine();
        }

        $summary = InquiryWorkloadSummary::forIndependent($this->a);
        $this->assertSame(['total', 'in_progress', 'closed'], array_keys($summary));
        $this->assertContainsOnly('int', $summary);

        $second = $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries', ['page' => 2]))->assertOk();
        $this->assertSame(3, $second->viewData('inquiries')->count());
        $this->assertMatchesRegularExpression('/data-summary="total">23</', $second->getContent());
    }
}
