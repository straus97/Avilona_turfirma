<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\ClientVerificationException;
use App\Services\Accounts\ClientVerificationService;
use App\Services\Accounts\ClientVisibility;
use App\Services\Accounts\StaffClientAccounts;
use App\Services\Audit\AuditRecorder;
use App\Services\IncomingInquiries\IncomingInquiryWorkflow;
use App\Services\IncomingInquiries\IncomingInquiryWorkflowException;
use App\Support\Audit\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I7 — приватная проверка клиента (решение владельца по I3-F3, вариант C).
 * Только синтетические данные; письма и уведомления не отправляются.
 */
class ClientVerificationWorkflowTest extends TestCase
{
    use BuildsAoaI2bFixtures;
    use RefreshDatabase;

    private const NEW_EMAIL = 'fresh.person@example.test';

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();

        foreach ([Role::ADMIN, Role::MANAGER, Role::TOURIST] as $name) {
            Role::query()->firstOrCreate(['name' => $name], ['description' => Role::availableRoles()[$name] ?? $name]);
        }
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    private function bookingPayload(array $overrides = []): array
    {
        return array_merge([
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
        ], $overrides);
    }

    private function submit(User $manager, string $email, string $name = 'Запрошенный Клиент', array $extra = []): TestResponse
    {
        return $this->actingAs($manager)->post(route('cabinet.manager.client-requests.store'), array_merge([
            'client_name' => $name,
            'client_email' => $email,
        ], $extra));
    }

    /** Всё, что менеджер может наблюдать в ответе, кроме собственного ввода. */
    private function fingerprint(TestResponse $response): array
    {
        $errors = session('errors');

        return [
            'status' => $response->getStatusCode(),
            'location' => preg_replace('#/\d+#', '/N', (string) $response->headers->get('Location')),
            'success' => session('success'),
            'errors' => $errors ? $errors->all() : [],
            'error_keys' => $errors ? array_keys($errors->messages()) : [],
        ];
    }

    /** Семь вариантов email из требования: нового, чужого, своего, администратора, менеджера, неактивного и в другом регистре. */
    private function emailCases(User $mine, User $other): array
    {
        $this->userWith('tourist', ['name' => 'Чужой Клиент', 'email' => 'foreign.client@example.test']);
        $this->bookingOf(User::where('email', 'foreign.client@example.test')->first(), $other);

        $own = $this->userWith('tourist', ['name' => 'Свой Клиент', 'email' => 'own.client@example.test']);
        $this->bookingOf($own, $mine);

        $this->userWith('admin', ['email' => 'boss@example.test']);
        $this->userWith('manager', ['email' => 'colleague@example.test']);
        $this->userWith('tourist', ['email' => 'inactive.client@example.test', 'is_active' => false]);

        return [
            'new' => self::NEW_EMAIL,
            'foreign' => 'foreign.client@example.test',
            'own' => 'own.client@example.test',
            'admin' => 'boss@example.test',
            'manager' => 'colleague@example.test',
            'inactive' => 'inactive.client@example.test',
            'cased' => 'Foreign.Client@EXAMPLE.test',
        ];
    }

    /** Освобождает адреса прогона, чтобы следующий вариант цикла начинал с чистого справочника (SQLite и MySQL). */
    private function retireEmails(array $emails): void
    {
        foreach (User::query()->whereIn(DB::raw('LOWER(email)'), array_map('strtolower', array_values($emails)))->get() as $user) {
            DB::table('users')->where('id', $user->id)->update(['email' => 'gone' . $user->id . '@gone.test']);
        }
    }

    private function pendingRequest(User $manager, array $attributes = [], ?IncomingInquiry $inquiry = null): ClientVerificationRequest
    {
        return app(ClientVerificationService::class)->submit(
            $manager,
            $attributes['name'] ?? 'Запрошенный Клиент',
            $attributes['email'] ?? self::NEW_EMAIL,
            $attributes['phone'] ?? null,
            $inquiry,
        );
    }

    // ------------------------------------------------------------------
    // I3-F3: the original disclosure through the legacy creation endpoints
    // ------------------------------------------------------------------

    public function test_manager_cannot_tell_existing_from_new_email_through_the_booking_form(): void
    {
        // На прежней реализации новый email создавал клиента и заявку (redirect на заявку), а
        // существующий — возвращал ошибку: один бит «аккаунт существует». Теперь отпечатки равны.
        $prints = [];

        foreach (['new', 'foreign', 'own', 'admin', 'manager', 'inactive', 'cased'] as $case) {
            $manager = $this->userWith('manager');
            $other = $this->userWith('manager');
            $emails = $this->emailCases($manager, $other);

            $bookingsBefore = Booking::count();
            $usersBefore = User::count();

            $response = $this->actingAs($manager)->post(route('bookings.store'), $this->bookingPayload([
                'is_new_client' => '1',
                'client_name' => 'Дубль Имени',
                'client_email' => $emails[$case],
            ]));

            $prints[$case] = $this->fingerprint($response);
            $this->assertSame($bookingsBefore, Booking::count(), "Заявка не создаётся ({$case})");
            $this->assertSame($usersBefore, User::count(), "Аккаунт не создаётся ({$case})");

            // Объекты следующего прогона не должны пересекаться по email.
            $this->retireEmails($emails);
        }

        foreach ($prints as $case => $print) {
            $this->assertSame($prints['new'], $print, "Ответ для случая {$case} отличается от ответа для нового email");
        }
    }

    public function test_manager_cannot_tell_existing_from_new_email_through_the_inquiry_form(): void
    {
        $prints = [];

        foreach (['new', 'foreign', 'own', 'admin', 'manager', 'inactive', 'cased'] as $case) {
            $manager = $this->userWith('manager');
            $other = $this->userWith('manager');
            $emails = $this->emailCases($manager, $other);
            $inquiry = $this->inquiryFor($manager);

            $response = $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), [
                'client_name' => 'Дубль Имени',
                'client_email' => $emails[$case],
            ]);

            $prints[$case] = $this->fingerprint($response);
            $this->assertNull($inquiry->fresh()->client_user_id, "Обращение не должно получить клиента ({$case})");

            $this->retireEmails($emails);
        }

        foreach ($prints as $case => $print) {
            $this->assertSame($prints['new'], $print, "Ответ для случая {$case} отличается от ответа для нового email");
        }
    }

    public function test_dedicated_request_endpoint_gives_one_response_for_all_email_cases_html_and_json(): void
    {
        $prints = [];
        $jsonPrints = [];

        foreach (['new', 'foreign', 'own', 'admin', 'manager', 'inactive', 'cased'] as $case) {
            $manager = $this->userWith('manager');
            $other = $this->userWith('manager');
            $emails = $this->emailCases($manager, $other);

            $prints[$case] = $this->fingerprint($this->submit($manager, $emails[$case], 'Дубль Имени'));
            $this->assertSame(ClientVerificationService::NEUTRAL_MESSAGE, $prints[$case]['success']);

            $json = $this->actingAs($manager)->postJson(route('cabinet.manager.client-requests.store'), [
                'client_name' => 'Дубль Имени Два',
                'client_email' => $emails[$case],
            ]);
            $jsonPrints[$case] = [$json->getStatusCode(), $json->headers->get('Content-Type') === null ? '' : explode(';', $json->headers->get('Content-Type'))[0], strlen((string) $json->getContent()) > 0 ? 'body' : 'empty'];

            $this->retireEmails($emails);
        }

        foreach ($prints as $case => $print) {
            $this->assertSame($prints['new'], $print, "HTML: случай {$case}");
            $this->assertSame($jsonPrints['new'], $jsonPrints[$case], "JSON: случай {$case}");
        }
    }

    public function test_request_for_every_email_case_is_stored_identically_and_never_touches_accounts(): void
    {
        $manager = $this->userWith('manager');
        $other = $this->userWith('manager');
        $emails = $this->emailCases($manager, $other);
        $usersBefore = User::query()->count();
        $snapshot = User::query()->orderBy('id')->get()->map->getAttributes()->all();

        $statements = [];
        DB::listen(function ($query) use (&$statements): void {
            $statements[] = strtolower($query->sql);
        });

        foreach ($emails as $case => $email) {
            $this->submit($manager, $email, 'Имя ' . $case)->assertSessionHas('success', ClientVerificationService::NEUTRAL_MESSAGE);
        }

        // Отправка не обращается к учётным записям по email: результат и время не зависят от существования аккаунта.
        foreach ($statements as $sql) {
            $this->assertFalse(str_contains($sql, 'from "users"') && str_contains($sql, 'email'), 'Запрос к users по email: ' . $sql);
        }

        $this->assertSame($usersBefore, User::query()->count());
        $this->assertEquals($snapshot, User::query()->orderBy('id')->get()->map->getAttributes()->all());
        // «Foreign.Client@EXAMPLE.test» и «foreign.client@example.test» — один и тот же запрос (без учёта регистра).
        $this->assertSame(6, ClientVerificationRequest::query()->where('status', 'pending')->count());
    }

    // ------------------------------------------------------------------
    // Manager flow
    // ------------------------------------------------------------------

    public function test_manager_sees_a_pending_state_only_for_own_requests_and_nothing_about_accounts(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $this->userWith('tourist', ['name' => 'Тайный Существующий', 'email' => 'exists.already@example.test']);

        $this->submit($a, 'exists.already@example.test', 'Мой Запрос');
        $this->submit($b, 'someone.else@example.test', 'Чужой Запрос');

        $page = $this->actingAs($a)->get(route('cabinet.manager.client-requests'))->assertOk();
        $page->assertSee('Мой Запрос')->assertSee('На проверке у администратора');
        $page->assertDontSee('Чужой Запрос')->assertDontSee('Тайный Существующий');
        $page->assertDontSee('уже существует')->assertDontSee('Привязан существующий')->assertDontSee('Создан новый');
    }

    public function test_booking_form_keeps_booking_input_and_creates_no_booking_or_user(): void
    {
        $manager = $this->userWith('manager');
        $users = User::count();

        $response = $this->actingAs($manager)->post(route('bookings.store'), $this->bookingPayload([
            'is_new_client' => '1',
            'client_name' => 'Новый Клиент',
            'client_email' => self::NEW_EMAIL,
            'notes' => 'ВАЖНАЯ-ЗАМЕТКА',
        ]));

        $response->assertRedirect(route('bookings.create'));
        $response->assertSessionHas('success');
        $this->assertSame('Москва', session()->getOldInput('departure_city'));
        $this->assertSame('ВАЖНАЯ-ЗАМЕТКА', session()->getOldInput('notes'));
        $this->assertNull(session()->getOldInput('client_email'), 'Поля клиента из старого ввода убраны');
        $this->assertSame(0, Booking::count());
        $this->assertSame($users, User::count());
        $this->assertSame(1, ClientVerificationRequest::count());
    }

    public function test_booking_form_page_offers_the_request_flow_to_managers_and_direct_creation_to_admins(): void
    {
        $manager = $this->userWith('manager');
        $admin = $this->userWith('admin');

        $this->actingAs($manager)->get(route('bookings.create'))->assertOk()
            ->assertSee('отправить запрос на проверку администратору')->assertSee('Мои запросы на проверку клиентов');
        $this->actingAs($admin)->get(route('bookings.create'))->assertOk()->assertSee('Новый клиент')->assertDontSee('отправить запрос на проверку администратору');
    }

    public function test_inquiry_request_records_the_context_and_shows_the_state_on_the_inquiry(): void
    {
        $manager = $this->userWith('manager');
        $inquiry = $this->inquiryFor($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), ['client_name' => 'Заявитель', 'client_email' => self::NEW_EMAIL])
            ->assertRedirect(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertSessionHas('success', ClientVerificationService::NEUTRAL_MESSAGE);

        $request = ClientVerificationRequest::query()->sole();
        $this->assertSame($inquiry->id, $request->incoming_inquiry_id);
        $this->assertSame(ClientVerificationRequest::CONTEXT_INCOMING_INQUIRY, $request->context_type);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()
            ->assertSee('На проверке у администратора')->assertSee('Отправить запрос на проверку клиента');
    }

    public function test_inquiry_request_requires_a_visible_open_inquiry_owned_by_the_manager(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $payload = ['client_name' => 'Заявитель', 'client_email' => self::NEW_EMAIL];

        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.create', $this->inquiryFor($b)), $payload)->assertNotFound();
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.create', $this->inquiryFor(null)), $payload)->assertNotFound();

        $closed = $this->inquiryFor($a, null, ['workflow_state' => IncomingInquiry::WORKFLOW_CLOSED]);
        $this->actingAs($a)->post(route('cabinet.manager.inquiries.client.create', $closed), $payload)->assertSessionHasErrors();

        $this->assertSame(0, ClientVerificationRequest::count());
    }

    public function test_manager_can_not_create_a_tourist_directly_even_through_the_workflow_service(): void
    {
        $manager = $this->userWith('manager');
        $inquiry = $this->inquiryFor($manager);

        try {
            app(IncomingInquiryWorkflow::class)->createClient($inquiry, $manager, 'Прямое Создание', 'direct@example.test', null);
            $this->fail('Менеджер не должен создавать аккаунт напрямую');
        } catch (IncomingInquiryWorkflowException $e) {
            $this->assertSame('client_email', $e->field);
        }

        $this->assertNull(User::where('email', 'direct@example.test')->first());
    }

    public function test_input_validation_is_independent_of_account_existence(): void
    {
        $manager = $this->userWith('manager');

        $this->submit($manager, 'not-an-email')->assertSessionHasErrors('client_email');
        $this->actingAs($manager)->post(route('cabinet.manager.client-requests.store'), ['client_email' => 'ok@example.test'])->assertSessionHasErrors('client_name');
        $this->actingAs($manager)->post(route('cabinet.manager.client-requests.store'), ['client_name' => str_repeat('я', 256)])->assertSessionHasErrors('client_name');
        $this->actingAs($manager)->post(route('cabinet.manager.client-requests.store'), ['client_name' => 'Ок', 'client_phone' => str_repeat('1', 21)])->assertSessionHasErrors('client_phone');
        $this->assertSame(0, ClientVerificationRequest::count());
    }

    // ------------------------------------------------------------------
    // Duplicates, caps, rate limiting
    // ------------------------------------------------------------------

    public function test_duplicate_submission_is_idempotent_and_case_insensitive(): void
    {
        $manager = $this->userWith('manager');

        $first = $this->fingerprint($this->submit($manager, 'Dup.Person@Example.test', 'Дубль'));
        $second = $this->fingerprint($this->submit($manager, 'dup.person@example.test', 'Дубль'));

        $this->assertSame($first, $second);
        $this->assertSame(1, ClientVerificationRequest::count());
        $this->assertSame(1, AuditEvent::where('event_type', AuditEventType::ClientVerificationRequested->value)->count());
        $this->assertSame('dup.person@example.test', ClientVerificationRequest::sole()->client_email);
    }

    public function test_each_manager_has_an_independent_request_for_the_same_client(): void
    {
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');

        $this->submit($a, self::NEW_EMAIL);
        $this->submit($b, self::NEW_EMAIL);

        $this->assertSame(2, ClientVerificationRequest::count());
    }

    public function test_pending_requests_are_capped_per_manager_with_a_neutral_message(): void
    {
        $manager = $this->userWith('manager');

        for ($i = 1; $i <= ClientVerificationService::MAX_PENDING_PER_MANAGER; $i++) {
            (new ClientVerificationRequest())->forceFill([
                'requested_by' => $manager->id, 'context_type' => 'booking_form', 'client_name' => "Имя {$i}",
                'status' => 'pending', 'pending_key' => hash('sha256', "seed-{$i}"),
            ])->save();
        }

        $this->submit($manager, 'overflow@example.test', 'Лишний')->assertSessionHasErrors('client_name');
        $this->assertStringNotContainsString('overflow@example.test', implode(' ', session('errors')->all()));
        $this->assertSame(ClientVerificationService::MAX_PENDING_PER_MANAGER, ClientVerificationRequest::count());
    }

    public function test_submissions_are_rate_limited_per_manager(): void
    {
        $manager = $this->userWith('manager');
        $other = $this->userWith('manager');

        for ($i = 1; $i <= ClientVerificationService::MAX_SUBMISSIONS_PER_MINUTE; $i++) {
            $this->submit($manager, "person{$i}@example.test", "Человек {$i}")->assertSessionHas('success');
        }

        $this->submit($manager, 'person-late@example.test', 'Поздний')->assertSessionHasErrors('client_name');
        $this->assertSame(ClientVerificationService::MAX_SUBMISSIONS_PER_MINUTE, ClientVerificationRequest::count());

        $this->submit($other, 'person-late@example.test', 'Поздний')->assertSessionHas('success');
    }

    // ------------------------------------------------------------------
    // Access control
    // ------------------------------------------------------------------

    public function test_only_the_admin_reaches_review_routes_and_only_managers_reach_the_submit_routes(): void
    {
        $manager = $this->userWith('manager');
        $admin = $this->userWith('admin');
        $tourist = $this->userWith('tourist');
        $request = $this->pendingRequest($manager);

        $adminRoutes = [
            ['get', route('cabinet.admin.client-requests')],
            ['get', route('cabinet.admin.client-requests.show', $request)],
            ['post', route('cabinet.admin.client-requests.link', $request), ['user_id' => $tourist->id]],
            ['post', route('cabinet.admin.client-requests.create', $request)],
            ['post', route('cabinet.admin.client-requests.reject', $request), ['note' => 'Причина отказа']],
        ];

        foreach ($adminRoutes as $route) {
            [$method, $url, $data] = $route + [2 => []];
            $this->actingAs($manager)->{$method}($url, $data)->assertForbidden();
            $this->actingAs($tourist)->{$method}($url, $data)->assertForbidden();
            auth()->logout();
            $this->{$method}($url, $data)->assertRedirect(route('login'));
        }

        $this->assertTrue($request->fresh()->isPending());
        $this->assertSame(1, ClientVerificationRequest::count());

        foreach ([['get', route('cabinet.manager.client-requests')], ['post', route('cabinet.manager.client-requests.store')]] as [$method, $url]) {
            $this->actingAs($tourist)->{$method}($url, ['client_name' => 'Х'])->assertForbidden();
            $this->actingAs($admin)->{$method}($url, ['client_name' => 'Х'])->assertForbidden();
            auth()->logout();
            $this->{$method}($url, ['client_name' => 'Х'])->assertRedirect(route('login'));
        }

        $this->assertSame(1, ClientVerificationRequest::count());
    }

    public function test_admin_list_counts_and_detail_show_pending_requests(): void
    {
        $manager = $this->userWith('manager');
        $admin = $this->userWith('admin');
        $this->pendingRequest($manager, ['name' => 'Видимый Админу']);

        $this->actingAs($admin)->get(route('cabinet.admin.client-requests'))->assertOk()->assertSee('Видимый Админу');
        $this->actingAs($admin)->get(route('cabinet.admin.dashboard'))->assertOk()->assertSee('Проверка клиентов');
        $this->actingAs($admin)->get(route('cabinet.admin.client-requests.show', ClientVerificationRequest::sole()))->assertOk()
            ->assertSee('Видимый Админу')->assertSee(self::NEW_EMAIL);
    }

    // ------------------------------------------------------------------
    // Admin resolution
    // ------------------------------------------------------------------

    public function test_admin_links_an_existing_tourist_without_exposing_foreign_records(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $shared = $this->userWith('tourist', ['name' => 'Общий Клиент', 'email' => self::NEW_EMAIL]);
        $bBooking = $this->bookingOf($shared, $b, Booking::STATUS_PROGRESS, ['notes' => 'ПРИВАТНАЯ-ЗАМЕТКА-Б']);
        $usersBefore = User::count();

        $this->submit($a, self::NEW_EMAIL, 'Общий Клиент');
        $request = ClientVerificationRequest::sole();

        $this->assertNotContains($shared->id, ClientVisibility::touristsVisibleTo($a)->pluck('users.id')->all());

        $this->actingAs($admin)->get(route('cabinet.admin.client-requests.show', $request))->assertOk()->assertSee('совпадает email');
        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $shared->id])
            ->assertRedirect(route('cabinet.admin.client-requests.show', $request))->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('approved', $request->status);
        $this->assertSame(ClientVerificationRequest::RESOLUTION_LINKED_EXISTING, $request->resolution);
        $this->assertSame($shared->id, $request->resolved_user_id);
        $this->assertNull($request->pending_key);
        $this->assertSame($usersBefore, User::count(), 'Дубликат аккаунта не создан');

        $this->assertContains($shared->id, ClientVisibility::touristsVisibleTo($a)->pluck('users.id')->all());
        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertOk()->assertSee('Общий Клиент');
        $this->actingAs($a)->get(route('bookings.create'))->assertOk()->assertSee('Общий Клиент');

        // …но не заявка, переписка и заметки менеджера B.
        $this->actingAs($a)->get(route('bookings.show', $bBooking))->assertForbidden();
        $this->actingAs($a)->get(route('cabinet.manager.clients'))->assertDontSee('ПРИВАТНАЯ-ЗАМЕТКА-Б');
        $this->assertSame(0, Booking::where('manager_id', $a->id)->count());

        $event = AuditEvent::where('event_type', AuditEventType::ClientVerificationLinked->value)->sole();
        $this->assertSame($admin->id, $event->actor_id);
        $this->assertSame($request->id, $event->subject_id);
    }

    public function test_admin_approves_a_new_tourist_who_becomes_visible_to_the_requesting_manager(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');

        $this->submit($a, 'Brand.New@Example.test', 'Совсем Новый', ['client_phone' => '+7 900 111-22-33']);
        $request = ClientVerificationRequest::sole();

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHas('success');

        $client = User::query()->where('email', 'brand.new@example.test')->sole();
        $this->assertTrue($client->hasRole(Role::TOURIST));
        $this->assertTrue((bool) $client->is_active);
        $this->assertNull($client->email_verified_at);
        $this->assertNull($client->temp_password);
        $this->assertFalse((bool) $client->password_change_required);
        $this->assertNotSame('', $client->password);
        $this->assertFalse(Hash::check('password', $client->password));
        $this->assertSame('+7 900 111-22-33', $client->phone);

        $request->refresh();
        $this->assertSame(ClientVerificationRequest::RESOLUTION_CREATED_NEW, $request->resolution);
        $this->assertSame($client->id, $request->resolved_user_id);
        $this->assertTrue($request->access_expires_at->isFuture());

        $this->assertContains($client->id, ClientVisibility::touristsVisibleTo($a)->pluck('users.id')->all());
        $this->assertNotContains($client->id, ClientVisibility::touristsVisibleTo($b)->pluck('users.id')->all(), 'Другой менеджер клиента не видит');

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();

        $event = AuditEvent::where('event_type', AuditEventType::ClientVerificationCreated->value)->sole();
        $this->assertSame($client->id, $event->metadata['client_id']);
    }

    public function test_manager_sees_the_same_state_after_linking_and_after_creating(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $existing = $this->userWith('tourist', ['name' => 'Был В Системе', 'email' => 'was.here@example.test']);

        $linked = $this->pendingRequest($a, ['name' => 'Был В Системе', 'email' => 'was.here@example.test']);
        $created = $this->pendingRequest($b, ['name' => 'Появился Позже', 'email' => 'new.arrival@example.test']);
        app(ClientVerificationService::class)->linkExisting($linked, $admin, $existing->id);
        app(ClientVerificationService::class)->approveNew($created, $admin);

        $blocks = [];
        foreach ([$a, $b] as $i => $manager) {
            $page = $this->actingAs($manager)->get(route('cabinet.manager.client-requests'))->assertOk();
            $page->assertSee('Клиент подтверждён')->assertDontSee('Привязан существующий')->assertDontSee('Создан новый');

            $this->assertSame(1, preg_match('#<ul class="list-group list-group-flush" aria-label="Мои запросы[^>]*>.*?</ul>#s', $page->getContent(), $m));
            $block = preg_replace('/(Был В Системе|Появился Позже|was\.here@example\.test|new\.arrival@example\.test|\d{2}\.\d{2}\.\d{4}( \d{2}:\d{2})?)/u', '#', $m[0]);
            $blocks[$i] = preg_replace('/\s+/', ' ', $block);
        }

        $this->assertSame($blocks[0], $blocks[1], 'Состояние после привязки и после создания неотличимо');
    }

    public function test_approval_is_refused_when_the_email_belongs_to_any_other_account(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');

        foreach ([
            'tourist' => ['email' => 'taken.tourist@example.test'],
            'admin' => ['email' => 'taken.admin@example.test'],
            'manager' => ['email' => 'taken.manager@example.test'],
            'inactive' => ['email' => 'taken.inactive@example.test', 'is_active' => false],
        ] as $role => $attributes) {
            $this->userWith($role === 'inactive' ? 'tourist' : $role, $attributes);
            $request = $this->pendingRequest($manager, ['name' => "Кандидат {$role}", 'email' => strtoupper($attributes['email'])]);
            $usersBefore = User::count();

            $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors('workflow');

            $this->assertSame($usersBefore, User::count(), "Аккаунт не создан ({$role})");
            $this->assertTrue($request->fresh()->isPending(), "Запрос остался на проверке ({$role})");
        }

        $this->assertSame(0, AuditEvent::where('event_type', AuditEventType::ClientVerificationCreated->value)->count());
    }

    public function test_only_active_tourists_can_be_linked(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $request = $this->pendingRequest($manager);

        foreach ([
            $this->userWith('admin'),
            $this->userWith('manager'),
            $this->userWith('tourist', ['is_active' => false]),
        ] as $notEligible) {
            $this->actingAs($admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $notEligible->id])->assertSessionHasErrors('user_id');
        }

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => 999999])->assertSessionHasErrors('user_id');
        $this->assertTrue($request->fresh()->isPending());
        $this->assertSame([], ClientVisibility::touristsVisibleTo($manager)->pluck('users.id')->all());
    }

    public function test_candidate_panel_flags_non_tourist_matches_for_the_admin_only(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $this->userWith('manager', ['name' => 'Коллега Совпадение', 'email' => self::NEW_EMAIL]);
        $request = $this->pendingRequest($manager);

        $this->actingAs($admin)->get(route('cabinet.admin.client-requests.show', $request))->assertOk()
            ->assertSee('Коллега Совпадение')->assertSee('нельзя привязать')
            ->assertDontSee('Привязать клиента Коллега Совпадение к менеджеру', false);
    }

    public function test_repeated_resolution_is_refused_and_writes_nothing_more(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist');
        $request = $this->pendingRequest($manager);

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHas('success');
        $users = User::count();
        $events = AuditEvent::count();

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors('workflow');
        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $tourist->id])->assertSessionHasErrors('workflow');
        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.reject', $request), ['note' => 'Поздний отказ'])->assertSessionHasErrors('workflow');

        $this->assertSame($users, User::count());
        $this->assertSame($events, AuditEvent::count());
        $this->assertSame('approved', $request->fresh()->status);
    }

    public function test_stale_concurrent_approval_loses_cleanly(): void
    {
        $admin1 = $this->userWith('admin');
        $admin2 = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $request = $this->pendingRequest($manager);

        // Второй администратор держит устаревший объект, в памяти запрос ещё «на проверке».
        $stale = ClientVerificationRequest::findOrFail($request->id);
        $service = app(ClientVerificationService::class);

        $service->approveNew($request, $admin1);

        try {
            $service->approveNew($stale, $admin2);
            $this->fail('Повторное одобрение должно быть отклонено');
        } catch (ClientVerificationException) {
            // ожидаемо
        }

        $this->assertSame(1, User::query()->where('email', self::NEW_EMAIL)->count());
        $this->assertSame(1, AuditEvent::where('event_type', AuditEventType::ClientVerificationCreated->value)->count());
        $this->assertSame($admin1->id, $request->fresh()->resolved_by);
    }

    public function test_unique_email_race_inside_the_transaction_leaves_nothing_behind(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $request = $this->pendingRequest($manager);
        $this->userWith('tourist', ['email' => self::NEW_EMAIL]);

        // Проверка существования «проиграла» гонку (аккаунт появился после неё): последнюю линию держит UNIQUE.
        $this->app->bind(StaffClientAccounts::class, fn () => new class extends StaffClientAccounts {
            public function emailExists(string $email): bool
            {
                return false;
            }
        });

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors('workflow');

        $this->assertSame(1, User::query()->where('email', self::NEW_EMAIL)->count(), 'Дубликата нет');
        $this->assertTrue($request->fresh()->isPending());
        $this->assertSame(0, AuditEvent::where('event_type', AuditEventType::ClientVerificationCreated->value)->count());
    }

    public function test_failed_audit_rolls_back_every_resolution(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist');
        $inquiry = $this->inquiryFor($manager);
        $createRequest = $this->pendingRequest($manager, ['email' => 'rollback.one@example.test']);
        $linkRequest = $this->pendingRequest($manager, ['email' => 'rollback.two@example.test'], $inquiry);
        $rejectRequest = $this->pendingRequest($manager, ['email' => 'rollback.three@example.test']);
        $users = User::count();

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, \App\Models\User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new \RuntimeException('audit down');
            }
        });

        $service = app(ClientVerificationService::class);

        foreach ([
            fn () => $service->approveNew($createRequest, $admin),
            fn () => $service->linkExisting($linkRequest, $admin, $tourist->id),
            fn () => $service->reject($rejectRequest, $admin, 'Не подходит клиент'),
        ] as $action) {
            try {
                $action();
                $this->fail('Сбой аудита должен прерывать решение');
            } catch (\RuntimeException $e) {
                $this->assertSame('audit down', $e->getMessage());
            }
        }

        $this->assertSame($users, User::count());
        $this->assertNull($inquiry->fresh()->client_user_id);
        $this->assertSame(3, ClientVerificationRequest::where('status', 'pending')->count());
        $this->assertSame([], ClientVisibility::touristsVisibleTo($manager)->pluck('users.id')->all());
    }

    // ------------------------------------------------------------------
    // Reject
    // ------------------------------------------------------------------

    public function test_reject_requires_a_safe_comment_shows_it_to_the_manager_and_grants_nothing(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $request = $this->pendingRequest($manager);

        foreach (['', 'мало', 'Свяжитесь с ivan@example.test', 'Звоните +7 900 123 45 67', str_repeat('я', 301)] as $bad) {
            $this->actingAs($admin)->post(route('cabinet.admin.client-requests.reject', $request), ['note' => $bad])->assertSessionHasErrors('note');
        }
        $this->assertTrue($request->fresh()->isPending());

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.reject', $request), ['note' => 'Уточните ФИО клиента'])->assertSessionHas('success');

        $request->refresh();
        $this->assertSame('rejected', $request->status);
        $this->assertNull($request->pending_key);
        $this->assertNull($request->resolved_user_id);
        $this->assertNull($request->access_expires_at);

        $this->actingAs($manager)->get(route('cabinet.manager.client-requests'))->assertOk()
            ->assertSee('Отклонён')->assertSee('Уточните ФИО клиента');
        $this->assertSame([], ClientVisibility::touristsVisibleTo($manager)->pluck('users.id')->all());

        // После решения тот же запрос можно отправить заново.
        $this->submit($manager, self::NEW_EMAIL)->assertSessionHas('success');
        $this->assertSame(2, ClientVerificationRequest::count());
    }

    // ------------------------------------------------------------------
    // Context and ownership
    // ------------------------------------------------------------------

    public function test_approval_links_the_inquiry_when_the_manager_still_owns_it(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $inquiry = $this->inquiryFor($manager);
        $request = $this->pendingRequest($manager, [], $inquiry);

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHas('success');

        $inquiry->refresh();
        $client = User::where('email', self::NEW_EMAIL)->sole();
        $this->assertSame($client->id, $inquiry->client_user_id);
        $this->assertSame($admin->id, $inquiry->client_linked_by);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()->assertSee('Клиент подтверждён');
    }

    public function test_an_inquiry_client_chosen_meanwhile_is_not_overwritten(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $chosen = $this->userWith('tourist');
        $inquiry = $this->inquiryFor($manager);
        $request = $this->pendingRequest($manager, [], $inquiry);
        $inquiry->forceFill(['client_user_id' => $chosen->id])->save();

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHas('success');

        $this->assertSame($chosen->id, $inquiry->fresh()->client_user_id);
    }

    public function test_reassigned_inquiry_blocks_approval_but_not_rejection(): void
    {
        $admin = $this->userWith('admin');
        $former = $this->userWith('manager');
        $current = $this->userWith('manager');
        $inquiry = $this->inquiryFor($former);
        $link = $this->pendingRequest($former, ['email' => 'ctx.one@example.test'], $inquiry);
        $create = $this->pendingRequest($former, ['email' => 'ctx.two@example.test'], $inquiry);
        $tourist = $this->userWith('tourist');
        $users = User::count();

        $inquiry->forceFill(['assigned_to' => $current->id])->save();

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $create))->assertSessionHasErrors('workflow');
        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.link', $link), ['user_id' => $tourist->id])->assertSessionHasErrors('workflow');

        $this->assertSame($users, User::count());
        $this->assertNull($inquiry->fresh()->client_user_id);
        $this->assertSame([], ClientVisibility::touristsVisibleTo($former)->pluck('users.id')->all());
        $this->assertSame([], ClientVisibility::touristsVisibleTo($current)->pluck('users.id')->all());

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.reject', $create), ['note' => 'Обращение переназначено'])->assertSessionHas('success');
        $this->assertSame('rejected', $create->fresh()->status);
    }

    public function test_closed_or_converted_inquiry_blocks_approval(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $inquiry = $this->inquiryFor($manager);
        $request = $this->pendingRequest($manager, [], $inquiry);
        $inquiry->forceFill(['workflow_state' => IncomingInquiry::WORKFLOW_CLOSED])->save();

        $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors('workflow');
        $this->assertNull(User::where('email', self::NEW_EMAIL)->first());
    }

    public function test_a_requester_who_is_no_longer_an_active_manager_cannot_receive_a_client(): void
    {
        $admin = $this->userWith('admin');
        $tourist = $this->userWith('tourist');

        foreach (['deactivated' => fn (User $u) => $u->forceFill(['is_active' => false])->save(), 'demoted' => fn (User $u) => $u->roles()->detach()] as $label => $change) {
            $manager = $this->userWith('manager');
            $request = $this->pendingRequest($manager, ['email' => "{$label}@example.test"]);
            $change($manager);

            $this->actingAs($admin)->post(route('cabinet.admin.client-requests.create', $request))->assertSessionHasErrors('workflow');
            $this->actingAs($admin)->post(route('cabinet.admin.client-requests.link', $request), ['user_id' => $tourist->id])->assertSessionHasErrors('workflow');
            $this->assertTrue($request->fresh()->isPending(), $label);
        }
    }

    public function test_access_is_time_limited_and_replaced_by_a_booking(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $other = $this->userWith('manager');

        $request = $this->pendingRequest($manager);
        $second = $this->pendingRequest($other, ['email' => 'second@example.test']);
        app(ClientVerificationService::class)->approveNew($request, $admin);
        app(ClientVerificationService::class)->approveNew($second, $admin);
        $client = User::where('email', self::NEW_EMAIL)->sole();
        $secondClient = User::where('email', 'second@example.test')->sole();

        $this->assertContains($client->id, ClientVisibility::touristsVisibleTo($manager)->pluck('users.id')->all());
        $this->assertContains($secondClient->id, ClientVisibility::touristsVisibleTo($other)->pluck('users.id')->all());

        // Менеджер оформляет заявку для подтверждённого клиента обычным путём.
        $this->actingAs($manager)->post(route('bookings.store'), $this->bookingPayload(['client_id' => $client->id]))->assertRedirect();
        $this->assertSame(1, Booking::where('manager_id', $manager->id)->where('user_id', $client->id)->count());

        try {
            $this->travel(ClientVerificationRequest::ACCESS_DAYS + 1)->days();

            $this->assertContains($client->id, ClientVisibility::touristsVisibleTo($manager)->pluck('users.id')->all(), 'Дальше доступ даёт заявка');
            $this->assertNotContains($secondClient->id, ClientVisibility::touristsVisibleTo($other)->pluck('users.id')->all(), 'Без заявки доступ по истечении срока пропадает');
        } finally {
            // Время в тестах Laravel не сбрасывается автоматически — иначе оно утекло бы в соседние тесты.
            $this->travelBack();
        }
    }

    public function test_approved_access_does_not_extend_to_other_inquiries_or_clients_of_other_managers(): void
    {
        $admin = $this->userWith('admin');
        $a = $this->userWith('manager');
        $b = $this->userWith('manager');
        $client = $this->userWith('tourist', ['email' => self::NEW_EMAIL]);
        $bBooking = $this->bookingOf($client, $b);
        $bInquiry = $this->inquiryFor($b, $client);

        $request = $this->pendingRequest($a);
        app(ClientVerificationService::class)->linkExisting($request, $admin, $client->id);

        $this->actingAs($a)->get(route('bookings.show', $bBooking))->assertForbidden();
        $this->actingAs($a)->get(route('cabinet.manager.inquiries.show', $bInquiry))->assertNotFound();
        \App\Models\Message::query()->create([
            'booking_id' => $bBooking->id, 'sender_id' => $b->id, 'receiver_id' => $client->id,
            'message' => 'ПРИВАТНЫЙ-ЧАТ-Б', 'is_read' => false,
        ]);
        $this->actingAs($a)->get(route('cabinet.manager.chat', $bBooking->id))->assertDontSee('ПРИВАТНЫЙ-ЧАТ-Б');
        $this->actingAs($a)->get(route('cabinet.manager.chat'))->assertDontSee('ПРИВАТНЫЙ-ЧАТ-Б');
    }

    // ------------------------------------------------------------------
    // Stored data and audit safety
    // ------------------------------------------------------------------

    public function test_request_rows_and_audit_metadata_contain_no_secrets_or_raw_email(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $this->submit($manager, 'Private.Person@Example.test', 'Приватный Человек', ['client_phone' => '+7 900 555-44-33']);
        app(ClientVerificationService::class)->approveNew(ClientVerificationRequest::sole(), $admin);
        $this->submit($manager, 'other.person@example.test', 'Другой Человек');
        app(ClientVerificationService::class)->reject(ClientVerificationRequest::query()->where('status', 'pending')->sole(), $admin, 'Не подходит клиент');

        $columns = array_keys((array) DB::table('client_verification_requests')->first());
        foreach ($columns as $column) {
            $this->assertDoesNotMatchRegularExpression('/pass|token|secret|passport|booking_id/i', $column);
        }

        $audit = AuditEvent::query()->where('subject_type', ClientVerificationService::AUDIT_SUBJECT)->get();
        $this->assertEquals(
            [
                AuditEventType::ClientVerificationCreated->value => 1,
                AuditEventType::ClientVerificationRejected->value => 1,
                AuditEventType::ClientVerificationRequested->value => 2,
            ],
            $audit->countBy('event_type')->sortKeys()->all()
        );

        $json = strtolower(json_encode($audit->map->metadata->all(), JSON_UNESCAPED_UNICODE));
        foreach (['private.person', 'other.person', '@example.test', '900 555', 'приватный', 'другой человек'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }

        foreach ($audit as $event) {
            foreach (array_keys($event->metadata ?? []) as $key) {
                $this->assertContains($key, ['context', 'inquiry_id', 'has_email', 'manager_id', 'client_id', 'linked_to_inquiry', 'reason']);
            }
        }
    }

    public function test_audit_page_labels_the_new_events(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $this->pendingRequest($manager);

        $this->actingAs($admin)->get(route('cabinet.admin.audit'))->assertOk()->assertSee('Запрос на проверку клиента передан');
    }

    // ------------------------------------------------------------------
    // Preserved workflows
    // ------------------------------------------------------------------

    public function test_ordinary_authorized_selection_and_admin_direct_creation_still_work(): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $own = $this->userWith('tourist', ['name' => 'Свой По Заявке']);
        $this->bookingOf($own, $manager);

        $this->actingAs($manager)->post(route('bookings.store'), $this->bookingPayload(['client_id' => $own->id]))->assertRedirect();
        $this->assertSame(2, Booking::where('user_id', $own->id)->count());

        $this->actingAs($admin)->post(route('bookings.store'), $this->bookingPayload(['is_new_client' => '1', 'client_name' => 'Прямой Клиент', 'client_email' => 'direct.admin@example.test']))
            ->assertSessionHasNoErrors()->assertRedirect();
        $this->assertNotNull(User::where('email', 'direct.admin@example.test')->first());
        $this->assertSame(0, ClientVerificationRequest::count());
    }

    public function test_manager_cannot_select_a_foreign_client_by_id(): void
    {
        $manager = $this->userWith('manager');
        $other = $this->userWith('manager');
        $foreign = $this->userWith('tourist');
        $this->bookingOf($foreign, $other);

        $this->actingAs($manager)->post(route('bookings.store'), $this->bookingPayload(['client_id' => $foreign->id]))->assertSessionHasErrors('client_id');
        $this->assertSame(0, Booking::where('manager_id', $manager->id)->count());
    }
}
