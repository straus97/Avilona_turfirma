<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\Accounts\AccountActivation;
use App\Services\Accounts\AccountChangeRefused;
use App\Services\Accounts\AccountRoleChange;
use App\Services\Accounts\UserDeletion;
use App\Services\Accounts\UserDeletionRefused;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\Schema;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-05-I4 — обратимое отключение учётной записи: вход и сессии закрыты, история сохранена,
 * последний активный администратор защищён. Синтетические данные, SQLite :memory:.
 */
class AccountDeactivationTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $admin2;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        $this->admin = $this->userWith('admin', ['name' => 'Админ Один']);
        $this->admin2 = $this->userWith('admin', ['name' => 'Админ Два']);
    }

    private function state(User $user): string
    {
        return app(AccountActivation::class)->stateToken($user);
    }

    private function deactivate(User $actor, User $target, string $reason = 'Сотрудник покинул агентство', ?string $state = null)
    {
        return $this->actingAs($actor)->from(route('cabinet.admin.user-show', $target))->post(
            route('cabinet.admin.user-deactivate', $target),
            ['reason' => $reason, 'expected_state' => $state ?? $this->state($target)],
        );
    }

    private function reactivate(User $actor, User $target, string $reason = 'Возвращается на работу', ?string $state = null)
    {
        return $this->actingAs($actor)->from(route('cabinet.admin.user-show', $target))->post(
            route('cabinet.admin.user-reactivate', $target),
            ['reason' => $reason, 'expected_state' => $state ?? $this->state($target)],
        );
    }

    private function events(string $type): int
    {
        return AuditEvent::query()->where('event_type', $type)->count();
    }

    private function snapshot(): array
    {
        return [
            'users' => DB::table('users')->count(),
            'bookings' => DB::table('bookings')->count(),
            'messages' => DB::table('messages')->count(),
            'inquiries' => DB::table('inquiries')->count(),
            'incoming' => DB::table('incoming_inquiries')->count(),
            'documents' => DB::table('user_documents')->count() + DB::table('booking_documents')->count(),
            'roles' => DB::table('role_user')->count(),
            'audit' => DB::table('audit_events')->count(),
        ];
    }

    // ---------------------------------------------------------------- Отключение и сохранность истории

    public function test_admin_deactivates_a_tourist_and_all_business_history_is_preserved(): void
    {
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist', ['name' => 'Турист Тестов', 'email' => 'tourist.keep@example.test', 'phone' => '+7 900 111-22-33']);
        $booking = $this->bookingOf($tourist, $manager);
        $message = Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $tourist->id, 'receiver_id' => $manager->id, 'message' => 'Сохранить', 'is_read' => false]);
        UserDocument::query()->create(['user_id' => $tourist->id, 'name' => 'Doc', 'document_type' => 'passport', 'file_path' => 'documents/personal/x.pdf', 'file_type' => 'pdf', 'file_size' => 1]);
        $inquiry = $this->independentInquiry($this->admin, $manager, $tourist);
        $before = $this->snapshot();
        $roles = $tourist->roles()->pluck('name')->all();

        $this->deactivate($this->admin, $tourist)->assertRedirect(route('cabinet.admin.user-show', $tourist));

        $fresh = $tourist->fresh();
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertSame('tourist.keep@example.test', $fresh->email);
        $this->assertSame('+7 900 111-22-33', $fresh->phone);
        $this->assertSame('Турист Тестов', $fresh->name);
        $this->assertSame($roles, $fresh->roles()->pluck('name')->all());
        $this->assertSame($tourist->id, $booking->fresh()->user_id);
        $this->assertSame($manager->id, $booking->fresh()->manager_id);
        $this->assertNotNull(Message::query()->find($message->id));
        $this->assertSame($tourist->id, $inquiry->fresh()->client_user_id);

        $after = $this->snapshot();
        $this->assertSame(1, $after['audit'] - $before['audit']);
        unset($before['audit'], $after['audit']);
        $this->assertSame($before, $after);
        $this->assertStringContainsString('отключена', (string) session('success'));
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_admin_deactivates_a_manager_and_assignment_history_stays_untouched(): void
    {
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist');
        $booking = $this->bookingOf($tourist, $manager);
        $inquiry = $this->independentInquiry($this->admin, $manager);
        $incoming = $this->inquiryFor($manager);
        $auditBefore = AuditEvent::query()->pluck('id')->all();

        $this->deactivate($this->admin, $manager)->assertSessionHasNoErrors();

        $this->assertFalse((bool) $manager->fresh()->is_active);
        // Ответственность не переписывается и не передаётся автоматически.
        $this->assertSame($manager->id, $booking->fresh()->manager_id);
        $this->assertSame($manager->id, $inquiry->fresh()->assigned_to);
        $this->assertSame($manager->id, $incoming->fresh()->assigned_to);
        // Прежние события аудита не тронуты, добавлено ровно одно.
        $this->assertSame($auditBefore, AuditEvent::query()->whereIn('id', $auditBefore)->pluck('id')->all());
        $this->assertSame(count($auditBefore) + 1, AuditEvent::query()->count());
        // Новых назначений неактивному сотруднику нет: правило пригодности уже его исключает.
        $this->assertFalse(User::query()->assignableToBookings()->whereKey($manager->id)->exists());
    }

    public function test_admin_deactivates_another_admin_and_the_actor_remains_active(): void
    {
        $this->deactivate($this->admin, $this->admin2)->assertSessionHasNoErrors();

        $this->assertFalse((bool) $this->admin2->fresh()->is_active);
        $this->assertTrue((bool) $this->admin->fresh()->is_active);
        $this->assertTrue($this->admin2->fresh()->hasRole('admin'), 'Роли при отключении не меняются.');
    }

    public function test_deactivation_audit_carries_ids_state_and_reason_but_no_personal_data(): void
    {
        $tourist = $this->userWith('tourist', ['name' => 'Секретное Имя', 'email' => 'secret.person@example.test', 'phone' => '+7 911 222-33-44']);

        $this->deactivate($this->admin, $tourist, 'Просьба клиента, обращение закрыто');

        $event = AuditEvent::query()->where('event_type', 'user.deactivated')->sole();
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame('user', $event->subject_type);
        $this->assertSame($tourist->id, $event->subject_id);
        $this->assertFalse($event->metadata['new_active']);
        $this->assertTrue($event->metadata['previous_active']);
        $this->assertSame('tourist', $event->metadata['target_roles']);
        $this->assertSame('Просьба клиента, обращение закрыто', $event->metadata['reason']);
        $json = json_encode($event->metadata, JSON_UNESCAPED_UNICODE);
        foreach (['secret.person@example.test', 'Секретное Имя', '911', 'password', 'token'] as $needle) {
            $this->assertStringNotContainsString($needle, $json);
        }
    }

    public function test_reactivation_restores_access_for_each_role_without_creating_or_rewriting_records(): void
    {
        foreach (['tourist', 'manager', 'admin'] as $role) {
            $target = $this->userWith($role, ['is_active' => false]);
            $client = $this->userWith('tourist');
            $booking = $role === 'tourist' ? $this->bookingOf($target, null) : $this->bookingOf($client, $target);
            $before = $this->snapshot();
            $roles = $target->roles()->pluck('name')->all();

            $this->reactivate($this->admin, $target)->assertSessionHasNoErrors();

            $this->assertTrue((bool) $target->fresh()->is_active, $role);
            $this->assertSame($roles, $target->fresh()->roles()->pluck('name')->all(), $role);
            $this->assertSame($booking->user_id, $booking->fresh()->user_id, $role);
            $this->assertSame($booking->manager_id, $booking->fresh()->manager_id, $role);
            $after = $this->snapshot();
            $this->assertSame(1, $after['audit'] - $before['audit'], $role);
            unset($before['audit'], $after['audit']);
            $this->assertSame($before, $after, "{$role}: reactivation must not create or delete records");
        }

        $this->assertSame(3, $this->events('user.reactivated'));
    }

    public function test_reactivated_staff_resume_own_data_only_and_gain_no_new_assignment(): void
    {
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist');
        $booking = $this->bookingOf($tourist, $manager);
        $this->deactivate($this->admin, $manager);
        // Администратор передал заявку другому сотруднику, пока аккаунт был отключён.
        $other = $this->userWith('manager');
        $booking->forceFill(['manager_id' => $other->id])->save();

        $this->reactivate($this->admin, $manager);

        $this->assertSame($other->id, $booking->fresh()->manager_id, 'Реактивация не возвращает чужую ответственность.');
        $this->assertSame(1, Booking::query()->count());
    }

    // ---------------------------------------------------------------- Вход и открытые сессии

    public function test_inactive_user_cannot_login_and_error_matches_unknown_account_and_wrong_password(): void
    {
        $inactive = $this->userWith('manager', ['is_active' => false, 'email' => 'inactive.mgr@example.test']);
        $active = $this->userWith('manager', ['email' => 'active.mgr@example.test']);

        $this->post('/login', ['email' => $inactive->email, 'password' => 'password']);
        $this->assertGuest();
        $inactiveMessage = session('errors')->first('email');

        RateLimiter::clear('x');
        $this->flushSession();
        $this->post('/login', ['email' => 'nobody.here@example.test', 'password' => 'password']);
        $unknownMessage = session('errors')->first('email');

        $this->flushSession();
        $this->post('/login', ['email' => $active->email, 'password' => 'wrong-password']);
        $wrongMessage = session('errors')->first('email');

        $this->assertNotSame('', $inactiveMessage);
        $this->assertSame($unknownMessage, $inactiveMessage);
        $this->assertSame($wrongMessage, $inactiveMessage);
        $this->assertGuest();
    }

    public function test_login_works_again_after_reactivation(): void
    {
        $user = $this->userWith('tourist', ['is_active' => false]);
        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertGuest();

        $this->reactivate($this->admin, $user);
        Auth::forgetGuards();
        $this->flushSession();

        $this->post('/login', ['email' => $user->email, 'password' => 'password']);
        $this->assertAuthenticatedAs($user->fresh());
    }

    public function test_existing_session_is_rejected_at_the_next_request_after_deactivation(): void
    {
        $manager = $this->userWith('manager');
        $this->post('/login', ['email' => $manager->email, 'password' => 'password']);
        $this->assertAuthenticated();
        $this->get(route('cabinet.manager.dashboard'))->assertOk();

        app(AccountActivation::class)->deactivate($manager, $this->admin, 'Увольнение сотрудника', $this->state($manager));

        $this->get(route('cabinet.manager.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        // Сессия закрыта, а не просто не пускает: публичная страница уже открывается как гостю.
        $this->get(route('home.index'))->assertOk();
        $this->assertGuest();
    }

    public function test_deactivated_session_is_rejected_on_every_authenticated_surface(): void
    {
        $tourist = $this->userWith('tourist');
        $manager = $this->userWith('manager');
        $admin = $this->userWith('admin');
        $booking = $this->bookingOf($tourist, $manager);

        foreach ([
            [$tourist, route('cabinet.dashboard')],
            [$tourist, route('bookings.show', $booking)],
            [$manager, route('cabinet.manager.dashboard')],
            [$admin, route('cabinet.admin.dashboard')],
            [$admin, route('cabinet.admin.users')],
        ] as [$user, $url]) {
            DB::table('users')->where('id', $user->id)->update(['is_active' => true]);
            $this->actingAs($user)->get($url)->assertOk();

            DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
            $this->actingAs($user)->get($url)->assertRedirect(route('login'));
            $this->assertGuest();
        }
    }

    public function test_inactive_user_cannot_reactivate_themselves_through_a_stale_session(): void
    {
        $this->actingAs($this->admin2)->get(route('cabinet.admin.dashboard'))->assertOk();
        DB::table('users')->where('id', $this->admin2->id)->update(['is_active' => false]);

        $this->actingAs($this->admin2)->post(route('cabinet.admin.user-reactivate', $this->admin2), [
            'reason' => 'Включаю себя сам',
            'expected_state' => 'inactive:admin',
        ])->assertRedirect(route('login'));

        $this->assertFalse((bool) $this->admin2->fresh()->is_active);
        $this->assertSame(0, $this->events('user.reactivated'));

        // Даже прямой вызов службы с устаревшей сессией отклоняется по актуальной строке.
        try {
            app(AccountActivation::class)->reactivate($this->admin2, $this->admin2, 'Включаю себя сам', 'inactive:admin');
            $this->fail('Inactive actor must be refused.');
        } catch (AccountChangeRefused $e) {
            $this->assertSame(AccountChangeRefused::REASON_NOT_AUTHORIZED, $e->reason);
        }
    }

    public function test_remember_me_cookie_stops_working_after_deactivation(): void
    {
        $user = $this->userWith('manager');
        $this->post('/login', ['email' => $user->email, 'password' => 'password', 'remember' => '1']);
        $oldToken = $user->fresh()->remember_token;
        $this->assertNotEmpty($oldToken);
        $recaller = $user->id . '|' . $oldToken . '|' . $user->fresh()->password;
        $cookie = Auth::guard('web')->getRecallerName();

        // Контроль: до отключения этот cookie аутентифицирует пользователя.
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie($cookie, $recaller)->get(route('cabinet.manager.dashboard'))->assertOk();
        $this->defaultCookies = [];
        Auth::forgetGuards();
        $this->flushSession();

        $this->deactivate($this->admin, $user);
        $this->assertNotSame($oldToken, $user->fresh()->remember_token, 'Токен «запомнить меня» заменён.');

        // Старый cookie не подходит: токен другой (даже если бы запись снова стала активной).
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie($cookie, $recaller)->get(route('cabinet.manager.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
        $this->defaultCookies = [];
        Auth::forgetGuards();
        $this->flushSession();

        DB::table('users')->where('id', $user->id)->update(['is_active' => true]);
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie($cookie, $recaller)->get(route('cabinet.manager.dashboard'))->assertRedirect(route('login'));
    }

    public function test_remember_me_cookie_with_a_matching_token_is_still_rejected_for_an_inactive_account(): void
    {
        $user = $this->userWith('manager');
        $token = 'remember-token-' . str_repeat('x', 20);
        DB::table('users')->where('id', $user->id)->update(['remember_token' => $token]);
        $recaller = $user->id . '|' . $token . '|' . $user->fresh()->password;
        $cookie = Auth::guard('web')->getRecallerName();

        // Контроль: пока запись активна, тот же cookie аутентифицирует пользователя.
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie($cookie, $recaller)->get(route('cabinet.manager.dashboard'))->assertOk();
        $this->defaultCookies = [];
        Auth::forgetGuards();
        $this->flushSession();

        DB::table('users')->where('id', $user->id)->update(['is_active' => false]);
        Auth::forgetGuards();
        $this->flushSession();
        $this->withCookie($cookie, $recaller)->get(route('cabinet.manager.dashboard'))->assertRedirect(route('login'));
        $this->assertGuest();
    }

    public function test_api_token_is_revoked_on_deactivation_and_rejected_for_inactive_accounts(): void
    {
        $user = $this->userWith('tourist');
        $plain = $user->createToken('probe')->plainTextToken;

        $this->withToken($plain)->getJson('/api/user')->assertOk();

        app(AccountActivation::class)->deactivate($user, $this->admin, 'Компрометация учётной записи', $this->state($user));
        $this->assertSame(0, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());

        Auth::forgetGuards();
        $this->withToken($plain)->getJson('/api/user')->assertUnauthorized();

        // Токен, оставшийся в БД (прямая правка), у неактивной записи всё равно не действует.
        Auth::forgetGuards();
        $other = $this->userWith('tourist');
        $otherPlain = $other->createToken('probe')->plainTextToken;
        DB::table('users')->where('id', $other->id)->update(['is_active' => false]);
        $this->withToken($otherPlain)->getJson('/api/user')->assertUnauthorized();
    }

    public function test_database_session_rows_of_the_user_are_removed_when_the_session_table_exists(): void
    {
        config(['session.driver' => 'database', 'session.table' => 'sessions']);
        Schema::create('sessions', function ($table): void {
            $table->string('id')->primary();
            $table->foreignId('user_id')->nullable();
            $table->longText('payload');
            $table->integer('last_activity');
        });
        $user = $this->userWith('tourist');
        DB::table('sessions')->insert([
            ['id' => 's1', 'user_id' => $user->id, 'payload' => 'x', 'last_activity' => time()],
            ['id' => 's2', 'user_id' => $this->admin->id, 'payload' => 'x', 'last_activity' => time()],
        ]);

        $this->deactivate($this->admin, $user);

        $this->assertSame(['s2'], DB::table('sessions')->pluck('id')->all());
    }

    public function test_password_reset_neither_sends_mail_nor_resets_nor_reactivates_an_inactive_account(): void
    {
        $user = $this->userWith('tourist', ['is_active' => false, 'email' => 'inactive.reset@example.test']);
        $unknownMessage = null;

        $this->post('/forgot-password', ['email' => 'nobody.known@example.test']);
        $unknownMessage = session('errors')->first('email');
        $this->flushSession();

        $this->post('/forgot-password', ['email' => $user->email]);
        Notification::assertNotSentTo($user, ResetPassword::class);
        $this->assertSame($unknownMessage, session('errors')->first('email'), 'Ответ не раскрывает состояние аккаунта.');

        // Токен, выпущенный до отключения, тоже не сбрасывает пароль.
        $token = Password::broker()->createToken($user);
        $hash = $user->fresh()->password;
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'brand-New-pass-1', 'password_confirmation' => 'brand-New-pass-1'])
            ->assertSessionHasErrors('email');

        $fresh = $user->fresh();
        $this->assertFalse((bool) $fresh->is_active);
        $this->assertSame($hash, $fresh->password);
    }

    public function test_password_reset_of_an_active_account_still_works(): void
    {
        $user = $this->userWith('tourist');
        $this->post('/forgot-password', ['email' => $user->email]);

        Notification::assertSentTo($user, ResetPassword::class);
        $token = Password::broker()->createToken($user);
        $this->post('/reset-password', ['token' => $token, 'email' => $user->email, 'password' => 'brand-New-pass-1', 'password_confirmation' => 'brand-New-pass-1'])
            ->assertSessionHasNoErrors();
        $this->assertTrue(Hash::check('brand-New-pass-1', $user->fresh()->password));
    }

    public function test_public_pages_and_the_external_webhook_route_do_not_depend_on_account_state(): void
    {
        $this->get(route('home.index'))->assertOk();
        $this->get('/login')->assertOk();

        $route = app('router')->getRoutes()->getByName('webhooks.tourvisor.inquiries');
        $this->assertNotContains('account.active', $route->gatherMiddleware());
        $this->assertNotContains(\App\Http\Middleware\EnsureAccountIsActive::class, $route->gatherMiddleware());
    }

    // ---------------------------------------------------------------- Права и подделка

    public function test_only_an_active_admin_can_change_account_status(): void
    {
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist');
        $victim = $this->userWith('tourist');
        $inactive = $this->userWith('tourist', ['is_active' => false]);

        foreach ([$manager, $tourist] as $actor) {
            $this->deactivate($actor, $victim)->assertForbidden();
            $this->reactivate($actor, $inactive)->assertForbidden();
        }

        $this->assertTrue((bool) $victim->fresh()->is_active);
        $this->assertFalse((bool) $inactive->fresh()->is_active);

        auth()->logout();
        $this->post(route('cabinet.admin.user-deactivate', $victim), ['reason' => 'Гость пытается', 'expected_state' => 'active:tourist'])
            ->assertRedirect(route('login'));
        $this->assertTrue((bool) $victim->fresh()->is_active);
        $this->assertSame(0, $this->events('user.deactivated') + $this->events('user.reactivated'));
    }

    public function test_dual_role_manager_without_admin_role_is_refused_even_at_the_service_layer(): void
    {
        $manager = $this->userWith('manager');
        $victim = $this->userWith('tourist');

        $this->expectException(AccountChangeRefused::class);

        try {
            app(AccountActivation::class)->deactivate($victim, $manager, 'Менеджер пытается', $this->state($victim));
        } finally {
            $this->assertTrue((bool) $victim->fresh()->is_active);
            $this->assertSame(0, $this->events('user.deactivated'));
        }
    }

    public function test_forged_body_fields_cannot_redirect_the_operation(): void
    {
        $target = $this->userWith('tourist');
        $other = $this->userWith('tourist');

        $this->actingAs($this->admin)->post(route('cabinet.admin.user-deactivate', $target), [
            'reason' => 'Обычная причина отключения',
            'expected_state' => $this->state($target),
            'user_id' => $other->id,
            'id' => $other->id,
            'is_active' => 1,
            'actor_id' => $this->admin2->id,
        ])->assertSessionHasNoErrors();

        $this->assertFalse((bool) $target->fresh()->is_active);
        $this->assertTrue((bool) $other->fresh()->is_active);
        $event = AuditEvent::query()->where('event_type', 'user.deactivated')->sole();
        $this->assertSame($this->admin->id, $event->actor_id);
        $this->assertSame($target->id, $event->subject_id);

        $this->actingAs($this->admin)->post(route('cabinet.admin.user-deactivate', 999999), ['reason' => 'Несуществующий', 'expected_state' => 'active:tourist'])
            ->assertNotFound();
    }

    // ---------------------------------------------------------------- Причина, повтор, устаревшая форма

    public function test_reason_is_required_bounded_and_screened(): void
    {
        $target = $this->userWith('tourist');
        $state = $this->state($target);

        foreach ([
            '' => 'reason',
            'нет' => 'reason',
            str_repeat('я', 201) => 'reason',
            'пишите на ivan@example.test срочно' => 'reason',
            'звонить +7 900 123 45 67' => 'reason',
        ] as $reason => $field) {
            $this->deactivate($this->admin, $target, (string) $reason, $state)->assertSessionHasErrors($field);
        }

        $this->assertTrue((bool) $target->fresh()->is_active);
        $this->assertSame(0, $this->events('user.deactivated'));
    }

    public function test_repeated_and_double_submitted_changes_are_safe_no_ops(): void
    {
        $target = $this->userWith('manager');
        $state = $this->state($target);

        $this->deactivate($this->admin, $target, 'Первая отправка формы', $state);
        $this->deactivate($this->admin, $target, 'Повторная отправка формы', $state)->assertSessionHasNoErrors();
        $this->assertSame(1, $this->events('user.deactivated'));
        $this->assertStringContainsString('изменений нет', (string) session('success'));

        $inactiveState = $this->state($target);
        $this->reactivate($this->admin, $target, 'Возвращаем на работу', $inactiveState);
        $this->reactivate($this->admin, $target, 'Повторная отправка', $inactiveState);
        $this->assertSame(1, $this->events('user.reactivated'));

        $this->deactivate($this->admin, $target, 'Снова отключаем', $this->state($target));
        $this->assertSame(2, $this->events('user.deactivated'));
        $this->assertFalse((bool) $target->fresh()->is_active);
    }

    public function test_stale_form_is_refused_when_roles_or_status_changed_since_it_was_rendered(): void
    {
        $target = $this->userWith('tourist');
        $stale = $this->state($target);
        Role::query()->firstOrCreate(['name' => 'manager'], ['description' => 'manager']);
        $target->assignRole(Role::MANAGER);

        $this->deactivate($this->admin, $target, 'Форма была устаревшей', $stale);
        $this->assertStringContainsString('уже изменились', (string) session('error'));
        $this->assertTrue((bool) $target->fresh()->is_active);

        $this->deactivate($this->admin, $target, 'Мусорное состояние формы', 'garbage');
        $this->assertTrue((bool) $target->fresh()->is_active);
        $this->assertSame(0, $this->events('user.deactivated'));
    }

    // ---------------------------------------------------------------- Последний администратор

    public function test_an_admin_cannot_deactivate_their_own_account(): void
    {
        $this->deactivate($this->admin, $this->admin)->assertSessionHas('error');
        $this->assertStringContainsString('собственную', (string) session('error'));
        $this->assertTrue((bool) $this->admin->fresh()->is_active);
        $this->assertSame(0, $this->events('user.deactivated'));
    }

    public function test_the_only_active_admin_cannot_be_deactivated(): void
    {
        DB::table('users')->where('id', $this->admin2->id)->update(['is_active' => false]);

        $this->deactivate($this->admin, $this->admin);

        $this->assertStringContainsString('без активного администратора', (string) session('error'));
        $this->assertTrue((bool) $this->admin->fresh()->is_active);
        $this->assertSame(0, $this->events('user.deactivated'));
    }

    public function test_a_deactivated_admin_cannot_deactivate_the_remaining_one_two_admin_sequence(): void
    {
        // Последовательный исход гонки «два администратора отключают друг друга»: победил первый.
        $this->deactivate($this->admin, $this->admin2)->assertSessionHasNoErrors();

        try {
            app(AccountActivation::class)->deactivate($this->admin, $this->admin2, 'Ответный ход второго', $this->state($this->admin));
            $this->fail('Deactivated admin must not act.');
        } catch (AccountChangeRefused $e) {
            $this->assertSame(AccountChangeRefused::REASON_NOT_AUTHORIZED, $e->reason);
        }

        $this->actingAs($this->admin2)->post(route('cabinet.admin.user-deactivate', $this->admin), ['reason' => 'Ответный ход второго', 'expected_state' => $this->state($this->admin)])
            ->assertRedirect(route('login'));

        $this->assertTrue((bool) $this->admin->fresh()->is_active);
        $this->assertTrue(User::query()->active()->byRole(Role::ADMIN)->exists());
    }

    public function test_the_last_active_admin_cannot_lose_the_admin_role_by_detach_or_replacement(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->first();
        $managerRole = Role::query()->firstOrCreate(['name' => 'manager'], ['description' => 'manager']);
        DB::table('users')->where('id', $this->admin2->id)->update(['is_active' => false]);

        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$this->admin, $adminRole]))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));

        $this->actingAs($this->admin)->post(route('cabinet.admin.user-update-role', $this->admin), ['role' => 'manager'])->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        $this->assertFalse($this->admin->fresh()->hasRole('manager'));

        // Неактивный администратор не считается: у второго тоже нельзя снять роль, если он единственный активный.
        $this->actingAs($this->admin2)->get(route('cabinet.admin.dashboard'))->assertRedirect(route('login'));
        $this->assertTrue($managerRole->exists);
    }

    public function test_admin_role_can_be_removed_from_one_of_two_active_admins_and_unrelated_changes_still_work(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->first();
        $tourist = $this->userWith('tourist');
        $managerRole = Role::query()->firstOrCreate(['name' => 'manager'], ['description' => 'manager']);

        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$this->admin2, $adminRole]))->assertSessionHas('success');
        $this->assertFalse($this->admin2->fresh()->hasRole('admin'));

        // Теперь админ остался один: второй раз снять роль нельзя.
        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$this->admin, $adminRole]))->assertSessionHas('error');
        $this->assertTrue($this->admin->fresh()->hasRole('admin'));

        // Несвязанные изменения ролей работают как раньше.
        $this->actingAs($this->admin)->post(route('cabinet.admin.user-update-role', $tourist), ['role' => 'manager'])->assertSessionHas('success');
        $this->assertTrue($tourist->fresh()->hasRole('manager'));
        $this->assertFalse($tourist->fresh()->hasRole('tourist'));
        $this->assertTrue($managerRole->exists);
    }

    public function test_inactive_admin_does_not_count_towards_the_invariant(): void
    {
        $inactiveAdmin = $this->userWith('admin', ['is_active' => false]);
        DB::table('users')->where('id', $this->admin2->id)->delete();
        $adminRole = Role::query()->where('name', 'admin')->first();

        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$this->admin, $adminRole]))->assertSessionHas('error');

        $this->assertTrue($this->admin->fresh()->hasRole('admin'));
        // Снять роль у неактивного администратора можно: активных он не уменьшает.
        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$inactiveAdmin, $adminRole]))->assertSessionHas('success');
        $this->assertFalse($inactiveAdmin->fresh()->hasRole('admin'));
    }

    public function test_dual_role_account_keeps_admin_protection_through_either_role(): void
    {
        $adminRole = Role::query()->where('name', 'admin')->first();
        $managerRole = Role::query()->firstOrCreate(['name' => 'manager'], ['description' => 'manager']);
        DB::table('users')->where('id', $this->admin2->id)->delete();
        $this->admin->roles()->attach($managerRole->id);

        // Снять роль менеджера можно, роль администратора — нет.
        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$this->admin, $managerRole]))->assertSessionHas('success');
        $this->actingAs($this->admin)->delete(route('cabinet.admin.remove-role', [$this->admin, $adminRole]))->assertSessionHas('error');
        $this->assertSame(['admin'], $this->admin->fresh()->roles()->pluck('name')->all());
    }

    public function test_the_last_active_admin_cannot_be_deleted_and_deletion_guards_still_work(): void
    {
        DB::table('users')->where('id', $this->admin2->id)->update(['is_active' => false]);

        try {
            app(UserDeletion::class)->delete($this->admin, $this->admin2, UserDeletion::CHANNEL_ADMIN_PANEL);
            $this->fail('Deleting the last active admin must be refused.');
        } catch (UserDeletionRefused $e) {
            $this->assertSame(UserDeletionRefused::REASON_LAST_ADMIN, $e->reason);
        }
        $this->assertDatabaseHas('users', ['id' => $this->admin->id]);

        // AOA-05-I3: пользователь с заявкой по-прежнему не удаляется.
        $tourist = $this->userWith('tourist');
        $this->bookingOf($tourist, null);
        $this->actingAs($this->admin2)->delete(route('cabinet.admin.delete-user', $tourist))->assertRedirect(route('login'));
        $this->actingAs($this->admin)->delete(route('cabinet.admin.delete-user', $tourist));
        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertSame(0, $this->events('user.deleted'));
    }

    public function test_role_removal_is_only_possible_through_the_guarded_service(): void
    {
        $offenders = [];

        foreach (new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(base_path('app'), \FilesystemIterator::SKIP_DOTS)) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $path = str_replace('\\', '/', $file->getPathname());
            $code = (string) file_get_contents($file->getPathname());

            if (preg_match('/roles\(\)->(detach|sync)\(/', $code) === 1 && ! str_ends_with($path, 'Services/Accounts/AccountRoleChange.php') && ! str_ends_with($path, 'Models/User.php')) {
                $offenders[] = $path;
            }

            if (! str_ends_with($path, 'Models/User.php') && preg_match('/->removeRole\(/', $code) === 1) {
                $offenders[] = $path . ' (removeRole)';
            }
        }

        $this->assertSame([], $offenders, 'Role removal must go through AccountRoleChange.');
    }

    // ---------------------------------------------------------------- Атомарность и аудит

    public function test_audit_failure_rolls_back_deactivation_tokens_and_remember_token(): void
    {
        $user = $this->userWith('manager');
        $user->createToken('probe');
        $remember = $user->fresh()->remember_token;

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit down');
            }
        });

        try {
            app(AccountActivation::class)->deactivate($user, $this->admin, 'Откат при сбое аудита', $this->state($user));
            $this->fail('Audit failure must propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit down', $e->getMessage());
        }

        $fresh = $user->fresh();
        $this->assertTrue((bool) $fresh->is_active);
        $this->assertSame($remember, $fresh->remember_token);
        $this->assertSame(1, DB::table('personal_access_tokens')->where('tokenable_id', $user->id)->count());
        $this->assertSame(0, $this->events('user.deactivated'));
    }

    public function test_audit_failure_rolls_back_reactivation(): void
    {
        $user = $this->userWith('manager', ['is_active' => false]);

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit down');
            }
        });

        try {
            app(AccountActivation::class)->reactivate($user, $this->admin, 'Откат при сбое аудита', $this->state($user));
            $this->fail('Audit failure must propagate.');
        } catch (RuntimeException) {
        }

        $this->assertFalse((bool) $user->fresh()->is_active);
    }

    public function test_refused_operations_leave_no_success_events(): void
    {
        $target = $this->userWith('tourist');
        $manager = $this->userWith('manager');

        $this->deactivate($this->admin, $this->admin);
        $this->deactivate($this->admin, $target, 'x');
        $this->deactivate($manager, $target)->assertForbidden();
        $this->deactivate($this->admin, $target, 'Устаревшая форма', 'active:nothing');

        $this->assertSame(0, AuditEvent::query()->whereIn('event_type', ['user.deactivated', 'user.reactivated'])->count());
    }

    // ---------------------------------------------------------------- Сохранность E5 / AOA-05

    public function test_conversion_and_intake_state_survive_deactivation(): void
    {
        $manager = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $inquiry = $this->independentInquiry($this->admin, $manager, $client);
        $incoming = $this->inquiryFor($manager, $client);

        $this->deactivate($this->admin, $manager);

        $this->assertSame($incoming->workflow_state, $incoming->fresh()->workflow_state);
        $this->assertSame($incoming->external_id, $incoming->fresh()->external_id);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->fresh()->status);

        // Конвертация обращения неактивного сотрудника отклоняется существующим правилом пригодности — заявка не создаётся.
        $before = Booking::query()->count();
        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.convert', $inquiry), [
            'expected_status' => $inquiry->status,
            'expected_assignee_id' => $manager->id,
            'expected_client_id' => $client->id,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7,
            'adults' => 2,
        ]);
        $this->assertSame($before, Booking::query()->count());
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->fresh()->status);
    }

    public function test_deactivating_the_linked_client_keeps_the_booking_and_blocks_new_conversion(): void
    {
        $manager = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $booking = $this->bookingOf($client, $manager);

        $this->deactivate($this->admin, $client);

        $this->assertSame($client->id, $booking->fresh()->user_id);
        $this->assertFalse(\App\Services\Accounts\ClientVisibility::touristsVisibleTo($this->admin)->whereKey($client->id)->exists(), 'Неактивный турист не предлагается как активный клиент.');
    }

    // ---------------------------------------------------------------- Интерфейс

    public function test_user_card_shows_status_forms_workload_and_history_without_raw_json(): void
    {
        $manager = $this->userWith('manager', ['name' => 'Менеджер Карточки']);
        $this->bookingOf($this->userWith('tourist'), $manager);
        $this->independentInquiry($this->admin, $manager);

        $html = $this->actingAs($this->admin)->get(route('cabinet.admin.user-show', $manager))->assertOk()->getContent();
        $this->assertStringContainsString('Статус учётной записи', $html);
        $this->assertStringContainsString('Отключить учётную запись', $html);
        $this->assertStringContainsString('name="expected_state"', $html);
        $this->assertStringContainsString('Автоматической передачи нет', $html);
        $this->assertStringContainsString(route('cabinet.admin.user-deactivate', $manager), $html);
        $this->assertStringNotContainsString('{"', $html);

        $this->deactivate($this->admin, $manager, 'Плановое отключение');
        $html = $this->actingAs($this->admin)->get(route('cabinet.admin.user-show', $manager))->assertOk()->getContent();
        $this->assertStringContainsString('Отключена', $html);
        $this->assertStringContainsString('Включить учётную запись', $html);
        $this->assertStringContainsString(route('cabinet.admin.user-reactivate', $manager), $html);
        $this->assertStringContainsString('Плановое отключение', $html);
        $this->assertStringContainsString('История статуса', $html);
        $this->assertStringContainsString('Админ Один', $html);
    }

    public function test_own_card_offers_no_self_deactivation_and_users_list_marks_inactive_accounts(): void
    {
        $inactive = $this->userWith('tourist', ['is_active' => false, 'name' => 'Отключённый Турист']);

        $html = $this->actingAs($this->admin)->get(route('cabinet.admin.user-show', $this->admin))->assertOk()->getContent();
        $this->assertStringContainsString('Свою учётную запись отключить нельзя', $html);
        $this->assertStringNotContainsString('Отключить учётную запись', $html);

        $list = $this->actingAs($this->admin)->get(route('cabinet.admin.users'))->assertOk()->getContent();
        $this->assertStringContainsString('Неактивен', $list);
        $this->assertTrue($inactive->exists);
    }
}
