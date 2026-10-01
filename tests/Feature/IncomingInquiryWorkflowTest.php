<?php

namespace Tests\Feature;

use App\Http\Middleware\VerifyCsrfToken;
use App\Mail\BookingCreated as BookingCreatedMail;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use App\Services\IncomingInquiries\IncomingInquiryWorkflow;
use App\Services\IncomingInquiries\IncomingInquiryWorkflowException;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Route;
use Tests\TestCase;

/**
 * E5-A3 — входящее обращение → нативный процесс Avilona: взять в работу,
 * явно выбрать/создать клиента, явно оформить ОДНУ заявку, закрыть без заявки.
 * Синтетические данные; почта, уведомления и HTTP — только fake.
 */
class IncomingInquiryWorkflowTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Http::fake();

        // Роль туриста существует в рабочей БД (миграции/сидер); в тестах — явно.
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
            'client_phone' => '+7 (900) 123-45-67',
            'client_email' => 'synthetic.client@example.test',
            'client_comment' => 'Хотим у моря',
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

    private function claimed(User $by, array $overrides = []): IncomingInquiry
    {
        $inquiry = $this->inquiry($overrides);
        $this->actingAs($by)->post(route('cabinet.manager.inquiries.claim', $inquiry))->assertRedirect();

        return $inquiry->refresh();
    }

    private function linkedClaimed(User $manager, ?User $tourist = null, array $overrides = []): array
    {
        $tourist ??= $this->userWithRoles(['tourist']);
        $inquiry = $this->claimed($manager, $overrides);
        $this->actingAs($manager)
            ->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $tourist->id])
            ->assertRedirect();

        return [$inquiry->refresh(), $tourist];
    }

    private function convertPayload(array $overrides = []): array
    {
        return array_merge([
            'departure_city' => 'Москва',
            'destination_country' => 'Таиланд',
            'destination_city' => 'Пхукет',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => '100000',
            'price_verified' => '1',
            'notes' => 'Отель: TEST RESORT 3*',
            'manager_notes' => 'внутренняя заметка',
        ], $overrides);
    }

    // ------------------------------------------------------------------
    // Workflow / assignment
    // ------------------------------------------------------------------

    public function test_new_inquiry_starts_as_new_and_import_state_is_independent(): void
    {
        $inquiry = $this->inquiry();

        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $inquiry->workflow_state);
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->state);
        $this->assertNull($inquiry->assigned_to);
        $this->assertNull($inquiry->booking_id);
    }

    public function test_manager_claims_inquiry_and_technical_state_is_untouched(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.claim', $inquiry))
            ->assertRedirect(route('cabinet.manager.inquiries.show', $inquiry));

        $inquiry->refresh();
        $this->assertSame(IncomingInquiry::WORKFLOW_IN_PROGRESS, $inquiry->workflow_state);
        $this->assertSame($manager->id, $inquiry->assigned_to);
        $this->assertNotNull($inquiry->claimed_at);
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->state);
        $this->assertSame(0, Booking::count());
    }

    public function test_repeated_claim_by_same_manager_is_harmless(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.claim', $inquiry))
            ->assertSessionHas('success');

        $this->assertSame($manager->id, $inquiry->fresh()->assigned_to);
    }

    public function test_second_manager_cannot_steal_active_claim(): void
    {
        $first = $this->userWithRoles(['manager']);
        $second = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($first);

        $this->actingAs($second)->post(route('cabinet.manager.inquiries.claim', $inquiry))
            ->assertSessionHasErrors('workflow');

        $this->assertSame($first->id, $inquiry->fresh()->assigned_to);
    }

    public function test_stale_second_claim_loses_atomically(): void
    {
        $first = $this->userWithRoles(['manager']);
        $second = $this->userWithRoles(['manager']);
        $inquiry = $this->inquiry();
        $staleCopy = IncomingInquiry::find($inquiry->id);

        $service = app(IncomingInquiryWorkflow::class);
        $this->assertTrue($service->claim($inquiry, $first));

        $this->expectException(IncomingInquiryWorkflowException::class);
        try {
            $service->claim($staleCopy, $second);
        } finally {
            $this->assertSame($first->id, $inquiry->fresh()->assigned_to);
        }
    }

    public function test_inquiry_with_unfinished_import_cannot_be_claimed(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $failed = $this->inquiry(['state' => IncomingInquiry::STATE_FAILED]);
        $online = $this->inquiry(['external_type' => 1, 'state' => IncomingInquiry::STATE_UNSUPPORTED]);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.claim', $failed))->assertSessionHasErrors('workflow');
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.claim', $online))->assertSessionHasErrors('workflow');

        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $failed->fresh()->workflow_state);
        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $online->fresh()->workflow_state);
    }

    public function test_inactive_staff_cannot_claim(): void
    {
        $inactive = $this->userWithRoles(['manager'], ['is_active' => false]);
        $inquiry = $this->inquiry();

        $this->actingAs($inactive)->post(route('cabinet.manager.inquiries.claim', $inquiry))->assertSessionHasErrors('workflow');
        $this->assertNull($inquiry->fresh()->assigned_to);
    }

    public function test_admin_can_claim_and_reassign_only_to_assignable_staff(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);
        $inactiveManager = $this->userWithRoles(['manager'], ['is_active' => false]);
        $tourist = $this->userWithRoles(['tourist']);

        $inquiry = $this->claimed($admin);
        $this->assertSame($admin->id, $inquiry->assigned_to);

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), ['assigned_to' => $inactiveManager->id])
            ->assertSessionHasErrors('assigned_to');
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), ['assigned_to' => $tourist->id])
            ->assertSessionHasErrors('assigned_to');
        $this->assertSame($admin->id, $inquiry->fresh()->assigned_to);

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.reassign', $inquiry), ['assigned_to' => $manager->id])
            ->assertSessionHas('success');
        $this->assertSame($manager->id, $inquiry->fresh()->assigned_to);
    }

    public function test_manager_cannot_reassign(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $other = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.reassign', $inquiry), ['assigned_to' => $other->id])
            ->assertForbidden();
        $this->assertSame($manager->id, $inquiry->fresh()->assigned_to);
    }

    public function test_tourist_and_guest_cannot_use_any_inquiry_action(): void
    {
        $tourist = $this->userWithRoles(['tourist']);
        $client = $this->userWithRoles(['tourist']);
        $inquiry = $this->inquiry();

        $actions = [
            ['claim', []],
            ['reassign', ['assigned_to' => $tourist->id]],
            ['client.select', ['client_id' => $client->id]],
            ['client.create', ['client_name' => 'X']],
            ['client.password-setup', []],
            ['convert', $this->convertPayload()],
            ['close', ['close_confirmed' => '1']],
        ];

        foreach ($actions as [$name, $payload]) {
            $url = route('cabinet.manager.inquiries.' . $name, $inquiry);
            $this->actingAs($tourist)->post($url, $payload)->assertForbidden();
        }

        $this->app['auth']->forgetGuards();
        foreach ($actions as [$name, $payload]) {
            $this->post(route('cabinet.manager.inquiries.' . $name, $inquiry), $payload)->assertRedirect(route('login'));
        }

        $fresh = $inquiry->fresh();
        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $fresh->workflow_state);
        $this->assertNull($fresh->assigned_to);
        $this->assertSame(0, Booking::count());
    }

    public function test_other_manager_cannot_process_someone_elses_inquiry(): void
    {
        $owner = $this->userWithRoles(['manager']);
        $intruder = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        [$inquiry] = $this->linkedClaimed($owner, $tourist);

        $this->actingAs($intruder)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $tourist->id])->assertForbidden();
        $this->actingAs($intruder)->post(route('cabinet.manager.inquiries.client.create', $inquiry), ['client_name' => 'X'])->assertForbidden();
        $this->actingAs($intruder)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertForbidden();
        $this->actingAs($intruder)->post(route('cabinet.manager.inquiries.close', $inquiry), ['close_confirmed' => '1'])->assertForbidden();

        $this->assertSame(0, Booking::count());
        $this->assertSame(IncomingInquiry::WORKFLOW_IN_PROGRESS, $inquiry->fresh()->workflow_state);
    }

    public function test_mutation_routes_are_behind_web_csrf_and_staff_role_middleware(): void
    {
        foreach (['claim', 'reassign', 'client.select', 'client.create', 'client.password-setup', 'convert', 'close'] as $name) {
            $route = Route::getRoutes()->getByName('cabinet.manager.inquiries.' . $name);
            $this->assertNotNull($route, $name);
            $this->assertContains('POST', $route->methods());
            $middleware = $route->gatherMiddleware();
            $this->assertContains('web', $middleware, $name);
            $this->assertContains('role:manager,admin', $middleware, $name);
        }

        $this->assertNotContains('cabinet/*', (new class(app(), app('encrypter')) extends VerifyCsrfToken {
            public function excluded(): array
            {
                return $this->except;
            }
        })->excluded());
    }

    // ------------------------------------------------------------------
    // Client matching / selection
    // ------------------------------------------------------------------

    public function test_matching_email_is_only_a_suggestion_never_a_link(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $match = $this->userWithRoles(['tourist'], ['name' => 'Совпавший По Почте', 'email' => 'Synthetic.Client@example.test']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertOk()->assertSee('Совпавший По Почте')->assertSee('совпадает email');

        $inquiry->refresh();
        $this->assertNull($inquiry->client_user_id);
        $this->assertSame(0, Booking::count());
        $this->assertSame(0, $match->bookings()->count());
    }

    public function test_matching_phone_is_only_a_suggestion_never_a_link(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $match = $this->userWithRoles(['tourist'], ['name' => 'Совпавший По Телефону', 'phone' => '89001234567']);
        $unrelated = $this->userWithRoles(['tourist'], ['name' => 'Посторонний Турист', 'phone' => '+7 911 000-00-00']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertOk()->assertSee('Совпавший По Телефону')->assertSee('совпадает телефон')
            ->assertDontSee('Посторонний Турист');

        $this->assertNull($inquiry->fresh()->client_user_id);
        $this->assertSame($match->phone, $match->fresh()->phone);
    }

    public function test_conversion_is_blocked_while_client_is_only_a_match(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $this->userWithRoles(['tourist'], ['email' => 'synthetic.client@example.test']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())
            ->assertSessionHasErrors('client_id');

        $this->assertSame(0, Booking::count());
        $this->assertSame(IncomingInquiry::WORKFLOW_IN_PROGRESS, $inquiry->fresh()->workflow_state);
    }

    public function test_manual_search_finds_tourists_and_hides_staff(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $this->userWithRoles(['manager'], ['name' => 'Коллега Поискин']);
        $this->userWithRoles(['tourist'], ['name' => 'Поискин Турист']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', [$inquiry, 'client_q' => 'Поискин']))
            ->assertOk()->assertSee('Поискин Турист')->assertDontSee('Коллега Поискин');
    }

    public function test_explicit_selection_of_existing_tourist_persists(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->claimed($manager);
        $before = $tourist->fresh()->only(['name', 'email', 'phone', 'password']);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $tourist->id])
            ->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame($tourist->id, $inquiry->client_user_id);
        $this->assertSame($manager->id, $inquiry->client_linked_by);
        $this->assertNotNull($inquiry->client_linked_at);
        $this->assertSame($before, $tourist->fresh()->only(['name', 'email', 'phone', 'password']), 'Аккаунт не изменяется');
    }

    public function test_only_active_tourists_can_be_selected(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inactive = $this->userWithRoles(['tourist'], ['is_active' => false]);
        $staff = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        foreach ([$inactive->id, $staff->id, 999999] as $id) {
            $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $id])
                ->assertSessionHasErrors('client_id');
        }

        $this->assertNull($inquiry->fresh()->client_user_id);
    }

    public function test_selection_requires_inquiry_in_progress(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->inquiry();

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.select', $inquiry), ['client_id' => $tourist->id])
            ->assertForbidden();
        $this->assertNull($inquiry->fresh()->client_user_id);
    }

    // ------------------------------------------------------------------
    // New client
    // ------------------------------------------------------------------

    public function test_new_tourist_is_created_safely_and_linked(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), [
            'client_name' => 'Новый Турист',
            'client_email' => 'brand.new@example.test',
            'client_phone' => '+79001112233',
        ])->assertSessionHas('success');

        $client = User::where('email', 'brand.new@example.test')->sole();
        $this->assertTrue($client->isTourist());
        $this->assertFalse($client->isManager() || $client->isAdmin());
        $this->assertNull($client->email_verified_at, 'Импортированный email не считается подтверждённым');
        $this->assertNull($client->temp_password, 'Пароль нигде не хранится открытым текстом');
        $this->assertFalse((bool) $client->password_change_required);
        $this->assertTrue((bool) $client->is_active);
        $this->assertSame($client->id, $inquiry->fresh()->client_user_id);
        $this->assertSame($manager->id, $inquiry->fresh()->client_linked_by);

        foreach (['password', 'Password1', '12345678', 'avilona', '89001112233', '+79001112233', 'brand.new@example.test', 'Новый Турист'] as $guess) {
            $this->assertFalse(Hash::check($guess, $client->password), "Пароль не должен быть предсказуемым: $guess");
        }

        Mail::assertNothingSent();
        Mail::assertNothingQueued();
        Notification::assertNothingSent();
    }

    public function test_client_creation_rolls_back_completely_when_tourist_role_is_missing(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);
        Role::query()->where('name', Role::TOURIST)->delete();
        $usersBefore = User::count();

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), [
            'client_name' => 'Без Роли', 'client_email' => 'norole@example.test',
        ])->assertStatus(404);

        $this->assertSame($usersBefore, User::count());
        $this->assertNull($inquiry->fresh()->client_user_id);
    }

    public function test_two_new_clients_never_share_a_password_hash(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $a = $this->claimed($manager);
        $b = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $a), ['client_name' => 'A', 'client_email' => 'a@example.test']);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $b), ['client_name' => 'B', 'client_email' => 'b@example.test']);

        $this->assertNotSame(User::where('email', 'a@example.test')->value('password'), User::where('email', 'b@example.test')->value('password'));
    }

    public function test_existing_email_never_creates_duplicate_or_touches_existing_account(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $existing = $this->userWithRoles(['tourist'], ['name' => 'Давний Клиент', 'email' => 'taken@example.test']);
        $inquiry = $this->claimed($manager);
        $usersBefore = User::count();
        $snapshot = $existing->fresh()->only(['name', 'email', 'password', 'phone', 'email_verified_at']);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), [
            'client_name' => 'Другое Имя',
            'client_email' => 'TAKEN@example.test',
        ])->assertSessionHasErrors('client_email');

        $this->assertSame($usersBefore, User::count());
        $this->assertNull($inquiry->fresh()->client_user_id);
        $this->assertEquals($snapshot, $existing->fresh()->only(['name', 'email', 'password', 'phone', 'email_verified_at']));
    }

    public function test_new_tourist_without_email_gets_technical_address_and_no_mail(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), ['client_name' => 'Без Почты'])
            ->assertSessionHas('success');

        $client = User::where('name', 'Без Почты')->sole();
        $this->assertTrue($client->hasTechnicalEmail());
        $this->assertNull($client->temp_password);
        Mail::assertNothingSent();
    }

    public function test_password_setup_link_is_explicit_and_only_for_not_yet_activated_clients(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.create', $inquiry), [
            'client_name' => 'Новый', 'client_email' => 'setup@example.test',
        ]);
        Notification::assertNothingSent();

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.password-setup', $inquiry))->assertSessionHas('success');
        Notification::assertSentTo(User::where('email', 'setup@example.test')->sole(), ResetPassword::class);

        // Уже активный (подтверждённый) клиент — ссылку слать нельзя.
        $active = $this->userWithRoles(['tourist']);
        $other = $this->claimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.select', $other), ['client_id' => $active->id]);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.client.password-setup', $other))->assertSessionHasErrors('client_id');
        Notification::assertNotSentTo($active, ResetPassword::class);
    }

    // ------------------------------------------------------------------
    // Conversion
    // ------------------------------------------------------------------

    public function test_no_booking_exists_before_explicit_conversion(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $this->linkedClaimed($manager);

        $this->assertSame(0, Booking::count());
    }

    public function test_conversion_creates_exactly_one_native_booking_with_provenance(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry, $tourist] = $this->linkedClaimed($manager);
        $payloadBefore = $inquiry->vendor_payload;

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())
            ->assertRedirect(route('cabinet.manager.inquiries.show', $inquiry))
            ->assertSessionHas('success');

        $this->assertSame(1, Booking::count());
        $booking = Booking::sole();
        $this->assertSame($tourist->id, $booking->user_id);
        $this->assertSame($manager->id, $booking->manager_id);
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status, 'Нативный статус созданной сотрудником заявки с ответственным');
        $this->assertNull($booking->tour_id);
        $this->assertSame(0, Tour::count(), 'Фиктивный Tour не создаётся');
        $this->assertSame('0.00', (string) $booking->paid_amount, 'Оплаты нет');
        $this->assertSame(2, $booking->adults);
        $this->assertSame('Москва', $booking->departure_city);
        $this->assertSame('Таиланд', $booking->destination_country);
        $this->assertSame(7, $booking->nights);

        $inquiry->refresh();
        $this->assertSame(IncomingInquiry::WORKFLOW_CONVERTED, $inquiry->workflow_state);
        $this->assertSame($booking->id, $inquiry->booking_id);
        $this->assertSame($manager->id, $inquiry->converted_by);
        $this->assertNotNull($inquiry->converted_at);
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->state);
        $this->assertSame($payloadBefore, $inquiry->vendor_payload, 'Снимок провайдера сохраняется');
        $this->assertSame('100000.00', (string) $inquiry->price);
        $this->assertSame($booking->id, $inquiry->booking->id);
        $this->assertSame($inquiry->id, $booking->incomingInquiry->id);
        $this->assertStringNotContainsString('RAW-PAYLOAD-MARKER', json_encode($booking->getAttributes(), JSON_UNESCAPED_UNICODE));

        Mail::assertQueued(BookingCreatedMail::class, fn ($mail) => $mail->hasTo($tourist->email));
        Http::assertNothingSent();
    }

    public function test_conversion_cannot_be_forged_into_other_owner_status_or_tour(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $victim = $this->userWithRoles(['tourist']);
        $tour = Tour::factory()->create();
        [$inquiry, $tourist] = $this->linkedClaimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload([
            'user_id' => $victim->id, 'manager_id' => 999, 'status' => Booking::STATUS_CONFIRMED,
            'tour_id' => $tour->id, 'paid_amount' => 5000,
        ]))->assertSessionHas('success');

        $booking = Booking::sole();
        $this->assertSame($tourist->id, $booking->user_id);
        $this->assertSame($manager->id, $booking->manager_id);
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
        $this->assertNull($booking->tour_id);
        $this->assertSame('0.00', (string) $booking->paid_amount);
    }

    public function test_duplicate_submit_creates_no_second_booking(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload());
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())
            ->assertSessionHas('success', fn ($m) => str_contains($m, 'уже оформлено'));

        $this->assertSame(1, Booking::count());
        Mail::assertQueued(BookingCreatedMail::class, 1);
    }

    public function test_concurrent_conversion_through_stale_instances_yields_one_booking(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);
        $tabA = IncomingInquiry::find($inquiry->id);
        $tabB = IncomingInquiry::find($inquiry->id);
        $service = app(IncomingInquiryWorkflow::class);
        $data = $this->convertPayload();

        $first = $service->convert($tabA, $manager, $data);
        $second = $service->convert($tabB, $manager, $data);

        $this->assertTrue($first['created']);
        $this->assertFalse($second['created']);
        $this->assertSame($first['booking']->id, $second['booking']->id);
        $this->assertSame(1, Booking::count());
    }

    public function test_database_enforces_one_booking_per_inquiry(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload());
        $bookingId = $inquiry->fresh()->booking_id;
        $another = $this->inquiry();

        $this->expectException(QueryException::class);
        IncomingInquiry::whereKey($another->id)->update(['booking_id' => $bookingId]);
    }

    public function test_conversion_preconditions_are_enforced_without_side_effects(): void
    {
        $manager = $this->userWithRoles(['manager']);

        // не взято в работу → у обращения нет ответственного: менеджер не вправе обрабатывать
        $new = $this->inquiry();
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $new), $this->convertPayload())->assertForbidden();

        // клиент не выбран
        $noClient = $this->claimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $noClient), $this->convertPayload())->assertSessionHasErrors('client_id');

        // клиент деактивирован после выбора
        [$withInactive, $tourist] = $this->linkedClaimed($manager);
        $tourist->forceFill(['is_active' => false])->save();
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $withInactive), $this->convertPayload())->assertSessionHasErrors('client_id');

        // ответственный деактивирован после взятия в работу
        [$orphaned] = $this->linkedClaimed($manager);
        $manager->forceFill(['is_active' => false])->save();
        $admin = $this->userWithRoles(['admin']);
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.convert', $orphaned), $this->convertPayload())->assertSessionHasErrors('assigned_to');

        $this->assertSame(0, Booking::count());
        $this->assertSame(0, IncomingInquiry::where('workflow_state', IncomingInquiry::WORKFLOW_CONVERTED)->count());
    }

    public function test_admin_can_convert_inquiry_assigned_to_manager_and_manager_stays_responsible(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);
        [$inquiry, $tourist] = $this->linkedClaimed($manager);

        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload())->assertSessionHas('success');

        $booking = Booking::sole();
        $this->assertSame($manager->id, $booking->manager_id);
        $this->assertSame($admin->id, $inquiry->fresh()->converted_by);
        $this->assertSame($tourist->id, $booking->user_id);
    }

    public function test_conversion_validation_requires_core_fields_and_price_confirmation(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);

        foreach ([
            ['total_price' => ''], ['total_price' => '0'], ['total_price' => '-5'], ['total_price' => 'abc'],
            ['price_verified' => ''], ['adults' => ''], ['adults' => '0'], ['nights' => ''],
            ['departure_city' => ''], ['destination_country' => ''],
            ['start_date' => now()->toDateString()], ['start_date' => ''],
        ] as $bad) {
            $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload($bad))
                ->assertSessionHasErrors();
        }

        $payload = $this->convertPayload();
        unset($payload['total_price']);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $payload)->assertSessionHasErrors('total_price');

        $this->assertSame(0, Booking::count());
    }

    public function test_children_ages_text_is_parsed_into_native_array(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload([
            'children' => 2, 'children_ages' => '5, 8',
        ]))->assertSessionHas('success');

        $this->assertSame([5, 8], Booking::sole()->children_ages === null ? [] : array_map('intval', Booking::sole()->children_ages));
    }

    // ------------------------------------------------------------------
    // Price semantics
    // ------------------------------------------------------------------

    public function test_reference_offer_total_is_not_multiplied_by_tourist_count(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager, null, ['price' => '100000']);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload([
            'adults' => 2, 'children' => 0, 'total_price' => '100000',
        ]));

        $booking = Booking::sole();
        $this->assertSame('100000.00', (string) $booking->total_price, 'Подтверждённая сумма записана как есть');
        $this->assertNotSame('200000.00', (string) $booking->total_price);
        $this->assertSame('100000.00', (string) $inquiry->fresh()->price, 'Справочная цена обращения не меняется');
    }

    public function test_manager_confirmed_total_is_the_booking_amount_even_if_it_differs_from_reference(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager, null, ['price' => '100000']);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload([
            'adults' => 2, 'total_price' => '200000',
        ]));

        $this->assertSame('200000.00', (string) Booking::sole()->total_price);
        $this->assertSame('100000.00', (string) $inquiry->fresh()->price);
    }

    public function test_form_shows_reference_price_but_does_not_silently_prefill_the_amount(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager, null, ['price' => '100000']);

        $html = $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()
            ->assertSee('Справочно из Tourvisor')
            ->assertSee('100 000 RUB')
            ->assertSee('ничего не умножается')
            ->getContent();

        $this->assertMatchesRegularExpression('/name="total_price" value=""/', $html);
    }

    public function test_non_rub_reference_currency_is_flagged_and_not_offered_for_fill(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager, null, ['price' => '1000', 'currency' => 'EUR']);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()
            ->assertSee('Валюта обращения не рубли')
            ->assertDontSee('Подставить справочную цену');
    }

    // ------------------------------------------------------------------
    // Native booking afterwards
    // ------------------------------------------------------------------

    public function test_converted_booking_enters_native_lifecycle_with_correct_visibility(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $otherManager = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);
        $otherTourist = $this->userWithRoles(['tourist']);
        [$inquiry, $tourist] = $this->linkedClaimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload());
        $booking = Booking::sole();

        $this->actingAs($tourist)->get(route('bookings.show', $booking))->assertOk()
            ->assertDontSee('входящее обращение')
            ->assertDontSee('RAW-PAYLOAD-MARKER')
            ->assertDontSee('внутренняя заметка');
        $this->actingAs($otherTourist)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($otherManager)->get(route('bookings.show', $booking))->assertForbidden();
        $this->actingAs($manager)->get(route('bookings.show', $booking))->assertOk()
            ->assertSee('входящее обращение #' . $inquiry->id)
            ->assertSee(route('cabinet.manager.inquiries.show', $inquiry), false);
        $this->actingAs($admin)->get(route('bookings.show', $booking))->assertOk();

        // чат: авторизация не изменена
        $this->actingAs($tourist)->get(route('cabinet.chat', $booking->id))->assertOk();
        $this->actingAs($manager)->get(route('cabinet.manager.chat', ['bookingId' => $booking->id]))->assertOk();
        $this->actingAs($otherTourist)->get(route('cabinet.chat', $booking->id))->assertStatus(404);

        // документы: чужой менеджер по-прежнему отклоняется
        $this->actingAs($otherManager)->post(route('bookings.documents.store', $booking), [])->assertForbidden();

        // обычный жизненный цикл
        $this->assertTrue($booking->canTransitionTo(Booking::STATUS_CONFIRMED));
        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()
            ->assertSee('Заявка №' . $booking->id);
    }

    public function test_other_tourist_never_sees_converted_booking_in_lists(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $otherTourist = $this->userWithRoles(['tourist']);
        [$inquiry, $tourist] = $this->linkedClaimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload());

        $this->assertSame(1, $tourist->bookings()->count());
        $this->assertSame(0, $otherTourist->bookings()->count());
        $this->actingAs($otherTourist)->get(route('cabinet.bookings'))->assertOk()->assertDontSee('Таиланд');
    }

    public function test_workflow_makes_no_external_calls_and_takes_no_payment(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload());

        Http::assertNothingSent();
        $booking = Booking::sole();
        $this->assertSame('0.00', (string) $booking->paid_amount);
        $this->assertNotSame(Booking::STATUS_CONFIRMED, $booking->status);
        $this->assertNotSame(Booking::STATUS_COMPLETED, $booking->status);
    }

    // ------------------------------------------------------------------
    // Close without booking
    // ------------------------------------------------------------------

    public function test_close_without_booking_preserves_inquiry_and_creates_nothing(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $inquiry), [
            'close_confirmed' => '1', 'close_reason' => 'Клиент передумал',
        ])->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame(IncomingInquiry::WORKFLOW_CLOSED, $inquiry->workflow_state);
        $this->assertSame($manager->id, $inquiry->closed_by);
        $this->assertNotNull($inquiry->closed_at);
        $this->assertSame('Клиент передумал', $inquiry->close_reason);
        $this->assertSame(0, Booking::count());
        $this->assertSame('RAW-PAYLOAD-MARKER', $inquiry->vendor_payload['internal_marker']);
        $this->assertSame('Синтетический Клиент', $inquiry->client_name);
        $this->assertSame(1, IncomingInquiry::count());
    }

    public function test_close_requires_explicit_confirmation(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $inquiry), [])->assertSessionHasErrors('close_confirmed');
        $this->assertSame(IncomingInquiry::WORKFLOW_IN_PROGRESS, $inquiry->fresh()->workflow_state);
    }

    public function test_unclaimed_inquiry_can_be_closed_by_any_staff_but_claimed_one_only_by_responsible_or_admin(): void
    {
        $a = $this->userWithRoles(['manager']);
        $b = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);

        $free = $this->inquiry();
        $this->actingAs($b)->post(route('cabinet.manager.inquiries.close', $free), ['close_confirmed' => '1'])->assertSessionHas('success');
        $this->assertSame(IncomingInquiry::WORKFLOW_CLOSED, $free->fresh()->workflow_state);

        $owned = $this->claimed($a);
        $this->actingAs($b)->post(route('cabinet.manager.inquiries.close', $owned), ['close_confirmed' => '1'])->assertForbidden();
        $this->actingAs($admin)->post(route('cabinet.manager.inquiries.close', $owned), ['close_confirmed' => '1'])->assertSessionHas('success');
        $this->assertSame(IncomingInquiry::WORKFLOW_CLOSED, $owned->fresh()->workflow_state);
    }

    public function test_converted_inquiry_cannot_be_closed_and_closed_cannot_be_converted(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$converted] = $this->linkedClaimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $converted), $this->convertPayload());

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $converted), ['close_confirmed' => '1'])->assertSessionHasErrors('workflow');
        $this->assertSame(IncomingInquiry::WORKFLOW_CONVERTED, $converted->fresh()->workflow_state);
        $this->assertNull($converted->fresh()->closed_at);

        [$closed] = $this->linkedClaimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $closed), ['close_confirmed' => '1']);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $closed), $this->convertPayload())->assertSessionHasErrors('workflow');

        $this->assertSame(1, Booking::count());
    }

    public function test_repeated_close_is_harmless(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = $this->claimed($manager);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $inquiry), ['close_confirmed' => '1', 'close_reason' => 'Первая']);
        $closedAt = $inquiry->fresh()->closed_at;

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $inquiry), ['close_confirmed' => '1', 'close_reason' => 'Вторая'])
            ->assertSessionHas('success');

        $this->assertSame('Первая', $inquiry->fresh()->close_reason);
        $this->assertEquals($closedAt, $inquiry->fresh()->closed_at);
    }

    // ------------------------------------------------------------------
    // UI
    // ------------------------------------------------------------------

    public function test_list_filters_by_workflow_state_and_shows_assignee(): void
    {
        $manager = $this->userWithRoles(['manager'], ['name' => 'Ответственная Мария']);
        $new = $this->inquiry(['external_id' => '111111']);
        $mine = $this->claimed($manager, ['external_id' => '222222']);
        $closed = $this->claimed($manager, ['external_id' => '333333']);
        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.close', $closed), ['close_confirmed' => '1']);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries', ['status' => 'new']))
            ->assertOk()->assertSee('#111111')->assertDontSee('#222222')->assertDontSee('#333333');
        $this->actingAs($manager)->get(route('cabinet.manager.inquiries', ['status' => 'in_progress']))
            ->assertOk()->assertSee('#222222')->assertSee('Ответственная Мария')->assertDontSee('#111111');
        $this->actingAs($manager)->get(route('cabinet.manager.inquiries', ['status' => 'closed']))
            ->assertOk()->assertSee('#333333')->assertDontSee('#222222');
        $this->actingAs($manager)->get(route('cabinet.manager.inquiries', ['status' => 'garbage']))
            ->assertOk()->assertSee('#111111')->assertSee('#222222');
    }

    public function test_detail_keeps_not_a_booking_warning_until_conversion(): void
    {
        $manager = $this->userWithRoles(['manager']);
        [$inquiry] = $this->linkedClaimed($manager);

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()
            ->assertSee('Входящее обращение, не бронирование');

        $this->actingAs($manager)->post(route('cabinet.manager.inquiries.convert', $inquiry), $this->convertPayload());

        $this->actingAs($manager)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertOk()
            ->assertDontSee('Входящее обращение, не бронирование')
            ->assertSee('Заявка №' . Booking::sole()->id)
            ->assertSee('Результат: заявка Avilona');
    }

    public function test_inquiry_contacts_and_actions_are_not_exposed_to_tourist_pages(): void
    {
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = $this->inquiry();

        $this->actingAs($tourist)->get(route('cabinet.manager.inquiries.show', $inquiry))->assertForbidden()
            ->assertDontSee('synthetic.client@example.test');
        $this->actingAs($tourist)->get(route('cabinet.manager.inquiries', ['status' => 'new']))->assertForbidden();
    }
}
