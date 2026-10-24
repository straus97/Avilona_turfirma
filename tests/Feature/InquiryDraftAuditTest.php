<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\Backfill\InquiryBackfillService;
use App\Services\Inquiries\Backfill\VerifiedQaConnection;
use App\Services\Inquiries\InquiryDraftException;
use App\Services\Inquiries\InquiryDraftStore;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I4 — бизнес-аудит правок черновика Inquiry: событие только у принятого изменения,
 * в той же транзакции, с доверенным актором, версиями «до/после» и именами полей (без значений).
 * Синтетические данные, SQLite :memory:.
 */
class InquiryDraftAuditTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const PRIVATE_NOTE = 'ПРИВАТНАЯ-ЗАМЕТКА-9917 звонить на client.private@example.test +7 912 345 67 89';
    private const PRIVATE_PREFERENCE = 'ПРИВАТНОЕ-ПОЖЕЛАНИЕ-4471';

    private InquiryDraftStore $store;
    private User $admin;
    private User $manager;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->store = app(InquiryDraftStore::class);
        $this->admin = $this->userWith('admin');
        $this->manager = $this->userWith('manager');
    }

    /** @return \Illuminate\Support\Collection<int, AuditEvent> */
    private function draftEvents(?Inquiry $inquiry = null)
    {
        return AuditEvent::query()
            ->where('event_type', AuditEventType::InquiryDraftUpdated->value)
            ->when($inquiry, fn ($q) => $q->where('subject_id', $inquiry->id))
            ->orderBy('id')
            ->get();
    }

    private function ownInquiry(): Inquiry
    {
        return Inquiry::factory()->managerCreated($this->manager)->create();
    }

    // ------------------------------------------------------------------ принятое изменение

    public function test_accepted_save_records_one_event_with_trusted_actor_and_revisions(): void
    {
        $inquiry = $this->ownInquiry();

        $this->store->save($inquiry, $this->manager, ['destination_country' => 'Турция', 'adults' => 2], 0);

        $events = $this->draftEvents($inquiry);
        $this->assertCount(1, $events);
        $event = $events->first();
        $this->assertSame('inquiry.draft_updated', $event->event_type);
        $this->assertSame($this->manager->id, (int) $event->actor_id, 'актор — аутентифицированный сотрудник, а не значение из запроса');
        $this->assertSame('inquiry', $event->subject_type);
        $this->assertSame($inquiry->id, (int) $event->subject_id);
        $this->assertSame(0, $event->metadata['previous_revision']);
        $this->assertSame(1, $event->metadata['new_revision']);
        $this->assertSame(2, $event->metadata['changed_field_count']);
        $this->assertSame('destination_country,adults', $event->metadata['changed_fields'], 'порядок — как в Inquiry::DRAFT_FIELDS');
        $this->assertSame('Черновик обращения обновлён', $event->typeLabel());
        // Версия в событии совпадает с версией в строке.
        $this->assertSame(1, $inquiry->fresh()->draft_revision);
    }

    public function test_admin_edit_is_attributed_to_the_admin_not_to_the_responsible_manager(): void
    {
        $inquiry = $this->ownInquiry();

        $this->store->save($inquiry, $this->admin, ['adults' => 3], 0);

        $event = $this->draftEvents($inquiry)->sole();
        $this->assertSame($this->admin->id, (int) $event->actor_id);
        $this->assertSame($this->admin->id, $inquiry->fresh()->draft_updated_by);
    }

    public function test_sequential_saves_form_a_gapless_revision_chain(): void
    {
        $inquiry = $this->ownInquiry();

        $this->store->save($inquiry, $this->manager, ['adults' => 2], 0);
        $this->store->save($inquiry, $this->admin, ['adults' => 3, 'nights_min' => 7], 1);
        $this->store->save($inquiry, $this->manager, ['nights_min' => null], 2);

        $chain = $this->draftEvents($inquiry)->map(fn ($e) => [$e->metadata['previous_revision'], $e->metadata['new_revision']])->all();
        $this->assertSame([[0, 1], [1, 2], [2, 3]], $chain);
        $this->assertSame(3, $inquiry->fresh()->draft_revision);
    }

    public function test_audit_metadata_carries_field_names_only_and_never_draft_values(): void
    {
        $inquiry = $this->ownInquiry();

        // Заметка с «похожим на e-mail/телефон» текстом по-прежнему сохраняется: в аудит она не попадает.
        $this->store->save($inquiry, $this->manager, [
            'internal_notes' => self::PRIVATE_NOTE,
            'preferences' => self::PRIVATE_PREFERENCE,
            'destination_city' => 'Анталья',
            'date_from' => '2027-06-01',
            'children' => 1,
            'children_ages' => [7],
        ], 0);

        $this->assertSame(self::PRIVATE_NOTE, $inquiry->fresh()->internal_notes);

        $event = $this->draftEvents($inquiry)->sole();
        $this->assertEqualsCanonicalizing(
            ['previous_revision', 'new_revision', 'changed_field_count', 'changed_fields'],
            array_keys($event->metadata),
            'набор ключей метаданных фиксирован'
        );
        $this->assertSame(
            'destination_city,date_from,children,children_ages,preferences,internal_notes',
            $event->metadata['changed_fields']
        );

        $stored = json_encode(DB::table('audit_events')->get()->all(), JSON_UNESCAPED_UNICODE);
        foreach ([self::PRIVATE_NOTE, self::PRIVATE_PREFERENCE, 'ПРИВАТНАЯ', 'client.private', '912', 'Анталья', '2027-06-01', $this->manager->email, $this->manager->name] as $secret) {
            $this->assertStringNotContainsString($secret, $stored, "в журнале нашлось: {$secret}");
        }
    }

    public function test_resubmitting_identical_values_at_the_current_revision_keeps_existing_semantics(): void
    {
        $inquiry = $this->ownInquiry();
        $this->store->save($inquiry, $this->manager, ['adults' => 2], 0);

        // Существующая семантика I1: непустой патч поднимает версию, даже если значения не изменились.
        $this->store->save($inquiry, $this->manager, ['adults' => 2], 1);

        $this->assertSame(2, $inquiry->fresh()->draft_revision);
        $second = $this->draftEvents($inquiry)->last();
        $this->assertSame(0, $second->metadata['changed_field_count']);
        $this->assertNull($second->metadata['changed_fields']);
        $this->assertCount(2, $this->draftEvents($inquiry), 'число событий равно версии черновика');
    }

    // ------------------------------------------------------------------ отказы не пишут событий

    public function test_validation_rejection_writes_no_event_and_no_change(): void
    {
        $inquiry = $this->ownInquiry();

        foreach ([['adults' => 0], ['adults' => 2, 'status' => 'closed'], ['nights_min' => 31]] as $bad) {
            try {
                $this->store->save($inquiry, $this->manager, $bad, 0);
                $this->fail('Invalid patch accepted.');
            } catch (ValidationException) {
                // ожидаемо
            }
        }

        $this->assertCount(0, $this->draftEvents());
        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertSame(0, $inquiry->fresh()->draft_revision);
    }

    public function test_stale_revision_rejection_writes_no_event_and_retry_does_not_duplicate(): void
    {
        $inquiry = $this->ownInquiry();
        $this->store->save($inquiry, $this->manager, ['adults' => 2], 0);

        // Повтор той же формы (ревизия 0 уже устарела) — отказ, второго события нет.
        foreach ([1, 2] as $attempt) {
            try {
                $this->store->save($inquiry, $this->manager, ['adults' => 2], 0);
                $this->fail('Stale revision accepted.');
            } catch (InquiryDraftException $e) {
                $this->assertSame('revision', $e->field);
            }
        }

        $this->assertCount(1, $this->draftEvents($inquiry));
        $this->assertSame(1, $inquiry->fresh()->draft_revision);
    }

    public function test_authorization_failures_write_no_event(): void
    {
        $foreign = $this->userWith('manager');
        $tourist = $this->userWith('tourist');
        $noRole = User::factory()->create();
        $mine = $this->ownInquiry();
        $unassigned = Inquiry::factory()->externalIntake($this->inquiryFor(null))->create();

        foreach ([[$foreign, $mine], [$tourist, $mine], [$noRole, $mine], [$this->manager, $unassigned], [$foreign, $unassigned]] as [$actor, $target]) {
            try {
                $this->store->save($target, $actor, ['adults' => 5], 0);
                $this->fail('Draft saved by an unauthorized actor.');
            } catch (AuthorizationException) {
                // ожидаемо
            }
        }

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_closed_inquiry_and_empty_patch_write_no_event(): void
    {
        $inquiry = $this->ownInquiry();

        $this->store->save($inquiry, $this->manager, [], 0);
        $this->assertSame(0, AuditEvent::query()->count(), 'пустой патч — не изменение');
        $this->assertSame(0, $inquiry->fresh()->draft_revision);

        $inquiry->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

        try {
            $this->store->save($inquiry, $this->manager, ['adults' => 2], 0);
            $this->fail('Closed inquiry draft changed.');
        } catch (InquiryDraftException) {
            // ожидаемо
        }

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_former_manager_after_reassignment_is_denied_without_event(): void
    {
        $other = $this->userWith('manager');
        $inquiry = $this->ownInquiry();
        $stale = Inquiry::find($inquiry->id);
        $inquiry->forceFill(['assigned_to' => $other->id])->save();

        try {
            $this->store->save($stale, $this->manager, ['adults' => 2], 0);
            $this->fail('Former manager edited the draft.');
        } catch (AuthorizationException) {
            // ожидаемо
        }

        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertNull($inquiry->fresh()->adults);

        // Новый ответственный — работает, и событие приписано ему.
        $this->store->save($stale, $other, ['adults' => 2], 0);
        $this->assertSame($other->id, (int) $this->draftEvents($inquiry)->sole()->actor_id);
    }

    public function test_linked_inquiry_requires_current_legacy_ownership_for_the_event(): void
    {
        $other = $this->userWith('manager');
        [$source, $inquiry] = $this->linkedPair($this->manager, $this->manager);

        $this->store->save($inquiry, $this->manager, ['adults' => 2], 0);
        $this->assertCount(1, $this->draftEvents($inquiry));

        // E5 передал источник другому: прежний ответственный теряет доступ, события нет.
        $this->legacyReassign($source, $other);

        try {
            $this->store->save($inquiry, $this->manager, ['adults' => 3], 1);
            $this->fail('Draft saved after legacy reassignment.');
        } catch (AuthorizationException) {
            // ожидаемо
        }

        $this->assertCount(1, $this->draftEvents($inquiry));
        $this->assertSame(2, $inquiry->fresh()->adults);
        $this->assertSame(1, $inquiry->fresh()->draft_revision);
    }

    // ------------------------------------------------------------------ транзакция

    public function test_audit_failure_rolls_back_the_draft_change(): void
    {
        $inquiry = $this->ownInquiry();

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });

        try {
            app(InquiryDraftStore::class)->save($inquiry, $this->manager, ['adults' => 4, 'internal_notes' => 'откат'], 0);
            $this->fail('Draft persisted although the audit event failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $fresh = $inquiry->fresh();
        $this->assertSame(0, $fresh->draft_revision);
        $this->assertNull($fresh->adults);
        $this->assertNull($fresh->internal_notes);
        $this->assertNull($fresh->draft_updated_by);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    // ------------------------------------------------------------------ побочные эффекты и история

    public function test_a_save_changes_nothing_but_the_draft_and_causes_no_side_effects(): void
    {
        [$source, $inquiry] = $this->linkedPair($this->manager, $this->manager);
        $before = $this->protectedState($source, $inquiry);

        $this->store->save($inquiry, $this->manager, ['adults' => 2, 'internal_notes' => 'заметка'], 0);

        $this->assertSame($before, $this->protectedState($source, $inquiry), 'ответственный, статус, клиент, заявка и источник не тронуты');
        $this->assertSame(0, Booking::withTrashed()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_backfill_does_not_invent_historical_inquiry_events(): void
    {
        if (DB::getDriverName() !== 'sqlite') {
            $this->markTestSkipped('VerifiedQaConnection refuses the test default connection on MySQL; covered by the disposable-MySQL harness (docs/AOA-03-I4).');
        }

        config(['database.connections.' . config('database.default') . '.qa_disposable' => true]);
        $qa = VerifiedQaConnection::verify(config('database.default'));
        $this->inquiryFor($this->manager, null);
        $this->inquiryFor(null);

        (new InquiryBackfillService($qa))->apply();

        $this->assertSame(2, Inquiry::query()->count());
        $this->assertSame(0, AuditEvent::query()->count(), 'перенос не создаёт и не выдумывает события истории');
    }

    /** @return array<string, mixed> */
    private function protectedState(IncomingInquiry $source, Inquiry $inquiry): array
    {
        $row = (array) DB::table('inquiries')->where('id', $inquiry->id)->first();
        foreach (Inquiry::DRAFT_FIELDS as $field) {
            unset($row[$field]);
        }
        unset($row['draft_revision'], $row['draft_updated_by'], $row['draft_updated_at'], $row['updated_at']);

        return [$row, (array) DB::table('incoming_inquiries')->where('id', $source->id)->first()];
    }
}
