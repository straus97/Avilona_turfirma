<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryDraftStore;
use App\Services\Inquiries\ManualInquiryCreator;
use App\Support\Audit\AuditEventType;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * AOA-04-I1 — ручное создание обращения менеджером и безопасные правки черновика через
 * реальные маршруты. Синтетические данные, SQLite :memory:.
 */
class ManualInquiryCreationTest extends TestCase
{
    use RefreshDatabase;

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
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
    }

    private function userWith(string $role): User
    {
        $user = User::factory()->create();
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['description' => Role::availableRoles()[$role] ?? $role]);
        $user->roles()->attach($roleModel->id);

        return $user;
    }

    private function create(User $actor, string $channel = 'phone', ?string $token = null)
    {
        return $this->actingAs($actor)->post(route('cabinet.manager.agency-inquiries.store'), [
            'contact_channel' => $channel,
            'creation_token' => $token ?? (string) Str::uuid(),
        ]);
    }

    private function draftUrl(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.draft.update', $inquiry);
    }

    private function showUrl(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private function auditCount(AuditEventType $type): int
    {
        return AuditEvent::query()->where('event_type', $type->value)->count();
    }

    // ───────── Создание ─────────

    #[DataProvider('channels')]
    public function test_manager_creates_own_inquiry_with_trusted_ownership(string $channel): void
    {
        $response = $this->create($this->a, $channel);

        $inquiry = Inquiry::query()->sole();
        $response->assertRedirect($this->showUrl($inquiry));
        $response->assertSessionHas('success');

        $this->assertSame(Inquiry::ORIGIN_MANAGER_CREATED, $inquiry->origin);
        $this->assertSame($channel, $inquiry->contact_channel);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->status);
        $this->assertSame($this->a->id, $inquiry->created_by);
        $this->assertSame($this->a->id, $inquiry->assigned_to);
        $this->assertNotNull($inquiry->assigned_at);
        $this->assertNull($inquiry->incoming_inquiry_id);
        $this->assertNull($inquiry->booking_id);
        $this->assertNull($inquiry->client_user_id);
        $this->assertSame(0, (int) $inquiry->draft_revision);
    }

    /** @return array<string, array{string}> */
    public static function channels(): array
    {
        return ['telephone' => ['phone'], 'messenger' => ['messenger']];
    }

    public function test_creation_has_no_side_effects_on_legacy_records_mail_or_bookings(): void
    {
        $this->create($this->a);

        $this->assertSame(0, IncomingInquiry::query()->count());
        $this->assertSame(0, Booking::query()->count());
        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_creation_is_audited_with_safe_metadata_only(): void
    {
        $this->create($this->a, 'messenger');

        $inquiry = Inquiry::query()->sole();
        $event = AuditEvent::query()->where('event_type', AuditEventType::InquiryCreated->value)->sole();

        $this->assertSame($this->a->id, (int) $event->actor_id);
        $this->assertSame('inquiry', $event->subject_type);
        $this->assertSame($inquiry->id, (int) $event->subject_id);
        $this->assertSame(['origin' => 'manager_created', 'contact_channel' => 'messenger'], $event->metadata);
        $this->assertStringNotContainsString($this->a->email, json_encode($event->metadata));
    }

    public function test_origin_and_contact_channel_are_separate_dimensions(): void
    {
        $this->create($this->a, 'messenger');
        $manual = Inquiry::query()->sole();

        $this->assertSame(Inquiry::ORIGIN_MANAGER_CREATED, $manual->origin);
        $this->assertSame('Личные сообщения в мессенджере', $manual->contactChannelLabel());

        $source = new IncomingInquiry();
        $source->forceFill(['provider' => 'tourvisor', 'external_type' => 'online', 'external_id' => '9001', 'workflow_state' => 'new']);
        $source->save();
        $external = Inquiry::factory()->externalIntake($source)->create();
        $this->assertNull($external->contact_channel);

        $this->expectException(InquiryInvariantViolation::class);
        $external->forceFill(['contact_channel' => 'phone'])->save();
    }

    public function test_model_rejects_unknown_channel_and_manual_inquiry_without_channel(): void
    {
        try {
            Inquiry::factory()->managerCreated($this->a)->state(['contact_channel' => 'telegram'])->create();
            $this->fail('Unknown channel accepted.');
        } catch (InquiryInvariantViolation) {
            $this->assertSame(0, Inquiry::query()->count());
        }

        $this->expectException(InquiryInvariantViolation::class);
        Inquiry::factory()->managerCreated($this->a)->state(['contact_channel' => null])->create();
    }

    public function test_spoofed_owner_and_protected_fields_are_rejected_without_creation(): void
    {
        $spoofs = [
            ['assigned_to' => $this->b->id],
            ['created_by' => $this->b->id],
            ['origin' => Inquiry::ORIGIN_ADMIN_CREATED],
            ['status' => Inquiry::STATUS_CONVERTED],
            ['booking_id' => 1],
            ['client_user_id' => $this->tourist->id],
            ['incoming_inquiry_id' => 1],
        ];

        foreach ($spoofs as $extra) {
            $response = $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.store'), [
                'contact_channel' => 'phone',
                'creation_token' => (string) Str::uuid(),
            ] + $extra);

            $response->assertSessionHasErrors('request');
        }

        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryCreated));
    }

    #[DataProvider('invalidChannels')]
    public function test_invalid_channel_is_rejected_and_form_state_is_recoverable(?string $channel): void
    {
        $token = (string) Str::uuid();
        $payload = ['creation_token' => $token];
        if ($channel !== null) {
            $payload['contact_channel'] = $channel;
        }

        $response = $this->actingAs($this->a)->from(route('cabinet.manager.agency-inquiries.create'))
            ->post(route('cabinet.manager.agency-inquiries.store'), $payload);

        $response->assertRedirect(route('cabinet.manager.agency-inquiries.create'));
        $response->assertSessionHasErrors('contact_channel');
        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryCreated));

        // Форма после ошибки сохраняет тот же токен показа.
        $this->get(route('cabinet.manager.agency-inquiries.create'))->assertOk()->assertSee($token);
    }

    /** @return array<string, array{?string}> */
    public static function invalidChannels(): array
    {
        return ['unknown provider' => ['telegram'], 'empty' => [''], 'missing' => [null], 'case variant' => ['Phone'], 'array-ish' => ['phone,messenger']];
    }

    public function test_malformed_creation_token_is_rejected(): void
    {
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.store'), [
            'contact_channel' => 'phone', 'creation_token' => 'not-a-uuid',
        ])->assertSessionHasErrors('creation_token');

        $this->assertSame(0, Inquiry::query()->count());
    }

    // ───────── Роли ─────────

    public function test_admin_tourist_and_guest_cannot_create(): void
    {
        $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries.create'))->assertForbidden();
        $this->create($this->admin)->assertForbidden();
        $this->actingAs($this->tourist)->get(route('cabinet.manager.agency-inquiries.create'))->assertForbidden();
        $this->create($this->tourist)->assertForbidden();

        auth()->logout();
        $this->post(route('cabinet.manager.agency-inquiries.store'), ['contact_channel' => 'phone', 'creation_token' => (string) Str::uuid()])
            ->assertRedirect(route('login'));

        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryCreated));
    }

    public function test_user_with_admin_and_manager_roles_does_not_create_under_manager_rules(): void
    {
        $both = $this->userWith('manager');
        $both->roles()->attach(Role::query()->where('name', 'admin')->value('id'));

        $this->create($both)->assertForbidden();
        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_inactive_manager_cannot_create_through_the_service(): void
    {
        $this->a->forceFill(['is_active' => false])->save();

        $this->expectException(AuthorizationException::class);

        try {
            app(ManualInquiryCreator::class)->createForManager($this->a, 'phone', (string) Str::uuid());
        } finally {
            $this->assertSame(0, Inquiry::query()->count());
            $this->assertSame(0, $this->auditCount(AuditEventType::InquiryCreated));
        }
    }

    public function test_service_refuses_non_manager_actor(): void
    {
        foreach ([$this->tourist, $this->admin] as $actor) {
            try {
                app(ManualInquiryCreator::class)->createForManager($actor, 'phone', (string) Str::uuid());
                $this->fail('A non-manager created an inquiry.');
            } catch (AuthorizationException) {
                // expected
            }
        }

        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_service_rejects_unknown_channel(): void
    {
        $this->expectException(ValidationException::class);
        app(ManualInquiryCreator::class)->createForManager($this->a, 'vk', (string) Str::uuid());
    }

    // ───────── Транзакционность и повтор ─────────

    public function test_audit_failure_rolls_back_the_inquiry(): void
    {
        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });

        try {
            app(ManualInquiryCreator::class)->createForManager($this->a, 'phone', (string) Str::uuid());
            $this->fail('Inquiry persisted although the audit event failed.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_resubmitting_the_same_form_returns_the_existing_inquiry_without_duplicates(): void
    {
        $token = (string) Str::uuid();

        $first = $this->create($this->a, 'phone', $token);
        $second = $this->create($this->a, 'phone', $token);
        $third = $this->create($this->a, 'messenger', $token);

        $inquiry = Inquiry::query()->sole();
        $first->assertRedirect($this->showUrl($inquiry));
        $second->assertRedirect($this->showUrl($inquiry));
        $third->assertRedirect($this->showUrl($inquiry));
        $second->assertSessionHas('success', fn (string $m) => str_contains($m, 'уже было создано'));

        $this->assertSame('phone', $inquiry->contact_channel);
        $this->assertSame(1, $this->auditCount(AuditEventType::InquiryCreated));
    }

    public function test_service_reports_whether_the_inquiry_was_newly_created(): void
    {
        $creator = app(ManualInquiryCreator::class);
        $token = (string) Str::uuid();

        $new = $creator->createForManager($this->a, 'phone', $token);
        $again = $creator->createForManager($this->a, 'phone', strtoupper($token));

        $this->assertTrue($new->wasRecentlyCreated);
        $this->assertFalse($again->wasRecentlyCreated);
        $this->assertSame($new->id, $again->id);
    }

    public function test_distinct_forms_create_distinct_inquiries_and_tokens_are_per_creator(): void
    {
        $token = (string) Str::uuid();

        $this->create($this->a, 'phone', $token);
        $this->create($this->a, 'phone');
        $this->create($this->b, 'messenger', $token);

        $this->assertSame(3, Inquiry::query()->count());
        $this->assertSame(2, Inquiry::query()->where('created_by', $this->a->id)->count());
        $this->assertSame(3, $this->auditCount(AuditEventType::InquiryCreated));
    }

    public function test_database_enforces_token_uniqueness_per_creator(): void
    {
        $token = (string) Str::uuid();
        Inquiry::factory()->managerCreated($this->a)->state(['creation_token' => $token])->create();

        $this->expectException(UniqueConstraintViolationException::class);
        Inquiry::factory()->managerCreated($this->a)->state(['creation_token' => $token])->create();
    }

    // ───────── Доступ ─────────

    public function test_managers_are_isolated_and_admin_has_oversight(): void
    {
        $this->create($this->a);
        $this->create($this->b, 'messenger');
        $inquiryA = Inquiry::query()->where('created_by', $this->a->id)->sole();
        $inquiryB = Inquiry::query()->where('created_by', $this->b->id)->sole();

        $this->actingAs($this->a)->get($this->showUrl($inquiryA))->assertOk();
        $this->actingAs($this->a)->get($this->showUrl($inquiryB))->assertNotFound();
        $this->actingAs($this->b)->get($this->showUrl($inquiryA))->assertNotFound();
        $this->actingAs($this->admin)->get($this->showUrl($inquiryA))->assertOk();
        $this->actingAs($this->admin)->get($this->showUrl($inquiryB))->assertOk();
        $this->actingAs($this->tourist)->get($this->showUrl($inquiryA))->assertForbidden();
    }

    public function test_index_is_scoped_to_the_viewer(): void
    {
        $this->create($this->a);
        $this->create($this->b);
        $inquiryB = Inquiry::query()->where('created_by', $this->b->id)->sole();
        Inquiry::factory()->managerCreated($this->a)->create(['destination_country' => 'Турция']);

        $htmlA = $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->getContent();
        $this->assertStringContainsString($this->showUrl(Inquiry::query()->where('created_by', $this->a->id)->first()), $htmlA);
        $this->assertStringNotContainsString($this->showUrl($inquiryB), $htmlA);
        $this->assertStringContainsString('Новое обращение', $htmlA);

        $htmlAdmin = $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->getContent();
        $this->assertStringContainsString($this->showUrl($inquiryB), $htmlAdmin);
        $this->assertStringNotContainsString(route('cabinet.manager.agency-inquiries.create'), $htmlAdmin);

        $this->actingAs($this->tourist)->get(route('cabinet.manager.agency-inquiries'))->assertForbidden();
    }

    public function test_empty_states_are_not_misleading(): void
    {
        $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries'))
            ->assertOk()->assertSee('Обращений клиентов пока нет')->assertSee('Новое обращение');

        $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries'))
            ->assertOk()->assertSee('Обращений клиентов пока нет')->assertDontSee('Новое обращение');
    }

    public function test_former_assignee_loses_access_after_reassignment(): void
    {
        $this->create($this->a);
        $inquiry = Inquiry::query()->sole();
        $inquiry->forceFill(['assigned_to' => $this->b->id])->save();

        $this->actingAs($this->a)->get($this->showUrl($inquiry))->assertNotFound();
        $this->actingAs($this->a)->put($this->draftUrl($inquiry), ['revision' => 0, 'adults' => 2])->assertNotFound();
        $this->actingAs($this->b)->get($this->showUrl($inquiry))->assertOk();
        $this->assertNull($inquiry->fresh()->adults);
    }

    public function test_unassigned_and_linked_inquiries_are_not_reachable_through_the_manual_screens(): void
    {
        $source = new IncomingInquiry();
        $source->forceFill(['provider' => 'tourvisor', 'external_type' => 'online', 'external_id' => '9002', 'workflow_state' => 'new']);
        $source->save();
        $unassigned = Inquiry::factory()->externalIntake($source)->create();

        $this->actingAs($this->a)->get($this->showUrl($unassigned))->assertNotFound();
        $this->actingAs($this->admin)->get($this->showUrl($unassigned))->assertNotFound();
        $this->actingAs($this->a)->put($this->draftUrl($unassigned), ['revision' => 0])->assertNotFound();
        $this->assertStringNotContainsString($this->showUrl($unassigned), $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries'))->getContent());
    }

    // ───────── Черновик ─────────

    private function ownInquiry(?User $owner = null): Inquiry
    {
        $this->create($owner ?? $this->a);

        return Inquiry::query()->latest('id')->firstOrFail();
    }

    /** @return array<string, mixed> */
    private function validDraft(array $override = []): array
    {
        return array_merge([
            'revision' => 0,
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'departure_city' => 'Санкт-Петербург',
            'date_from' => '2027-06-10',
            'date_to' => '2027-06-20',
            'nights_min' => 7,
            'nights_max' => 10,
            'adults' => 2,
            'children' => 2,
            'children_ages' => '5, 8',
            'preferences' => 'Первая линия, всё включено',
            'internal_notes' => 'Перезвонить во вторник',
        ], $override);
    }

    public function test_draft_is_saved_audited_and_reloaded(): void
    {
        $inquiry = $this->ownInquiry();

        $this->actingAs($this->a)->put($this->draftUrl($inquiry), $this->validDraft())
            ->assertRedirect($this->showUrl($inquiry))
            ->assertSessionHas('success', 'Черновик сохранён.');

        $fresh = $inquiry->fresh();
        $this->assertSame('Турция', $fresh->destination_country);
        $this->assertSame([5, 8], $fresh->children_ages);
        $this->assertSame(1, (int) $fresh->draft_revision);
        $this->assertSame($this->a->id, (int) $fresh->draft_updated_by);
        // Владелец и статус не затронуты.
        $this->assertSame($this->a->id, $fresh->assigned_to);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $fresh->status);

        $event = AuditEvent::query()->where('event_type', AuditEventType::InquiryDraftUpdated->value)->sole();
        $this->assertSame(1, (int) $event->metadata['new_revision']);
        $this->assertStringNotContainsString('Перезвонить', json_encode($event->metadata, JSON_UNESCAPED_UNICODE));
        $this->assertStringNotContainsString('Анталья', json_encode($event->metadata, JSON_UNESCAPED_UNICODE));

        $this->actingAs($this->a)->get($this->showUrl($inquiry))->assertOk()
            ->assertSee('Анталья')->assertSee('2027-06-10')->assertSee('5, 8')->assertSee('Перезвонить во вторник');
    }

    public function test_partial_draft_without_client_or_dates_is_accepted(): void
    {
        $inquiry = $this->ownInquiry();

        $this->actingAs($this->a)->put($this->draftUrl($inquiry), ['revision' => 0, 'destination_country' => 'Египет'])
            ->assertSessionHasNoErrors();

        $this->assertSame('Египет', $inquiry->fresh()->destination_country);
        $this->assertNull($inquiry->fresh()->client_user_id);
    }

    public function test_validation_errors_keep_input_and_do_not_change_the_draft(): void
    {
        $inquiry = $this->ownInquiry();

        $response = $this->actingAs($this->a)->from($this->showUrl($inquiry))->put($this->draftUrl($inquiry), $this->validDraft([
            'nights_min' => 40, 'preferences' => 'Сохранить это поле',
        ]));

        $response->assertRedirect($this->showUrl($inquiry));
        $response->assertSessionHasErrors('nights_min');
        $this->assertSame(0, (int) $inquiry->fresh()->draft_revision);
        $this->assertNull($inquiry->fresh()->destination_country);
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryDraftUpdated));

        $this->actingAs($this->a)->get($this->showUrl($inquiry))->assertOk()
            ->assertSee('Сохранить это поле')->assertSee('Анталья');
    }

    #[DataProvider('badAges')]
    public function test_children_ages_are_validated_with_clear_errors(string $ages, int $children): void
    {
        $inquiry = $this->ownInquiry();

        $this->actingAs($this->a)->put($this->draftUrl($inquiry), ['revision' => 0, 'children' => $children, 'children_ages' => $ages])
            ->assertSessionHasErrors();

        $this->assertSame(0, (int) $inquiry->fresh()->draft_revision);
    }

    /** @return array<string, array{string, int}> */
    public static function badAges(): array
    {
        return ['text' => ['abc', 1], 'count mismatch' => ['5, 6', 1], 'out of range' => ['18', 1], 'decimal' => ['5.5', 1]];
    }

    public function test_stale_revision_is_rejected_with_a_safe_reload_path(): void
    {
        $inquiry = $this->ownInquiry();
        $url = $this->draftUrl($inquiry);

        $this->actingAs($this->a)->put($url, ['revision' => 0, 'destination_country' => 'Турция']);
        $this->assertSame(1, (int) $inquiry->fresh()->draft_revision);

        // Устаревшая вкладка всё ещё держит версию 0.
        $this->actingAs($this->a)->put($url, ['revision' => 0, 'destination_country' => 'Греция', 'internal_notes' => 'моя правка'])
            ->assertRedirect($this->showUrl($inquiry))
            ->assertSessionHas('error', fn (string $m) => str_contains($m, 'НЕ сохранены'))
            ->assertSessionHas('draft_conflict');

        $fresh = $inquiry->fresh();
        $this->assertSame('Турция', $fresh->destination_country);
        $this->assertSame(1, (int) $fresh->draft_revision);
        $this->assertSame(1, $this->auditCount(AuditEventType::InquiryDraftUpdated));

        // Перезагрузка показывает актуальную версию и несохранённые значения для сверки.
        $this->followingRedirects()->actingAs($this->a)->put($url, ['revision' => 0, 'destination_country' => 'Греция', 'internal_notes' => 'моя правка'])
            ->assertOk()
            ->assertSee('Ваши несохранённые значения')->assertSee('Греция')->assertSee('моя правка')
            ->assertSee('name="revision" value="1"', false);
    }

    public function test_draft_payload_cannot_change_owner_status_client_or_booking(): void
    {
        $inquiry = $this->ownInquiry();

        foreach (['assigned_to' => $this->b->id, 'status' => 'closed', 'client_user_id' => $this->tourist->id, 'booking_id' => 1, 'created_by' => $this->b->id, 'origin' => 'external_intake', 'contact_channel' => 'phone', 'creation_token' => (string) Str::uuid()] as $field => $value) {
            $this->actingAs($this->a)->put($this->draftUrl($inquiry), ['revision' => 0, $field => $value, 'adults' => 3])
                ->assertSessionHasErrors('draft');
        }

        $fresh = $inquiry->fresh();
        $this->assertSame($this->a->id, $fresh->assigned_to);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->client_user_id);
        $this->assertNull($fresh->booking_id);
        $this->assertNull($fresh->adults);
        $this->assertSame(0, (int) $fresh->draft_revision);
    }

    public function test_missing_revision_is_rejected(): void
    {
        $inquiry = $this->ownInquiry();

        $this->actingAs($this->a)->put($this->draftUrl($inquiry), ['adults' => 2])->assertSessionHasErrors('revision');
        $this->assertNull($inquiry->fresh()->adults);
    }

    public function test_admin_edits_through_the_same_store_and_is_the_audited_actor(): void
    {
        $inquiry = $this->ownInquiry();

        $this->actingAs($this->admin)->put($this->draftUrl($inquiry), ['revision' => 0, 'adults' => 3])->assertSessionHasNoErrors();

        $event = AuditEvent::query()->where('event_type', AuditEventType::InquiryDraftUpdated->value)->sole();
        $this->assertSame($this->admin->id, (int) $event->actor_id);
        $this->assertSame($this->a->id, $inquiry->fresh()->assigned_to);
    }

    public function test_tourist_cannot_save_a_draft(): void
    {
        $inquiry = $this->ownInquiry();

        $this->actingAs($this->tourist)->put($this->draftUrl($inquiry), ['revision' => 0, 'adults' => 3])->assertForbidden();
        $this->assertNull($inquiry->fresh()->adults);
    }

    public function test_store_authorizes_against_the_locked_current_row(): void
    {
        $inquiry = $this->ownInquiry();
        $inquiry->forceFill(['assigned_to' => $this->b->id])->save();

        $this->expectException(AuthorizationException::class);
        app(InquiryDraftStore::class)->save($inquiry, $this->a, ['adults' => 2], 0);
    }

    // ───────── Приватность и навигация ─────────

    public function test_pages_and_serialization_do_not_expose_user_models(): void
    {
        $inquiry = $this->ownInquiry();

        $html = $this->actingAs($this->admin)->get($this->showUrl($inquiry))->assertOk()->getContent();
        $this->assertStringNotContainsString($this->a->email, $html);
        $this->assertStringNotContainsString($this->b->email, $html);
        $this->assertStringContainsString($this->a->name, $html);

        $loaded = Inquiry::query()->with(['assignee', 'creator', 'clientUser', 'draftUpdatedBy'])->findOrFail($inquiry->id)->toArray();
        foreach (['assignee', 'creator', 'client_user', 'internal_notes', 'creation_token'] as $key) {
            $this->assertArrayNotHasKey($key, $loaded);
        }
        // Колонка draft_updated_by совпадает по имени с отношением: наружу уходит только скалярный id.
        $this->assertTrue(is_scalar($loaded['draft_updated_by']) || $loaded['draft_updated_by'] === null);
        $this->assertStringNotContainsString($this->a->email, json_encode($loaded));

        $htmlB = $this->actingAs($this->b)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->getContent();
        $this->assertStringNotContainsString($this->a->email, $htmlB);
    }

    public function test_navigation_has_one_distinct_entry_with_active_state(): void
    {
        foreach ([$this->a, $this->admin] as $viewer) {
            $html = $this->actingAs($viewer)->get(route('cabinet.manager.agency-inquiries'))->assertOk()->getContent();

            $this->assertSame(1, substr_count($html, 'href="' . route('cabinet.manager.agency-inquiries') . '" class="menu-item active"'), 'active entry');
            $this->assertSame(1, substr_count($html, '<span>Обращения клиентов</span>'));
            $this->assertSame(1, substr_count($html, '<span>Входящие обращения</span>'));
            $this->assertStringNotContainsString('href="' . route('cabinet.manager.inquiries') . '" class="menu-item active"', $html);
        }

        $legacy = $this->actingAs($this->a)->get(route('cabinet.manager.inquiries'))->assertOk()->getContent();
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.agency-inquiries') . '" class="menu-item active"', $legacy);
    }

    public function test_creation_form_is_labelled_in_russian(): void
    {
        $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries.create'))->assertOk()
            ->assertSee('Телефонный звонок')->assertSee('Личные сообщения в мессенджере')
            ->assertSee('name="creation_token"', false)->assertSee('type="radio"', false);
    }

    public function test_legacy_incoming_inquiry_screens_are_unchanged(): void
    {
        $this->create($this->a);

        $this->actingAs($this->a)->get(route('cabinet.manager.inquiries'))->assertOk()
            ->assertSee('Входящие обращения')->assertSee('Входящих обращений пока нет');
        $this->assertSame(0, IncomingInquiry::query()->count());
        $this->assertSame(1, Inquiry::query()->count());
    }

    public function test_creation_does_not_touch_other_tables(): void
    {
        $before = [
            'users' => User::query()->count(),
            'bookings' => DB::table('bookings')->count(),
            'incoming' => DB::table('incoming_inquiries')->count(),
            'client_requests' => DB::table('client_verification_requests')->count(),
        ];

        $this->create($this->a);

        $this->assertSame($before, [
            'users' => User::query()->count(),
            'bookings' => DB::table('bookings')->count(),
            'incoming' => DB::table('incoming_inquiries')->count(),
            'client_requests' => DB::table('client_verification_requests')->count(),
        ]);
    }
}
