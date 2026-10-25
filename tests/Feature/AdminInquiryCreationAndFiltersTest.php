<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryAssignment;
use App\Services\Inquiries\ManualInquiryCreator;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\TestCase;

/**
 * AOA-04-I3 — создание обращения администратором с выбором первого ответственного и фильтры
 * списка обращений. Синтетические данные, SQLite :memory:.
 */
class AdminInquiryCreationAndFiltersTest extends TestCase
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

    private function store(User $actor, array $payload = [])
    {
        return $this->actingAs($actor)->post(route('cabinet.manager.agency-inquiries.store'), $payload + [
            'contact_channel' => 'phone',
            'assignee_id' => $this->a->id,
            'creation_token' => (string) Str::uuid(),
        ]);
    }

    private function showUrl(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    private function auditCount(AuditEventType $type): int
    {
        return AuditEvent::query()->where('event_type', $type->value)->count();
    }

    private function listHtml(User $viewer, array $query = []): string
    {
        return $this->actingAs($viewer)->get(route('cabinet.manager.agency-inquiries', $query))->assertOk()->getContent();
    }

    // ───────── Создание администратором ─────────

    #[DataProvider('channels')]
    public function test_admin_creates_inquiry_for_a_manager_with_trusted_attributes(string $channel): void
    {
        $response = $this->store($this->admin, ['contact_channel' => $channel, 'assignee_id' => $this->a->id]);

        $inquiry = Inquiry::query()->sole();
        $response->assertRedirect($this->showUrl($inquiry));

        $this->assertSame(Inquiry::ORIGIN_ADMIN_CREATED, $inquiry->origin);
        $this->assertSame($this->admin->id, $inquiry->created_by);
        $this->assertSame($this->a->id, $inquiry->assigned_to);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->status);
        $this->assertSame($channel, $inquiry->contact_channel);
        $this->assertNull($inquiry->incoming_inquiry_id);
        $this->assertNull($inquiry->booking_id);
        $this->assertNull($inquiry->client_user_id);
        $this->assertNotNull($inquiry->assigned_at);
    }

    public static function channels(): array
    {
        return [['phone'], ['messenger']];
    }

    public function test_admin_may_choose_an_active_admin_including_themselves(): void
    {
        $other = $this->userWith('admin');

        $this->store($this->admin, ['assignee_id' => $other->id])->assertSessionHasNoErrors();
        $this->store($this->admin, ['assignee_id' => $this->admin->id])->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$other->id, $this->admin->id], Inquiry::query()->pluck('assigned_to')->all());
        $this->assertSame([$this->admin->id], Inquiry::query()->pluck('created_by')->unique()->values()->all());
    }

    public function test_creation_has_no_side_effects_on_other_records(): void
    {
        $users = User::query()->count();

        $this->store($this->admin);

        $this->assertSame(0, Booking::query()->count());
        $this->assertSame(0, IncomingInquiry::query()->count());
        $this->assertSame($users, User::query()->count());
        Mail::assertNothingOutgoing();
        Notification::assertNothingSent();
    }

    public function test_selected_manager_opens_it_and_foreign_manager_does_not(): void
    {
        $this->store($this->admin, ['assignee_id' => $this->a->id]);
        $inquiry = Inquiry::query()->sole();

        $this->actingAs($this->a)->get($this->showUrl($inquiry))->assertOk();
        $this->actingAs($this->b)->get($this->showUrl($inquiry))->assertNotFound();
        $this->actingAs($this->admin)->get($this->showUrl($inquiry))->assertOk();
        $this->assertStringContainsString($this->showUrl($inquiry), $this->listHtml($this->a));
        $this->assertStringNotContainsString($this->showUrl($inquiry), $this->listHtml($this->b));
    }

    public function test_assignment_controls_are_admin_only(): void
    {
        $this->store($this->admin, ['assignee_id' => $this->a->id]);
        $inquiry = Inquiry::query()->sole();

        $this->assertStringContainsString('История назначений', $this->actingAs($this->admin)->get($this->showUrl($inquiry))->getContent());
        $htmlManager = $this->actingAs($this->a)->get($this->showUrl($inquiry))->assertOk()->getContent();
        $this->assertStringNotContainsString('История назначений', $htmlManager);
        $this->assertStringNotContainsString('agency-inquiry-assignment-title', $htmlManager);
    }

    public function test_inactive_tourist_missing_and_unknown_assignees_are_rejected_without_creation(): void
    {
        $this->b->forceFill(['is_active' => false])->save();

        foreach ([$this->b->id, $this->tourist->id, 999999] as $id) {
            $this->store($this->admin, ['assignee_id' => $id])->assertSessionHasErrors('assignee_id');
        }
        $this->store($this->admin, ['assignee_id' => null])->assertSessionHasErrors('assignee_id');
        $this->store($this->admin, ['assignee_id' => 'abc'])->assertSessionHasErrors('assignee_id');

        $missing = $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.store'), [
            'contact_channel' => 'phone', 'creation_token' => (string) Str::uuid(),
        ]);
        $missing->assertSessionHasErrors('assignee_id');

        $this->assertSame(0, Inquiry::query()->count());
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryCreated));
    }

    public function test_invalid_channel_is_rejected(): void
    {
        foreach (['email', '', 'PHONE'] as $channel) {
            $this->store($this->admin, ['contact_channel' => $channel])->assertSessionHasErrors('contact_channel');
        }
        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_protected_fields_cannot_be_spoofed(): void
    {
        foreach (['created_by' => $this->b->id, 'origin' => 'manager_created', 'status' => 'new', 'booking_id' => 1,
            'incoming_inquiry_id' => 1, 'client_user_id' => $this->tourist->id, 'assigned_at' => '2020-01-01'] as $field => $value) {
            $this->store($this->admin, [$field => $value])->assertSessionHasErrors('request');
        }

        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_manager_creation_is_unchanged_and_cannot_choose_an_assignee(): void
    {
        $this->actingAs($this->a)->get(route('cabinet.manager.agency-inquiries.create'))
            ->assertOk()->assertDontSee('Ответственный сотрудник');

        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.store'), [
            'contact_channel' => 'phone', 'creation_token' => (string) Str::uuid(), 'assignee_id' => $this->b->id,
        ])->assertSessionHasErrors('request');
        $this->assertSame(0, Inquiry::query()->count());

        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.store'), [
            'contact_channel' => 'phone', 'creation_token' => (string) Str::uuid(),
        ])->assertSessionHasNoErrors();
        $inquiry = Inquiry::query()->sole();
        $this->assertSame(Inquiry::ORIGIN_MANAGER_CREATED, $inquiry->origin);
        $this->assertSame($this->a->id, $inquiry->assigned_to);
    }

    public function test_admin_form_shows_the_staff_selector_with_minimal_data(): void
    {
        $html = $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries.create'))
            ->assertOk()->assertSee('Ответственный сотрудник')->getContent();

        $this->assertStringContainsString($this->a->name, $html);
        $this->assertStringNotContainsString($this->a->email, $html);
        $this->assertStringNotContainsString($this->tourist->name, $html);
    }

    public function test_tourist_cannot_create_even_with_an_assignee(): void
    {
        $this->store($this->tourist)->assertForbidden();
        $this->actingAs($this->tourist)->get(route('cabinet.manager.agency-inquiries.create'))->assertForbidden();
        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_dual_role_account_follows_the_admin_route_rules(): void
    {
        $both = $this->userWith('manager');
        $both->roles()->attach(Role::query()->where('name', 'admin')->value('id'));

        $this->store($both, ['assignee_id' => $this->b->id])->assertSessionHasNoErrors();

        $inquiry = Inquiry::query()->sole();
        $this->assertSame(Inquiry::ORIGIN_ADMIN_CREATED, $inquiry->origin);
        $this->assertSame($both->id, $inquiry->created_by);
        $this->assertSame($this->b->id, $inquiry->assigned_to);
    }

    public function test_inactive_admin_cannot_create_through_the_service(): void
    {
        $this->admin->forceFill(['is_active' => false])->save();

        $this->expectException(AuthorizationException::class);

        try {
            app(ManualInquiryCreator::class)->createForAdmin($this->admin, $this->a->id, 'phone', (string) Str::uuid());
        } finally {
            $this->assertSame(0, Inquiry::query()->count());
        }
    }

    public function test_manager_and_tourist_cannot_use_the_admin_service(): void
    {
        foreach ([$this->a, $this->tourist] as $actor) {
            try {
                app(ManualInquiryCreator::class)->createForAdmin($actor, $this->a->id, 'phone', (string) Str::uuid());
                $this->fail('A non-admin used the admin creation entry point.');
            } catch (AuthorizationException) {
                // expected
            }
        }
        $this->assertSame(0, Inquiry::query()->count());
    }

    // ───────── Аудит ─────────

    public function test_creation_audit_records_admin_actor_and_ids_only(): void
    {
        $this->store($this->admin, ['contact_channel' => 'messenger', 'assignee_id' => $this->a->id]);
        $inquiry = Inquiry::query()->sole();

        $event = AuditEvent::query()->where('event_type', AuditEventType::InquiryCreated->value)->sole();
        $this->assertSame($this->admin->id, (int) $event->actor_id);
        $this->assertSame($inquiry->id, (int) $event->subject_id);
        $this->assertEquals([
            'origin' => 'admin_created',
            'contact_channel' => 'messenger',
            'initial_assignee_id' => $this->a->id,
        ], $event->metadata);
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryAssigned));
        $this->assertSame(0, $this->auditCount(AuditEventType::InquiryReassigned));
    }

    public function test_audit_failure_rolls_back_the_creation(): void
    {
        $this->app->instance(AuditRecorder::class, new class extends AuditRecorder {
            public function record(...$args): never
            {
                throw new RuntimeException('audit down');
            }
        });

        try {
            app(ManualInquiryCreator::class)->createForAdmin($this->admin, $this->a->id, 'phone', (string) Str::uuid());
            $this->fail('Creation survived an audit failure.');
        } catch (RuntimeException) {
            // expected
        }

        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_history_shows_initial_assignment_once_and_later_transfers_separately(): void
    {
        $this->store($this->admin, ['assignee_id' => $this->a->id]);
        $inquiry = Inquiry::query()->sole();

        $history = app(InquiryAssignment::class)->history($inquiry);
        $this->assertCount(1, $history);
        $this->assertTrue($history[0]['created']);
        $this->assertSame($this->a->id, $history[0]['new_id']);
        $this->assertNull($history[0]['previous_id']);

        app(InquiryAssignment::class)->assign($inquiry, $this->admin, $this->b->id, $this->a->id, 'Перераспределение нагрузки');
        $history = app(InquiryAssignment::class)->history($inquiry);
        $this->assertCount(2, $history);
        $this->assertFalse($history[0]['created']);
        $this->assertSame($this->a->id, $history[0]['previous_id']);
        $this->assertTrue($history[1]['created']);

        $html = $this->actingAs($this->admin)->get($this->showUrl($inquiry))->getContent();
        $this->assertStringContainsString('Создано администратором', $html);
        $this->assertStringContainsString('первый ответственный', $html);
    }

    public function test_manager_created_inquiry_does_not_appear_in_assignment_history(): void
    {
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.store'), [
            'contact_channel' => 'phone', 'creation_token' => (string) Str::uuid(),
        ]);

        $this->assertCount(0, app(InquiryAssignment::class)->history(Inquiry::query()->sole()));
    }

    // ───────── Идемпотентность ─────────

    public function test_same_token_returns_the_existing_inquiry_without_changes_or_new_events(): void
    {
        $token = (string) Str::uuid();

        $this->store($this->admin, ['creation_token' => $token, 'assignee_id' => $this->a->id, 'contact_channel' => 'phone']);
        $first = Inquiry::query()->sole();

        $retry = $this->store($this->admin, ['creation_token' => $token, 'assignee_id' => $this->b->id, 'contact_channel' => 'messenger']);

        $retry->assertRedirect($this->showUrl($first));
        $this->assertSame(1, Inquiry::query()->count());
        $this->assertSame(1, $this->auditCount(AuditEventType::InquiryCreated));
        $first->refresh();
        $this->assertSame($this->a->id, $first->assigned_to);
        $this->assertSame('phone', $first->contact_channel);
    }

    public function test_duplicate_retry_after_assignee_became_ineligible_still_returns_existing(): void
    {
        $token = (string) Str::uuid();
        $this->store($this->admin, ['creation_token' => $token, 'assignee_id' => $this->a->id]);
        $this->a->forceFill(['is_active' => false])->save();

        $this->store($this->admin, ['creation_token' => $token, 'assignee_id' => $this->a->id])->assertSessionHasNoErrors();

        $this->assertSame(1, Inquiry::query()->count());
    }

    public function test_token_is_scoped_to_the_creating_actor_and_never_exposes_a_foreign_inquiry(): void
    {
        $token = (string) Str::uuid();
        $this->actingAs($this->a)->post(route('cabinet.manager.agency-inquiries.store'), ['contact_channel' => 'phone', 'creation_token' => $token]);
        $managerInquiry = Inquiry::query()->sole();

        // Администратор, использующий тот же токен, получает собственное обращение, а не чужое.
        $this->store($this->admin, ['creation_token' => $token, 'assignee_id' => $this->b->id]);

        $this->assertSame(2, Inquiry::query()->count());
        $adminInquiry = Inquiry::query()->where('created_by', $this->admin->id)->sole();
        $this->assertNotSame($managerInquiry->id, $adminInquiry->id);
    }

    public function test_service_reports_whether_the_inquiry_is_new(): void
    {
        $creator = app(ManualInquiryCreator::class);
        $token = (string) Str::uuid();

        $this->assertTrue($creator->createForAdmin($this->admin, $this->a->id, 'phone', $token)->wasRecentlyCreated);
        $this->assertFalse($creator->createForAdmin($this->admin, $this->b->id, 'phone', $token)->wasRecentlyCreated);
    }

    public function test_service_rejects_invalid_input(): void
    {
        $creator = app(ManualInquiryCreator::class);

        foreach ([
            fn () => $creator->createForAdmin($this->admin, $this->a->id, 'email', (string) Str::uuid()),
            fn () => $creator->createForAdmin($this->admin, $this->a->id, 'phone', 'not-a-uuid'),
            fn () => $creator->createForAdmin($this->admin, $this->tourist->id, 'phone', (string) Str::uuid()),
        ] as $call) {
            try {
                $call();
                $this->fail('Invalid input was accepted.');
            } catch (ValidationException) {
                // expected
            }
        }
        $this->assertSame(0, Inquiry::query()->count());
    }

    // ───────── Фильтры списка ─────────

    private function seedList(): array
    {
        $a1 = Inquiry::factory()->managerCreated($this->a)->create(['destination_country' => 'Турция']);
        $a2 = Inquiry::factory()->adminCreated($this->admin, $this->a)->create(['contact_channel' => 'messenger', 'destination_country' => 'Египет']);
        $b1 = Inquiry::factory()->managerCreated($this->b)->create(['destination_country' => 'Греция']);
        $closed = Inquiry::factory()->managerCreated($this->b)->create(['destination_country' => 'Тайланд']);
        $closed->forceFill(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()])->save();
        $orphan = Inquiry::factory()->managerCreated($this->b)->create(['destination_country' => 'Кипр']);
        // Как после удаления аккаунта ответственного: БД обнуляет assigned_to в обход инвариантов модели.
        \DB::table('inquiries')->where('id', $orphan->id)->update(['assigned_to' => null]);

        return compact('a1', 'a2', 'b1', 'closed', 'orphan');
    }

    public function test_status_and_channel_filters_narrow_the_admin_list(): void
    {
        ['a1' => $a1, 'a2' => $a2, 'b1' => $b1, 'closed' => $closed] = $this->seedList();

        $html = $this->listHtml($this->admin, ['status' => 'closed']);
        $this->assertStringContainsString($this->showUrl($closed), $html);
        $this->assertStringNotContainsString($this->showUrl($a1), $html);

        $html = $this->listHtml($this->admin, ['channel' => 'messenger']);
        $this->assertStringContainsString($this->showUrl($a2), $html);
        $this->assertStringNotContainsString($this->showUrl($a1), $html);
        $this->assertStringNotContainsString($this->showUrl($b1), $html);

        $html = $this->listHtml($this->admin, ['status' => 'in_progress', 'channel' => 'phone']);
        $this->assertStringContainsString($this->showUrl($a1), $html);
        $this->assertStringNotContainsString($this->showUrl($a2), $html);
        $this->assertStringNotContainsString($this->showUrl($closed), $html);
    }

    public function test_admin_can_filter_by_assignee_and_unassigned(): void
    {
        ['a1' => $a1, 'a2' => $a2, 'b1' => $b1, 'orphan' => $orphan] = $this->seedList();

        $html = $this->listHtml($this->admin, ['assignee' => (string) $this->a->id]);
        $this->assertStringContainsString($this->showUrl($a1), $html);
        $this->assertStringContainsString($this->showUrl($a2), $html);
        $this->assertStringNotContainsString($this->showUrl($b1), $html);

        $html = $this->listHtml($this->admin, ['assignee' => 'none']);
        $this->assertStringContainsString($this->showUrl($orphan), $html);
        $this->assertStringNotContainsString($this->showUrl($a1), $html);

        $html = $this->listHtml($this->admin, ['assignee' => (string) $this->a->id, 'channel' => 'messenger']);
        $this->assertStringContainsString($this->showUrl($a2), $html);
        $this->assertStringNotContainsString($this->showUrl($a1), $html);
    }

    public function test_manager_cannot_widen_the_list_with_filter_parameters(): void
    {
        ['a1' => $a1, 'a2' => $a2, 'b1' => $b1, 'orphan' => $orphan] = $this->seedList();

        foreach ([['assignee' => (string) $this->b->id], ['assignee' => 'none'], ['assignee' => (string) $this->a->id], []] as $query) {
            $html = $this->listHtml($this->a, $query);
            $this->assertStringContainsString($this->showUrl($a1), $html);
            $this->assertStringContainsString($this->showUrl($a2), $html);
            $this->assertStringNotContainsString($this->showUrl($b1), $html);
            $this->assertStringNotContainsString($this->showUrl($orphan), $html);
            $this->assertStringNotContainsString('ai-filter-assignee', $html);
        }

        // Статус/канал сужают дальше внутри собственных обращений менеджера.
        $html = $this->listHtml($this->a, ['channel' => 'messenger']);
        $this->assertStringContainsString($this->showUrl($a2), $html);
        $this->assertStringNotContainsString($this->showUrl($a1), $html);
    }

    public function test_invalid_filter_values_are_ignored_not_injected(): void
    {
        $this->seedList();

        $html = $this->actingAs($this->admin)->get(route('cabinet.manager.agency-inquiries', [
            'status' => "x' OR 1=1 --", 'channel' => ['phone'], 'assignee' => '999999',
        ]))->assertOk()->getContent();

        $this->assertStringContainsString('Часть условий фильтра недопустима', $html);
        $this->assertStringNotContainsString("x' OR 1=1", $html);
        $this->assertStringContainsString('Кипр', $html);
    }

    public function test_empty_filtered_result_has_its_own_message_and_no_foreign_titles(): void
    {
        $this->seedList();

        $html = $this->listHtml($this->a, ['status' => 'closed']);
        $this->assertStringContainsString('По выбранным условиям обращений нет', $html);
        $this->assertStringNotContainsString('Тайланд', $html);
    }

    public function test_pagination_keeps_filters_and_orders_deterministically(): void
    {
        foreach (range(1, 25) as $i) {
            Inquiry::factory()->managerCreated($this->a)->create();
        }
        Inquiry::factory()->managerCreated($this->b)->create(['contact_channel' => 'messenger']);

        $page1 = $this->listHtml($this->admin, ['channel' => 'phone']);
        $this->assertStringContainsString('channel=phone', $page1);
        $this->assertStringContainsString('page=2', $page1);

        $page2 = $this->listHtml($this->admin, ['channel' => 'phone', 'page' => 2]);
        $ids = Inquiry::query()->where('contact_channel', 'phone')->orderByDesc('id')->pluck('id');
        $this->assertStringContainsString($this->showUrl(Inquiry::find($ids[20])), $page2);
        $this->assertStringNotContainsString($this->showUrl(Inquiry::find($ids[0])), $page2);
        // Ссылка из списка несёт фильтры и страницу, карточка возвращает к ним же.
        $this->assertStringContainsString('page=2', $page2);

        $card = $this->actingAs($this->admin)->get($this->showUrl(Inquiry::find($ids[20])) . '?channel=phone&page=2')->getContent();
        $this->assertStringContainsString('channel=phone', $card);
        $this->assertStringContainsString('page=2', $card);
    }

    public function test_back_link_ignores_unsafe_parameters(): void
    {
        $inquiry = Inquiry::factory()->managerCreated($this->a)->create();

        $html = $this->actingAs($this->a)->get($this->showUrl($inquiry) . '?status=%22%3E%3Cscript%3E&channel=phone&evil=1')->assertOk()->getContent();

        $this->assertStringNotContainsString('<script>alert', $html);
        $this->assertStringNotContainsString('evil=1', $html);
        $this->assertStringContainsString('channel=phone', $html);
    }

    public function test_lists_do_not_include_linked_external_inquiries_or_user_models(): void
    {
        $source = new IncomingInquiry();
        $source->forceFill([
            'provider' => 'tourvisor', 'external_type' => 0, 'external_id' => '000321',
            'state' => IncomingInquiry::STATE_IMPORTED, 'webhook_count' => 1, 'assigned_to' => $this->a->id,
        ])->save();
        $linked = Inquiry::factory()->externalIntake($source)->create();
        $own = Inquiry::factory()->managerCreated($this->a)->create();

        foreach ([$this->admin, $this->a] as $viewer) {
            $html = $this->listHtml($viewer, ['status' => 'in_progress']);
            $this->assertStringNotContainsString($this->showUrl($linked), $html);
            $this->assertStringContainsString($this->showUrl($own), $html);
            $this->assertStringNotContainsString($this->a->email, $html);
            $this->assertStringNotContainsString($this->b->email, $html);
        }
    }

    public function test_list_query_count_does_not_grow_with_rows(): void
    {
        foreach (range(1, 15) as $i) {
            Inquiry::factory()->managerCreated($this->a)->create();
        }
        $this->actingAs($this->admin);

        \DB::enableQueryLog();
        $this->get(route('cabinet.manager.agency-inquiries', ['status' => 'in_progress']))->assertOk();
        $few = count(\DB::getQueryLog());

        foreach (range(1, 15) as $i) {
            Inquiry::factory()->managerCreated($this->b)->create();
        }
        \DB::flushQueryLog();
        $this->get(route('cabinet.manager.agency-inquiries', ['status' => 'in_progress']))->assertOk();

        $this->assertSame($few, count(\DB::getQueryLog()));
    }

    public function test_draft_updates_and_reassignment_are_unchanged_for_admin_created_inquiries(): void
    {
        $this->store($this->admin, ['assignee_id' => $this->a->id]);
        $inquiry = Inquiry::query()->sole();

        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => 0, 'adults' => 2])
            ->assertSessionHasNoErrors();
        $this->assertSame(2, $inquiry->refresh()->adults);

        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Проверка передачи',
        ])->assertSessionHasNoErrors();

        $this->assertSame($this->b->id, $inquiry->refresh()->assigned_to);
        $this->assertSame($this->admin->id, $inquiry->created_by);
        $this->actingAs($this->a)->get($this->showUrl($inquiry))->assertNotFound();
        $this->assertSame(1, $this->auditCount(AuditEventType::InquiryReassigned));
    }
}
