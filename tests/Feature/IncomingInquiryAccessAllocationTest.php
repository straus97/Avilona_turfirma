<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\IncomingInquiries\IncomingInquiryWorkflow;
use App\Services\IncomingInquiries\IncomingInquiryWorkflowException;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use RuntimeException;
use Tests\TestCase;

/**
 * AOA-01-I2A — доступ к входящим обращениям и распределение администратором.
 *
 * Менеджер видит только назначенные ему обращения (чужое/ничьё — 404 без данных),
 * назначает и переназначает только администратор (переназначение — с причиной и
 * событием аудита в той же транзакции). Синтетические данные, SQLite :memory:.
 */
class IncomingInquiryAccessAllocationTest extends TestCase
{
    use RefreshDatabase;

    private const PHONE = '+7 (900) 555-01-23';

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Http::fake();

        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);
    }

    private function userWithRoles(array $roleNames, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function inquiry(array $overrides = []): IncomingInquiry
    {
        $inquiry = new IncomingInquiry();
        $inquiry->forceFill(array_merge([
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => (string) random_int(100000, 999999),
            'state' => IncomingInquiry::STATE_IMPORTED,
            'webhook_count' => 1,
            'first_notified_at' => now(),
            'last_notified_at' => now(),
            'imported_at' => now(),
            'client_name' => 'Синтетический Клиент',
            'client_phone' => self::PHONE,
            'client_email' => 'synthetic.client@example.test',
            'client_comment' => 'Приватный комментарий клиента',
            'operator_name' => 'Sunmar',
            'departure_city' => 'Москва',
            'destination_country' => 'Таиланд',
            'hotel_name' => 'TEST RESORT 3*',
            'fly_date' => now()->addMonths(2)->toDateString(),
            'price' => '100000',
            'currency' => 'RUB',
            'nights' => 7,
            'vendor_payload' => ['internal_marker' => 'RAW-PAYLOAD-MARKER'],
            'normalizer_version' => 1,
        ], $overrides))->save();

        return $inquiry->refresh();
    }

    /** Обращение, уже закреплённое за сотрудником (как после назначения администратором). */
    private function assignedTo(User $user, array $overrides = []): IncomingInquiry
    {
        return $this->inquiry(array_merge([
            'assigned_to' => $user->id,
            'workflow_state' => IncomingInquiry::WORKFLOW_IN_PROGRESS,
            'claimed_at' => now(),
        ], $overrides));
    }

    private function reassignPayload(User $to, User $expected, string $reason = 'Клиент просил другого менеджера'): array
    {
        return ['assigned_to' => $to->id, 'expected_assignee_id' => $expected->id, 'reason' => $reason];
    }

    private function convertPayload(): array
    {
        return [
            'departure_city' => 'Москва',
            'destination_country' => 'Таиланд',
            'destination_city' => 'Пхукет',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => '100000',
            'price_verified' => '1',
        ];
    }

    private function failingRecorder(): void
    {
        $this->app->instance(AuditRecorder::class, new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });
    }

    // ------------------------------------------------------------------
    // G1 — visibility
    // ------------------------------------------------------------------

    public function test_manager_list_contains_only_own_inquiries(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);

        $this->assignedTo($a, ['external_id' => '111111']);
        $this->assignedTo($b, ['external_id' => '222222']);
        $this->assignedTo($admin, ['external_id' => '333333']);
        $this->inquiry(['external_id' => '444444']);

        $this->actingAs($a)->get(route('cabinet.manager.inquiries'))
            ->assertOk()
            ->assertSee('#111111')
            ->assertDontSee('#222222')
            ->assertDontSee('#333333')
            ->assertDontSee('#444444');
    }

    public function test_admin_list_contains_every_inquiry(): void
    {
        $a = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);

        $this->assignedTo($a, ['external_id' => '111111']);
        $this->assignedTo($admin, ['external_id' => '333333']);
        $this->inquiry(['external_id' => '444444']);

        $this->actingAs($admin)->get(route('cabinet.manager.inquiries'))
            ->assertOk()
            ->assertSee('#111111')
            ->assertSee('#333333')
            ->assertSee('#444444');
    }

    public function test_pagination_and_filters_do_not_leak_foreign_rows(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);

        for ($i = 0; $i < 3; $i++) {
            $this->assignedTo($a);
        }
        for ($i = 0; $i < 30; $i++) {
            $this->assignedTo($b);
        }
        for ($i = 0; $i < 5; $i++) {
            $this->inquiry();
        }

        $response = $this->actingAs($a)->get(route('cabinet.manager.inquiries'));
        $response->assertOk();
        $paginator = $response->viewData('inquiries');
        $this->assertSame(3, $paginator->total());
        $this->assertFalse($paginator->hasPages());
        $this->assertSame([$a->id], $paginator->getCollection()->pluck('assigned_to')->unique()->values()->all());

        // Вторая страница чужих записей не существует для менеджера.
        $page2 = $this->actingAs($a)->get(route('cabinet.manager.inquiries', ['page' => 2]));
        $this->assertCount(0, $page2->viewData('inquiries')->getCollection());

        // «Новые» у менеджера пусто: ничьи обращения ему недоступны.
        $new = $this->actingAs($a)->get(route('cabinet.manager.inquiries', ['status' => 'new']));
        $this->assertSame(0, $new->viewData('inquiries')->total());

        $progress = $this->actingAs($a)->get(route('cabinet.manager.inquiries', ['status' => 'in_progress']));
        $this->assertSame(3, $progress->viewData('inquiries')->total());
    }

    public function test_manager_cannot_open_foreign_unassigned_or_admin_owned_inquiry_and_gets_no_data(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);

        $foreign = [
            $this->assignedTo($b),
            $this->assignedTo($admin),
            $this->inquiry(),
        ];

        foreach ($foreign as $inquiry) {
            $response = $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', $inquiry));
            $response->assertNotFound();
            $response->assertDontSee(self::PHONE);
            $response->assertDontSee('Синтетический Клиент');
            $response->assertDontSee('Приватный комментарий клиента');
            $response->assertDontSee('synthetic.client@example.test');

            $json = $this->actingAs($a)->getJson(route('cabinet.manager.inquiries.show', $inquiry));
            $json->assertNotFound();
            $this->assertStringNotContainsString(self::PHONE, $json->getContent());
            $this->assertStringNotContainsString('Синтетический', $json->getContent());
        }

        $own = $this->assignedTo($a);
        $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', $own))->assertOk()->assertSee(self::PHONE);
        $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', 999999))->assertNotFound();
    }

    public function test_admin_can_open_any_inquiry(): void
    {
        $a = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);

        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', $this->assignedTo($a)))->assertOk();
        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', $this->inquiry()))->assertOk();
    }

    public function test_every_inquiry_action_is_hidden_from_a_manager_who_does_not_own_it(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);

        $targets = [$this->assignedTo($b), $this->inquiry()];

        $actions = [
            ['claim', []],
            ['assign', ['assigned_to' => $a->id]],
            ['reassign', $this->reassignPayload($a, $b)],
            ['client.select', ['client_id' => $tourist->id]],
            ['client.create', ['client_name' => 'X']],
            ['client.password-setup', []],
            ['convert', $this->convertPayload()],
            ['close', ['close_confirmed' => '1']],
        ];

        foreach ($targets as $inquiry) {
            $before = $inquiry->fresh()->toArray();

            foreach ($actions as [$name, $payload]) {
                $response = $this->actingAs($a)->post(route('cabinet.manager.inquiries.' . $name, $inquiry), $payload);
                $response->assertNotFound();
                $response->assertSessionMissing('success');
                $response->assertSessionMissing('errors');
            }

            $this->assertSame($before, $inquiry->fresh()->toArray());
        }

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_tourist_has_no_access_to_inquiry_management(): void
    {
        $tourist = $this->userWithRoles(['tourist']);
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($manager);

        $this->actingAs($tourist)->get(route('cabinet.manager.inquiries'))->assertForbidden();
        $this->actingAs($tourist)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($tourist)->getJson(route('cabinet.manager.inquiries.show', $inquiry))->assertForbidden();
        $this->actingAs($tourist)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $tourist->id])->assertForbidden();
        $this->actingAs($tourist)->post(route('cabinet.manager.inquiries.reassign', $inquiry), $this->reassignPayload($tourist, $manager))->assertForbidden();

        $this->assertSame($manager->id, $inquiry->fresh()->assigned_to);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_visibility_scope_matches_policy_for_every_role(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);
        $tourist = $this->userWithRoles(['tourist']);
        $norole = User::factory()->create();

        $mine = $this->assignedTo($a);
        $theirs = $this->assignedTo($b);
        $free = $this->inquiry();

        foreach ([$a, $b, $admin, $tourist, $norole] as $user) {
            $visibleIds = IncomingInquiry::query()->visibleTo($user)->pluck('id')->all();

            foreach ([$mine, $theirs, $free] as $inquiry) {
                $this->assertSame(
                    $user->can('view', $inquiry),
                    in_array($inquiry->id, $visibleIds, true),
                    'scope/policy mismatch for user ' . $user->id . ' inquiry ' . $inquiry->id
                );
            }
        }
    }

    // ------------------------------------------------------------------
    // G2 — claim / close
    // ------------------------------------------------------------------

    public function test_manager_cannot_claim_assign_or_reassign_even_for_own_inquiry(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $own = $this->assignedTo($a);

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.claim', $own))->assertForbidden();
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.assign', $own), ['assigned_to' => $b->id])->assertForbidden();
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.reassign', $own), $this->reassignPayload($b, $a))->assertForbidden();

        $this->assertSame($a->id, $own->fresh()->assigned_to);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_manager_closes_only_own_inquiry(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);

        $free = $this->inquiry();
        $foreign = $this->assignedTo($b);
        $own = $this->assignedTo($a);

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.close', $free), ['close_confirmed' => '1'])->assertNotFound();
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.close', $foreign), ['close_confirmed' => '1'])->assertNotFound();
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.close', $own), ['close_confirmed' => '1'])->assertSessionHas('success');

        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $free->fresh()->workflow_state);
        $this->assertSame(IncomingInquiry::WORKFLOW_IN_PROGRESS, $foreign->fresh()->workflow_state);
        $this->assertSame(IncomingInquiry::WORKFLOW_CLOSED, $own->fresh()->workflow_state);
    }

    public function test_service_level_manager_claim_of_unassigned_inquiry_is_refused(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();

        $this->expectException(AuthorizationException::class);

        try {
            app(IncomingInquiryWorkflow::class)->claim($inquiry, $manager);
        } finally {
            $this->assertNull($inquiry->fresh()->assigned_to);
        }
    }

    // ------------------------------------------------------------------
    // G4 — direct Admin assignment
    // ------------------------------------------------------------------

    public function test_admin_assigns_new_inquiry_directly_to_a_manager_and_records_audit(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry(['external_id' => '777777']);

        $this->actingAs($admin)
            ->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $manager->id])
            ->assertRedirect(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertSessionHas('success');

        $fresh = $inquiry->fresh();
        $this->assertSame($manager->id, $fresh->assigned_to);
        $this->assertSame(IncomingInquiry::WORKFLOW_IN_PROGRESS, $fresh->workflow_state);
        $this->assertNotNull($fresh->claimed_at);

        // Происхождение, дедупликация и связь с заявкой не тронуты; заявка не создана.
        $this->assertSame('tourvisor', $fresh->provider);
        $this->assertSame('777777', $fresh->external_id);
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $fresh->state);
        $this->assertNull($fresh->booking_id);
        $this->assertSame(0, Booking::count());

        $event = AuditEvent::query()->sole();
        $this->assertSame('inquiry.assigned', $event->event_type);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame('incoming_inquiry', $event->subject_type);
        $this->assertSame($inquiry->id, $event->subject_id);
        $this->assertSame($manager->id, $event->metadata['new_assignee_id']);
        $this->assertFalse($event->metadata['self_assigned']);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_admin_can_assign_new_inquiry_to_self(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $admin->id])
            ->assertSessionHas('success');

        $this->assertSame($admin->id, $inquiry->fresh()->assigned_to);
        $this->assertTrue(AuditEvent::query()->sole()->metadata['self_assigned']);
    }

    public function test_assignment_rejects_ineligible_targets_without_side_effects(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $tourist = $this->userWithRoles(['tourist']);
        $inactive = $this->userWithRoles(['manager'], ['is_active' => false]);
        $noRole = User::factory()->create();
        $inquiry = $this->inquiry();

        foreach ([$tourist->id, $inactive->id, $noRole->id, 987654] as $target) {
            $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $target])
                ->assertSessionHasErrors('assigned_to');
        }

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), [])->assertSessionHasErrors('assigned_to');

        $fresh = $inquiry->fresh();
        $this->assertNull($fresh->assigned_to);
        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $fresh->workflow_state);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_assignment_requires_actionable_inquiry(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $failed = $this->inquiry(['state' => IncomingInquiry::STATE_FAILED]);

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $failed), ['assigned_to' => $manager->id])
            ->assertSessionHasErrors('workflow');

        $this->assertNull($failed->fresh()->assigned_to);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_repeated_assignment_is_harmless_and_does_not_duplicate_audit(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $manager->id])->assertSessionHas('success');
        $claimedAt = $inquiry->fresh()->claimed_at;

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $manager->id])
            ->assertSessionHas('success');

        $this->assertSame(1, AuditEvent::count());
        $this->assertEquals($claimedAt, $inquiry->fresh()->claimed_at);
    }

    public function test_assignment_cannot_overwrite_a_concurrent_assignment(): void
    {
        $adminA = $this->userWithRoles(['admin']);
        $adminB = $this->userWithRoles(['admin']);
        $m1 = $this->userWithRoles(['manager']);
        $m2 = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();
        $stale = IncomingInquiry::find($inquiry->id);

        $this->actingAs($adminA)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $m1->id])->assertSessionHas('success');

        // Администратор B отправляет устаревшую форму «назначить» — чужое решение не перезаписывается.
        $this->actingAs($adminB)->post(route('cabinet.manager.inquiries.assign', $stale), ['assigned_to' => $m2->id])
            ->assertSessionHasErrors('workflow');

        $this->assertSame($m1->id, $inquiry->fresh()->assigned_to);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_assignment_rolls_back_when_audit_recording_fails(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();
        $this->failingRecorder();

        try {
            app(IncomingInquiryWorkflow::class)->assign($inquiry, $admin, $manager->id);
            $this->fail('Assignment must fail when the audit event cannot be written.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit store unavailable', $e->getMessage());
        }

        $fresh = $inquiry->fresh();
        $this->assertNull($fresh->assigned_to);
        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $fresh->workflow_state);
        $this->assertNull($fresh->claimed_at);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_assignment_alone_creates_no_booking(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $manager->id]);

        $this->assertSame(0, Booking::count());
        $this->assertNull($inquiry->fresh()->booking_id);
        $this->assertNull($inquiry->fresh()->client_user_id);
    }

    // ------------------------------------------------------------------
    // G3 — reassignment with reason and history
    // ------------------------------------------------------------------

    public function test_admin_reassigns_with_reason_and_event_is_recorded(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $old = $this->userWithRoles(['manager']);
        $new = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($old);

        $this->actingAs($admin)
            ->post(route('cabinet.manager.inquiries.reassign', $inquiry), $this->reassignPayload($new, $old, '  Менеджер  в отпуске,   передаём коллеге '))
            ->assertSessionHas('success');

        $this->assertSame($new->id, $inquiry->fresh()->assigned_to);

        $event = AuditEvent::query()->sole();
        $this->assertSame('inquiry.reassigned', $event->event_type);
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($inquiry->id, $event->subject_id);
        $this->assertSame($old->id, $event->metadata['previous_assignee_id']);
        $this->assertSame($new->id, $event->metadata['new_assignee_id']);
        $this->assertSame('Менеджер в отпуске, передаём коллеге', $event->metadata['reason']);
        $this->assertNotNull($event->occurred_at);
    }

    public function test_reassignment_requires_a_meaningful_bounded_safe_reason(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $old = $this->userWithRoles(['manager']);
        $new = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($old);
        $url = route('cabinet.manager.inquiries.reassign', $inquiry);

        $invalid = [
            'missing' => null,
            'blank' => '     ',
            'too short' => 'ок',
            'too long' => str_repeat('а', IncomingInquiryWorkflow::REASON_MAX_LENGTH + 1),
        ];

        foreach ($invalid as $label => $reason) {
            $payload = ['assigned_to' => $new->id, 'expected_assignee_id' => $old->id];
            if ($reason !== null) {
                $payload['reason'] = $reason;
            }

            $this->actingAs($admin)->post($url, $payload)->assertSessionHasErrors('reason');
        }

        // Небезопасный для журнала текст отклоняется с понятной ошибкой, валидатор метаданных не ослаблен.
        foreach (['Писала на client@example.com', 'См. файл C:\\secret\\notes.txt', 'Код ' . str_repeat('A1', 20) . ' передан'] as $unsafe) {
            $this->actingAs($admin)->post($url, $this->reassignPayload($new, $old, $unsafe))->assertSessionHasErrors('reason');
        }

        $this->assertSame($old->id, $inquiry->fresh()->assigned_to);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_reassignment_requires_assignable_target_and_in_progress_state(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $old = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inactive = $this->userWithRoles(['manager'], ['is_active' => false]);
        $inquiry = $this->assignedTo($old);
        $unassigned = $this->inquiry();

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), $this->reassignPayload($tourist, $old))
            ->assertSessionHasErrors('assigned_to');
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), $this->reassignPayload($inactive, $old))
            ->assertSessionHasErrors('assigned_to');

        // Первичное назначение и переназначение — разные операции.
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $unassigned), ['assigned_to' => $old->id, 'expected_assignee_id' => 0, 'reason' => 'Тестовая причина'])
            ->assertSessionHasErrors('workflow');

        $this->assertSame($old->id, $inquiry->fresh()->assigned_to);
        $this->assertNull($unassigned->fresh()->assigned_to);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_previous_manager_loses_access_and_new_manager_gains_it_immediately(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $old = $this->userWithRoles(['manager']);
        $new = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($old);

        $this->actingAs($old)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk();
        $this->actingAs($new)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertNotFound();

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), $this->reassignPayload($new, $old))
            ->assertSessionHas('success');

        $this->actingAs($old)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertNotFound()->assertDontSee(self::PHONE);
        $this->actingAs($old)->post(route('cabinet.manager.inquiries.close', $inquiry), ['close_confirmed' => '1'])->assertNotFound();
        $this->actingAs($old)->get(route('cabinet.manager.inquiries'))->assertOk()->assertDontSee('#' . $inquiry->external_id);

        $this->actingAs($new)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()->assertSee(self::PHONE);
        $this->actingAs($new)->get(route('cabinet.manager.inquiries'))->assertOk()->assertSee('#' . $inquiry->external_id);
    }

    public function test_stale_reassignment_is_rejected_and_retry_does_not_duplicate_history(): void
    {
        $adminA = $this->userWithRoles(['admin']);
        $adminB = $this->userWithRoles(['admin']);
        $m1 = $this->userWithRoles(['manager']);
        $m2 = $this->userWithRoles(['manager']);
        $m3 = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($m1);
        $url = route('cabinet.manager.inquiries.reassign', $inquiry);

        $this->actingAs($adminA)->post($url, $this->reassignPayload($m2, $m1))->assertSessionHas('success');
        $this->assertSame(1, AuditEvent::count());

        // Тот же запрос повторён (двойная отправка): ничего не меняется, второго события нет.
        $this->actingAs($adminA)->post($url, $this->reassignPayload($m2, $m1))->assertSessionHas('success');
        $this->assertSame(1, AuditEvent::count());

        // Администратор B видел старого ответственного m1: его решение устарело.
        $this->actingAs($adminB)->post($url, $this->reassignPayload($m3, $m1))->assertSessionHasErrors('assigned_to');

        $this->assertSame($m2->id, $inquiry->fresh()->assigned_to);
        $this->assertSame(1, AuditEvent::count());
    }

    public function test_reassignment_rolls_back_when_audit_recording_fails(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $old = $this->userWithRoles(['manager']);
        $new = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($old);
        $claimedAt = $inquiry->claimed_at;
        $this->failingRecorder();

        try {
            app(IncomingInquiryWorkflow::class)->reassign($inquiry, $admin, $new->id, 'Передаю по просьбе клиента', $old->id);
            $this->fail('Reassignment must fail when the audit event cannot be written.');
        } catch (RuntimeException) {
            // expected
        }

        $fresh = $inquiry->fresh();
        $this->assertSame($old->id, $fresh->assigned_to);
        $this->assertEquals($claimedAt, $fresh->claimed_at);
        $this->assertSame(0, AuditEvent::count());
    }

    public function test_assignment_history_is_visible_to_admin_only(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $old = $this->userWithRoles(['manager'], ['name' => 'Первый Менеджер']);
        $new = $this->userWithRoles(['manager'], ['name' => 'Второй Менеджер']);
        $inquiry = $this->assignedTo($old);

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), $this->reassignPayload($new, $old, 'Смена зоны ответственности'));

        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertOk()
            ->assertSee('История назначений')
            ->assertSee('Смена зоны ответственности')
            ->assertSee('Первый Менеджер')
            ->assertSee('Второй Менеджер');

        $this->actingAs($new)->get(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertOk()
            ->assertDontSee('История назначений')
            ->assertDontSee('Смена зоны ответственности');
    }

    public function test_admin_forms_are_rendered_only_for_admin(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $free = $this->inquiry();
        $owned = $this->assignedTo($manager);

        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', $free))
            ->assertOk()
            ->assertSee(route('cabinet.manager.inquiries.assign', $free), false)
            ->assertSee('Назначить ответственного');

        $this->actingAs($admin)->get(route('cabinet.manager.inquiries.show', $owned))
            ->assertOk()
            ->assertSee(route('cabinet.manager.inquiries.reassign', $owned), false)
            ->assertSee('Причина переназначения');

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $owned))
            ->assertOk()
            ->assertDontSee(route('cabinet.manager.inquiries.assign', $owned), false)
            ->assertDontSee(route('cabinet.manager.inquiries.reassign', $owned), false)
            ->assertDontSee(route('cabinet.manager.inquiries.claim', $owned), false);
    }

    // ------------------------------------------------------------------
    // G6 — authorization before idempotent returns
    // ------------------------------------------------------------------

    public function test_repeated_conversion_after_access_is_revoked_returns_no_booking(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->assignedTo($a, ['client_user_id' => $tourist->id]);

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');
        $this->assertSame(1, Booking::count());

        // Владелец сменился (исправление данных администратором): прежний владелец доступ потерял.
        $inquiry->forceFill(['assigned_to' => $b->id])->save();

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertNotFound();

        try {
            app(IncomingInquiryWorkflow::class)->convert($inquiry->fresh(), $a, $this->convertPayload());
            $this->fail('The idempotent branch must not run before authorization.');
        } catch (AuthorizationException) {
            // expected
        }

        $this->assertSame(1, Booking::count());

        // Законный повтор владельцем и админом — по-прежнему идемпотентен.
        $this->actingAs($b)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');
        $this->assertSame(1, Booking::count());
    }

    public function test_repeated_close_after_access_is_revoked_is_refused(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $inquiry = $this->assignedTo($a);

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.close', $inquiry), ['close_confirmed' => '1', 'close_reason' => 'Первая'])
            ->assertSessionHas('success');
        $inquiry->forceFill(['assigned_to' => $b->id])->save();

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.close', $inquiry), ['close_confirmed' => '1'])->assertNotFound();

        try {
            app(IncomingInquiryWorkflow::class)->close($inquiry->fresh(), $a, null);
            $this->fail('The idempotent branch must not run before authorization.');
        } catch (AuthorizationException) {
            // expected
        }

        $this->assertSame('Первая', $inquiry->fresh()->close_reason);
    }

    public function test_service_level_reassign_and_assign_by_manager_are_refused(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $own = $this->assignedTo($a);
        $free = $this->inquiry();
        $service = app(IncomingInquiryWorkflow::class);

        foreach ([
            fn () => $service->reassign($own, $a, $b->id, 'Передаю коллеге', $a->id),
            fn () => $service->assign($free, $a, $a->id),
        ] as $call) {
            try {
                $call();
                $this->fail('Manager must not be able to assign or reassign.');
            } catch (AuthorizationException) {
                // expected
            }
        }

        $this->assertSame($a->id, $own->fresh()->assigned_to);
        $this->assertNull($free->fresh()->assigned_to);
        $this->assertSame(0, AuditEvent::count());
    }

    // ------------------------------------------------------------------
    // Conversion and Booking regression
    // ------------------------------------------------------------------

    public function test_assigned_manager_converts_once_and_booking_stays_linked(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->inquiry();

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.assign', $inquiry), ['assigned_to' => $manager->id]);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $tourist->id])->assertSessionHas('success');
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');

        $this->assertSame(1, Booking::count());
        $booking = Booking::query()->sole();
        $fresh = $inquiry->fresh();
        $this->assertSame($booking->id, $fresh->booking_id);
        $this->assertSame(IncomingInquiry::WORKFLOW_CONVERTED, $fresh->workflow_state);
        $this->assertSame($manager->id, $booking->manager_id);
        $this->assertSame($tourist->id, $booking->user_id);
    }

    public function test_booking_page_links_to_inquiry_only_for_those_who_can_view_it(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $other = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->assignedTo($manager, ['client_user_id' => $tourist->id]);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');
        $booking = Booking::query()->sole();
        $link = route('cabinet.manager.inquiries.show', $inquiry);

        $this->actingAs($manager)->get(route('bookings.show', $booking))->assertOk()->assertSee($link, false);
        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk()->assertSee($link, false);

        // Заявку ведёт другой сотрудник, обращение закреплено за первым: ссылки на него нет.
        $booking->forceFill(['manager_id' => $other->id])->save();
        $this->actingAs($other)->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertDontSee($link, false)
            ->assertSee('входящее обращение #' . $inquiry->id);
    }
}
