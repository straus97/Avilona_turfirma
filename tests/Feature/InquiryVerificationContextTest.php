<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Accounts\ClientVerificationException;
use App\Services\Accounts\ClientVerificationService;
use App\Services\Accounts\ClientVisibility;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\RateLimiter;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I4, Gate B — вариант C (нейтральная проверка клиента администратором) в контексте
 * самостоятельного обращения: тот же ClientVerificationService, без разветвления потока. Синтетические
 * данные, SQLite :memory:.
 */
class InquiryVerificationContextTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const KNOWN_EMAIL = 'known.client@example.test';
    private const UNKNOWN_EMAIL = 'unknown.client@example.test';

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
        $this->a = $this->userWith('manager', ['name' => 'Анна Менеджер']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Менеджер']);
        $this->tourist = $this->userWith('tourist', ['name' => 'Существующий Турист', 'email' => self::KNOWN_EMAIL]);
    }

    private function mine(?User $manager = null): Inquiry
    {
        return Inquiry::factory()->managerCreated($manager ?? $this->a)->create()->refresh();
    }

    private function url(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.client.verification', $inquiry);
    }

    /** @return array<string, mixed> */
    private function form(string $email = self::UNKNOWN_EMAIL, array $override = []): array
    {
        return array_merge(['client_name' => 'Клиент Запроса', 'client_email' => $email, 'client_phone' => '+7 900 111-22-33'], $override);
    }

    private function submit(User $actor, Inquiry $inquiry, ?array $data = null)
    {
        return $this->actingAs($actor)->post($this->url($inquiry), $data ?? $this->form());
    }

    private function pending(Inquiry $inquiry): ClientVerificationRequest
    {
        return ClientVerificationRequest::query()->where('agency_inquiry_id', $inquiry->id)->orderByDesc('id')->firstOrFail();
    }

    private function events(string $type)
    {
        return AuditEvent::query()->where('event_type', $type)->orderBy('id')->get();
    }

    private function failAudit(): void
    {
        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });
    }

    // ---- submission --------------------------------------------------------------------------

    public function test_responsible_manager_submits_a_neutral_request_from_the_inquiry(): void
    {
        $inquiry = $this->mine();
        $users = User::query()->count();
        $bookings = Booking::query()->count();

        $this->submit($this->a, $inquiry)
            ->assertRedirect(route('cabinet.manager.agency-inquiries.show', $inquiry))
            ->assertSessionHas('success', ClientVerificationService::NEUTRAL_MESSAGE)
            ->assertSessionHasNoErrors();

        $request = $this->pending($inquiry);
        $this->assertSame(ClientVerificationRequest::CONTEXT_INQUIRY, $request->context_type);
        $this->assertSame($this->a->id, (int) $request->requested_by);
        $this->assertSame(ClientVerificationRequest::STATUS_PENDING, $request->status);
        $this->assertNull($request->incoming_inquiry_id);
        $this->assertNull($request->resolved_user_id);
        $this->assertNull($inquiry->refresh()->client_user_id, 'запрос клиента не привязывает');

        $event = $this->events('client_verification.requested')->sole();
        $this->assertSame(['context' => 'inquiry', 'agency_inquiry_id' => $inquiry->id, 'has_email' => true], $event->metadata);

        $this->assertSame($users, User::query()->count(), 'менеджер не создаёт пользователей');
        $this->assertSame($bookings, Booking::query()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_known_and_unknown_email_are_indistinguishable_and_never_look_up_users(): void
    {
        $first = $this->mine();
        $second = $this->mine();
        $run = function (Inquiry $inquiry, string $email) : array {
            DB::flushQueryLog();
            DB::enableQueryLog();
            $response = $this->submit($this->a, $inquiry, $this->form($email));
            $queries = DB::getQueryLog();
            DB::disableQueryLog();

            return [
                'status' => $response->getStatusCode(),
                'location' => $response->headers->get('Location'),
                'success' => session('success'),
                'errors' => session('errors')?->all() ?? [],
                'sql' => array_map(fn (array $q): string => (string) preg_replace('/\s+/', ' ', $q['query']), $queries),
                'userBindings' => array_merge([], ...array_map(fn (array $q): array => preg_match('/"users"/', $q['query']) === 1 ? $q['bindings'] : [], $queries)),
            ];
        };

        $known = $run($first, self::KNOWN_EMAIL);
        auth()->logout();
        $unknown = $run($second, self::UNKNOWN_EMAIL);

        $this->assertSame($known['status'], $unknown['status']);
        $this->assertSame(302, $known['status']);
        $this->assertSame($known['success'], $unknown['success']);
        $this->assertSame([], $known['errors']);
        $this->assertSame([], $unknown['errors']);

        // Тот же набор SQL-операторов и то же число запросов: время ответа не зависит от существования аккаунта.
        $this->assertSame($known['sql'], $unknown['sql']);

        foreach ([$known, $unknown] as $result) {
            foreach ($result['sql'] as $sql) {
                if (preg_match('/\bfrom "users"/i', $sql) === 1) {
                    $this->assertMatchesRegularExpression('/"users"\."id" = \?|"id" = \?/', $sql, 'читается только строка по id: ' . $sql);
                    $this->assertStringNotContainsString('email', strtolower($sql), 'email аккаунтов не запрашивается: ' . $sql);
                }
            }
            foreach ($result['userBindings'] as $binding) {
                $this->assertNotContains($binding, [self::KNOWN_EMAIL, self::UNKNOWN_EMAIL], 'запрос к users не получает отправленный email');
            }
        }

        // Единственное место, где email встречается как значение, — строка самого запроса.
        $this->assertSame(self::KNOWN_EMAIL, $this->pending($first)->client_email);
        $this->assertSame(self::UNKNOWN_EMAIL, $this->pending($second)->client_email);
        $this->assertSame(1, $this->events('client_verification.requested')->where('subject_id', $this->pending($first)->id)->count());
        $this->assertNull($first->refresh()->client_user_id);
        $this->assertNull($second->refresh()->client_user_id);
    }

    public function test_repeat_submission_returns_the_open_request_without_a_second_row_or_event(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->a, $inquiry)->assertSessionHas('success');
        $this->submit($this->a, $inquiry)->assertSessionHas('success');
        $this->submit($this->a, $inquiry, $this->form(self::KNOWN_EMAIL, ['client_name' => 'Другое имя']))->assertSessionHas('success');

        $this->assertSame(1, ClientVerificationRequest::query()->count());
        $this->assertCount(1, $this->events('client_verification.requested'));
    }

    public function test_submissions_share_the_existing_rate_limit_and_pending_cap(): void
    {
        for ($i = 0; $i < ClientVerificationService::MAX_SUBMISSIONS_PER_MINUTE; $i++) {
            $this->submit($this->a, $this->mine())->assertSessionHasNoErrors();
        }

        $blocked = $this->mine();
        $this->submit($this->a, $blocked)->assertSessionHasErrors('client_name');
        $this->assertSame(0, ClientVerificationRequest::query()->where('agency_inquiry_id', $blocked->id)->count());

        // Потолок открытых запросов один на менеджера и для остальных способов подачи.
        RateLimiter::clear('client-verification:' . $this->a->id);
        $service = app(ClientVerificationService::class);
        for ($i = ClientVerificationService::MAX_SUBMISSIONS_PER_MINUTE; $i < ClientVerificationService::MAX_PENDING_PER_MANAGER; $i++) {
            RateLimiter::clear('client-verification:' . $this->a->id);
            $service->submit($this->a, "Клиент формы {$i}", null, null);
        }
        RateLimiter::clear('client-verification:' . $this->a->id);
        $this->submit($this->a, $blocked)->assertSessionHasErrors('client_name');
    }

    public function test_only_the_responsible_manager_may_submit(): void
    {
        $inquiry = $this->mine();
        $tourist = $this->userWith('tourist');

        $this->submit($this->admin, $inquiry)->assertForbidden();
        $this->submit($tourist, $inquiry)->assertForbidden();
        $this->submit($this->b, $inquiry)->assertNotFound();
        auth()->logout();
        $this->post($this->url($inquiry), $this->form())->assertRedirect(route('login'));

        $unassigned = $this->mine();
        DB::table('inquiries')->where('id', $unassigned->id)->update(['assigned_to' => null]);
        $this->submit($this->a, $unassigned)->assertNotFound();

        [, $linked] = $this->linkedPair($this->a, $this->a);
        $this->submit($this->a, $linked)->assertNotFound();

        $this->assertSame(0, ClientVerificationRequest::query()->count());
        $this->assertCount(0, $this->events('client_verification.requested'));
    }

    public function test_closed_or_already_linked_inquiries_are_refused_without_a_row(): void
    {
        $closed = $this->mine();
        DB::table('inquiries')->where('id', $closed->id)->update(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()]);
        $this->submit($this->a, $closed)->assertSessionHasErrors();

        $linked = $this->independentInquiry($this->a, null, $this->tourist);
        $this->submit($this->a, $linked)->assertSessionHasErrors();

        $this->assertSame(0, ClientVerificationRequest::query()->count());
    }

    public function test_forged_fields_are_rejected_wholesale(): void
    {
        $inquiry = $this->mine();
        $other = $this->mine($this->b);

        foreach ([
            ['agency_inquiry_id' => $other->id],
            ['requested_by' => $this->b->id],
            ['context_type' => 'booking_form'],
            ['status' => 'approved'],
            ['resolved_user_id' => $this->tourist->id],
            ['client_user_id' => $this->tourist->id],
            ['inquiry_id' => $other->id],
        ] as $forged) {
            $this->submit($this->a, $inquiry, $this->form() + $forged)->assertSessionHasErrors('client');
        }

        $this->assertSame(0, ClientVerificationRequest::query()->count());
        $this->assertNull($inquiry->refresh()->client_user_id);
    }

    public function test_input_is_validated_without_touching_accounts(): void
    {
        $inquiry = $this->mine();

        $this->submit($this->a, $inquiry, $this->form('not-an-email'))->assertSessionHasErrors('client_email');
        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL, ['client_name' => '']))->assertSessionHasErrors('client_name');
        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL, ['client_phone' => str_repeat('1', 21)]))->assertSessionHasErrors('client_phone');
        $this->assertSame(0, ClientVerificationRequest::query()->count());
    }

    public function test_audit_failure_rolls_back_the_request(): void
    {
        $inquiry = $this->mine();
        $this->failAudit();

        $this->expectException(RuntimeException::class);
        try {
            app(ClientVerificationService::class)->submitForInquiry($this->a, $inquiry, 'Клиент', null, null);
        } finally {
            $this->assertSame(0, ClientVerificationRequest::query()->count());
        }
    }

    // ---- Admin resolution --------------------------------------------------------------------

    public function test_admin_links_an_existing_tourist_and_visibility_follows_the_assignment(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::KNOWN_EMAIL));
        $request = $this->pending($inquiry);

        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->tourist->id)->exists());

        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $this->tourist->id])
            ->assertRedirect(route('cabinet.admin.client-requests.show', $request))
            ->assertSessionHas('success');

        $inquiry->refresh();
        $request->refresh();
        $this->assertSame($this->tourist->id, $inquiry->client_user_id);
        $this->assertSame($this->admin->id, $inquiry->client_linked_by);
        $this->assertNotNull($inquiry->client_linked_at);
        $this->assertSame(ClientVerificationRequest::STATUS_APPROVED, $request->status);
        $this->assertSame($this->tourist->id, (int) $request->resolved_user_id);
        $this->assertNull($request->access_expires_at, 'контекст обращения не даёт 30-дневного окна');

        $linked = $this->events('inquiry.client_linked')->sole();
        $this->assertSame($inquiry->id, (int) $linked->subject_id);
        $this->assertSame('inquiry', $linked->subject_type);
        $this->assertSame($this->admin->id, (int) $linked->actor_id);
        $this->assertSame(['client_id' => $this->tourist->id, 'via' => 'verification', 'verification_id' => $request->id], $linked->metadata);

        $resolved = $this->events('client_verification.linked')->sole();
        $this->assertSame([
            'manager_id' => $this->a->id, 'client_id' => $this->tourist->id, 'inquiry_id' => null,
            'linked_to_inquiry' => true, 'agency_inquiry_id' => $inquiry->id,
        ], $resolved->metadata);

        // Ответственный менеджер видит клиента через обращение; посторонний — нет.
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->tourist->id)->exists());
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->b)->whereKey($this->tourist->id)->exists());

        // Передача: прежний менеджер теряет клиента сразу, новый получает.
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ])->assertSessionHasNoErrors();
        $this->assertFalse(ClientVisibility::touristsVisibleTo($this->a)->whereKey($this->tourist->id)->exists(), 'нет остаточного окна доступа');
        $this->assertTrue(ClientVisibility::touristsVisibleTo($this->b)->whereKey($this->tourist->id)->exists());
        Mail::assertNothingSent();
    }

    public function test_admin_approves_a_new_tourist_and_links_it_without_sending_anything(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL));
        $request = $this->pending($inquiry);
        $users = User::query()->count();

        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHas('success');

        $created = User::query()->where('email', self::UNKNOWN_EMAIL)->firstOrFail();
        $this->assertSame($users + 1, User::query()->count());
        $this->assertTrue($created->hasRole(User::ROLE_TOURIST));
        $this->assertNull($created->email_verified_at, 'email не считается подтверждённым');
        $this->assertSame($created->id, $inquiry->refresh()->client_user_id);
        $this->assertSame(ClientVerificationRequest::RESOLUTION_CREATED_NEW, $request->refresh()->resolution);
        $this->assertSame('created_new', $request->resolution);
        $this->assertCount(1, $this->events('client_verification.created'));
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_new_tourist_with_a_registered_email_is_refused_to_the_admin_and_nothing_is_written(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::KNOWN_EMAIL));
        $request = $this->pending($inquiry);
        $users = User::query()->count();

        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors('workflow');

        $this->assertSame($users, User::query()->count());
        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertTrue($request->refresh()->isPending());
    }

    public function test_rejection_keeps_the_inquiry_unlinked_and_allows_a_new_request(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry);
        $request = $this->pending($inquiry);

        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.reject', $request), ['note' => 'Данные клиента не подтверждены'])
            ->assertSessionHas('success');

        $this->assertNull($inquiry->refresh()->client_user_id);
        $event = $this->events('client_verification.rejected')->sole();
        $this->assertSame($inquiry->id, $event->metadata['agency_inquiry_id']);
        $this->assertSame(ClientVerificationRequest::STATUS_REJECTED, $request->refresh()->status);

        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL, ['client_name' => 'Повторный запрос']))->assertSessionHas('success');
        $this->assertSame(2, ClientVerificationRequest::query()->count());
    }

    public function test_stale_resolution_after_reassignment_is_refused_and_writes_nothing(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL));
        $request = $this->pending($inquiry);

        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ])->assertSessionHasNoErrors();

        $users = User::query()->count();
        $this->actingAs($this->admin)->get(route('cabinet.admin.client-requests.show', $request))->assertOk()->assertSee('Запрос потерял актуальность');
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $this->tourist->id])->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors();

        $this->assertSame($users, User::query()->count(), 'устаревшее решение не создаёт аккаунт');
        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertTrue($request->refresh()->isPending());
        $this->assertCount(0, $this->events('inquiry.client_linked'));

        // Устаревший запрос можно отклонить.
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.reject', $request), ['note' => 'Обращение передано другому'])->assertSessionHas('success');
    }

    public function test_stale_resolution_when_a_client_was_linked_meanwhile_does_not_overwrite(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::KNOWN_EMAIL));
        $request = $this->pending($inquiry);
        $other = $this->userWith('tourist');

        DB::table('inquiries')->where('id', $inquiry->id)->update(['client_user_id' => $other->id]);

        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $this->tourist->id])->assertSessionHasErrors();

        $this->assertSame($other->id, $inquiry->refresh()->client_user_id);
        $this->assertTrue($request->refresh()->isPending());
    }

    public function test_stale_resolution_for_closed_inquiry_or_departed_manager_is_refused(): void
    {
        $closed = $this->mine();
        $this->submit($this->a, $closed);
        $closedRequest = $this->pending($closed);
        DB::table('inquiries')->where('id', $closed->id)->update(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()]);
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $closedRequest), ['user_id' => $this->tourist->id])->assertSessionHasErrors();

        $open = $this->mine($this->b);
        $this->submit($this->b, $open);
        $openRequest = $this->pending($open);
        $this->b->update(['is_active' => false]);
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $openRequest), ['user_id' => $this->tourist->id])->assertSessionHasErrors();

        $this->assertNull($closed->refresh()->client_user_id);
        $this->assertNull($open->refresh()->client_user_id);
    }

    public function test_request_resolved_twice_is_applied_once(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry);
        $request = $this->pending($inquiry);

        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $this->tourist->id])->assertSessionHas('success');
        $other = $this->userWith('tourist');
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $other->id])->assertSessionHasErrors();

        $this->assertSame($this->tourist->id, $inquiry->refresh()->client_user_id);
        $this->assertCount(1, $this->events('inquiry.client_linked'));
    }

    public function test_resolution_failure_in_audit_rolls_everything_back(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL));
        $request = $this->pending($inquiry);
        $users = User::query()->count();

        $this->failAudit();
        $service = app(ClientVerificationService::class);

        foreach ([
            fn () => $service->linkExisting($request, $this->admin, $this->tourist->id),
            fn () => $service->approveNew($request, $this->admin),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Сбой аудита должен прерывать решение');
            } catch (RuntimeException $e) {
                $this->assertSame('audit store unavailable', $e->getMessage());
            }
        }

        $this->assertNull($inquiry->refresh()->client_user_id);
        $this->assertTrue($request->refresh()->isPending());
        $this->assertSame($users, User::query()->count(), 'созданный аккаунт откатывается вместе с решением');
    }

    public function test_only_admin_resolves_and_manager_cannot(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry);
        $request = $this->pending($inquiry);

        $this->actingAs($this->a)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $this->tourist->id])->assertForbidden();
        $this->actingAs($this->a)->get(route('cabinet.admin.client-requests.show', $request))->assertForbidden();
        $this->expectException(\Illuminate\Auth\Access\AuthorizationException::class);
        app(ClientVerificationService::class)->linkExisting($request, $this->a, $this->tourist->id);
    }

    // ---- views -------------------------------------------------------------------------------

    public function test_admin_sees_the_context_and_the_manager_sees_only_neutral_state(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::KNOWN_EMAIL));
        $request = $this->pending($inquiry);

        $this->actingAs($this->admin)->get(route('cabinet.admin.client-requests.show', $request))
            ->assertOk()
            ->assertSee('Обращение клиента №' . $inquiry->id)
            ->assertSee(route('cabinet.manager.agency-inquiries.show', $inquiry), false)
            ->assertDontSee('Запрос потерял актуальность');
        $this->actingAs($this->admin)->get(route('cabinet.admin.client-requests'))->assertOk()->assertSee('Обращение клиента №' . $inquiry->id);

        $html = $this->actingAs($this->a)->get(route('cabinet.manager.client-requests'))->assertOk()->getContent();
        $this->assertStringContainsString('На проверке у администратора', $html);
        $this->assertStringContainsString('Клиент Запроса', $html);
        $this->assertStringNotContainsString('Существующий Турист', $html);

        // Посторонний менеджер строку не видит.
        $this->actingAs($this->b)->get(route('cabinet.manager.client-requests'))->assertOk()->assertDontSee('Клиент Запроса');

        // После передачи данные клиента скрыты, строка остаётся в истории.
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ]);
        $masked = $this->actingAs($this->a)->get(route('cabinet.manager.client-requests'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Клиент Запроса', $masked);
        $this->assertStringNotContainsString(self::KNOWN_EMAIL, $masked);
        $this->assertStringContainsString('Обращение передано', $masked);
        $this->assertStringNotContainsString('открыть обращение', $masked);
    }

    public function test_verification_audit_events_carry_no_client_personal_data(): void
    {
        $inquiry = $this->mine();
        $this->submit($this->a, $inquiry, $this->form(self::UNKNOWN_EMAIL));
        $request = $this->pending($inquiry);
        $this->actingAs($this->admin)->post(route('cabinet.admin.client-requests.create', $request));

        $stored = json_encode(DB::table('audit_events')->get()->all(), JSON_UNESCAPED_UNICODE);
        foreach (['Клиент Запроса', self::UNKNOWN_EMAIL, '900 111', '+7 900'] as $secret) {
            $this->assertStringNotContainsString($secret, $stored, "в аудите: {$secret}");
        }
    }

    public function test_existing_contexts_keep_their_signatures_and_audit_payloads(): void
    {
        $source = $this->inquiryFor($this->a);
        $service = app(ClientVerificationService::class);

        $request = $service->submit($this->a, 'Клиент E5', 'e5.client@example.test', null, $source);
        $this->assertSame(ClientVerificationRequest::CONTEXT_INCOMING_INQUIRY, $request->context_type);
        $this->assertNull($request->agency_inquiry_id);
        $this->assertSame(
            ['context' => 'incoming_inquiry', 'inquiry_id' => $source->id, 'has_email' => true],
            $this->events('client_verification.requested')->sole()->metadata,
            'ключи аудита E5 не изменились'
        );

        $this->assertSame(1, IncomingInquiry::query()->count());
        $this->expectException(ClientVerificationException::class);
        $service->submit($this->admin, 'Админ', null, null);
    }
}
