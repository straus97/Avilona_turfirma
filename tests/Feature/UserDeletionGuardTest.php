<?php

namespace Tests\Feature;

use App\Http\Controllers\ProfileController;
use App\Models\AuditEvent;
use App\Models\BonusAccount;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use App\Services\Accounts\ClientVerificationService;
use App\Services\Accounts\UserDeletion;
use App\Services\Accounts\UserDeletionRefused;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-05-I3 — охраняемое удаление учётной записи: заявки, переписка, обращения и проверки
 * клиентов не теряются; отказ не разрывает сессию; допустимое удаление пишет аудит.
 * Синтетические данные, SQLite :memory:.
 */
class UserDeletionGuardTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        Storage::fake('local');
    }

    private function selfDelete(User $user)
    {
        $route = $user->hasRole(Role::TOURIST) && ! $user->hasAnyRole([Role::ADMIN, Role::MANAGER])
            ? route('cabinet.settings.destroy-account')
            : route('cabinet.manager.destroy-account');

        return $this->actingAs($user)->from(route('home.index'))->delete($route, ['password' => 'password']);
    }

    private function adminDelete(User $admin, User $target)
    {
        return $this->actingAs($admin)->from(route('cabinet.admin.users'))->delete(route('cabinet.admin.delete-user', $target));
    }

    private function assertRefusedAndIntact(User $user, string $needle = ''): void
    {
        $this->assertDatabaseHas('users', ['id' => $user->id]);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'user.deleted']);

        if ($needle !== '') {
            $this->assertStringContainsString($needle, (string) session('error'));
        }
    }

    // ---------------------------------------------------------------- Турист: заявки

    public function test_tourist_without_protected_records_can_delete_the_account_and_audit_is_written(): void
    {
        $tourist = $this->userWith('tourist', ['name' => 'Секретное Имя', 'email' => 'secret-name@example.test']);
        UserDocument::query()->create(['user_id' => $tourist->id, 'name' => 'Doc', 'document_type' => 'passport', 'file_path' => 'documents/personal/x.pdf', 'file_type' => 'pdf', 'file_size' => 1]);
        Storage::disk('local')->put('documents/personal/x.pdf', 'x');

        $this->selfDelete($tourist)->assertRedirect(route('home.index'));

        $this->assertGuest();
        $this->assertDatabaseMissing('users', ['id' => $tourist->id]);
        Storage::disk('local')->assertMissing('documents/personal/x.pdf');

        $event = AuditEvent::query()->where('event_type', AuditEventType::UserDeleted->value)->sole();
        $this->assertSame($tourist->id, (int) $event->actor_id);
        $this->assertSame('user', $event->subject_type);
        $this->assertSame($tourist->id, (int) $event->subject_id);
        $this->assertSame(['channel' => 'self_service', 'target_roles' => 'tourist', 'self_service' => true], $event->metadata);

        $json = json_encode($event->getAttributes());
        $this->assertStringNotContainsString('Секретное', $json);
        $this->assertStringNotContainsString('secret-name', $json);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('bookingStatuses')]
    public function test_tourist_with_a_booking_of_any_status_is_refused_and_keeps_session_and_data(string $status): void
    {
        $tourist = $this->userWith('tourist');
        $booking = $this->bookingOf($tourist, null, $status, ['total_price' => 1000, 'paid_amount' => 500]);
        UserDocument::query()->create(['user_id' => $tourist->id, 'name' => 'Doc', 'document_type' => 'passport', 'file_path' => 'documents/personal/keep.pdf', 'file_type' => 'pdf', 'file_size' => 1]);
        Storage::disk('local')->put('documents/personal/keep.pdf', 'x');

        $response = $this->selfDelete($tourist);

        $response->assertRedirect(route('home.index'));
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($tourist);
        $this->assertRefusedAndIntact($tourist, UserDeletionRefused::SUPPORT_EMAIL);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'user_id' => $tourist->id, 'status' => $status, 'total_price' => 1000, 'paid_amount' => 500, 'deleted_at' => null]);
        $this->assertDatabaseHas('user_documents', ['user_id' => $tourist->id]);
        Storage::disk('local')->assertExists('documents/personal/keep.pdf');
    }

    public static function bookingStatuses(): array
    {
        return [
            'new' => [Booking::STATUS_NEW],
            'progress' => [Booking::STATUS_PROGRESS],
            'confirmed' => [Booking::STATUS_CONFIRMED],
            'completed' => [Booking::STATUS_COMPLETED],
            'cancelled' => [Booking::STATUS_CANCELLED],
        ];
    }

    public function test_tourist_with_only_a_soft_deleted_booking_is_refused_and_the_row_survives(): void
    {
        $tourist = $this->userWith('tourist');
        $booking = $this->bookingOf($tourist, null);
        $booking->delete();

        $this->selfDelete($tourist)->assertSessionHas('error');

        $this->assertAuthenticatedAs($tourist);
        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertNotNull(Booking::withTrashed()->find($booking->id));
    }

    public function test_tourist_with_several_bookings_is_refused_and_every_booking_survives(): void
    {
        $tourist = $this->userWith('tourist');
        $ids = [
            $this->bookingOf($tourist, null, Booking::STATUS_NEW)->id,
            $this->bookingOf($tourist, null, Booking::STATUS_COMPLETED)->id,
            $this->bookingOf($tourist, null, Booking::STATUS_CANCELLED)->id,
        ];

        $this->selfDelete($tourist)->assertSessionHas('error');

        $this->assertSame(3, Booking::withTrashed()->whereIn('id', $ids)->where('user_id', $tourist->id)->count());
    }

    public function test_converted_inquiry_keeps_its_booking_link_when_the_client_deletion_is_refused(): void
    {
        $manager = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $booking = $this->bookingOf($client, $manager);
        $inquiry = $this->independentInquiry($manager, null, $client, [
            'status' => 'converted',
            'booking_id' => $booking->id,
            'converted_by' => $manager->id,
            'converted_at' => now(),
        ]);

        $this->selfDelete($client)->assertSessionHas('error');

        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'status' => 'converted', 'booking_id' => $booking->id, 'client_user_id' => $client->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
    }

    public function test_booking_documents_and_files_survive_a_refused_deletion(): void
    {
        $tourist = $this->userWith('tourist');
        $booking = $this->bookingOf($tourist, null);
        Storage::disk('local')->put('documents/bookings/1/voucher.pdf', 'v');
        $document = BookingDocument::query()->create(['booking_id' => $booking->id, 'document_type' => 'voucher', 'title' => 'V', 'file_path' => 'documents/bookings/1/voucher.pdf', 'file_size' => 1, 'uploaded_by' => null]);

        $this->selfDelete($tourist);

        $this->assertDatabaseHas('booking_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists('documents/bookings/1/voucher.pdf');
    }

    public function test_non_empty_bonus_account_protects_a_tourist_without_bookings(): void
    {
        $tourist = $this->userWith('tourist');
        BonusAccount::query()->create(['user_id' => $tourist->id, 'balance' => 50, 'level' => 'newbie', 'total_earned' => 50, 'total_spent' => 0, 'referral_code' => 'ABC12345']);

        $this->selfDelete($tourist)->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
    }

    public function test_empty_bonus_account_does_not_block_deletion(): void
    {
        $tourist = $this->userWith('tourist');
        BonusAccount::query()->create(['user_id' => $tourist->id, 'balance' => 0, 'level' => 'newbie', 'total_earned' => 0, 'total_spent' => 0, 'referral_code' => 'ZERO1234']);

        $this->selfDelete($tourist)->assertRedirect(route('home.index'));

        $this->assertDatabaseMissing('users', ['id' => $tourist->id]);
    }

    // ---------------------------------------------------------------- Администратор удаляет пользователя

    public function test_admin_deleting_a_tourist_with_a_booking_is_refused_with_a_visible_error(): void
    {
        $admin = $this->userWith('admin');
        $tourist = $this->userWith('tourist', ['name' => 'Чужое Имя']);
        $booking = $this->bookingOf($tourist, null);

        $response = $this->adminDelete($admin, $tourist);

        $response->assertRedirect(route('cabinet.admin.users'));
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);

        $message = (string) session('error');
        $this->assertStringContainsString('заявки клиента', $message);
        $this->assertStringContainsString('не реализовано', $message);
        $this->assertStringNotContainsString('Чужое Имя', $message);
        $this->assertStringNotContainsString((string) $tourist->email, $message);

        $this->get(route('cabinet.admin.users'))->assertSee('Пользователя нельзя удалить', false);
    }

    public function test_admin_can_delete_a_tourist_without_records_and_audit_records_the_admin_channel(): void
    {
        $admin = $this->userWith('admin');
        $tourist = $this->userWith('tourist');

        $this->adminDelete($admin, $tourist)->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $tourist->id]);
        $event = AuditEvent::query()->where('event_type', 'user.deleted')->sole();
        $this->assertSame($admin->id, (int) $event->actor_id);
        $this->assertSame($tourist->id, (int) $event->subject_id);
        $this->assertSame('admin_panel', $event->metadata['channel']);
        $this->assertFalse($event->metadata['self_service']);
    }

    public function test_non_admin_cannot_use_the_admin_deletion_route_and_foreign_accounts_survive(): void
    {
        $target = $this->userWith('tourist');
        $manager = $this->userWith('manager');
        $tourist = $this->userWith('tourist');

        $this->actingAs($manager)->delete(route('cabinet.admin.delete-user', $target))->assertForbidden();
        $this->actingAs($tourist)->delete(route('cabinet.admin.delete-user', $target))->assertForbidden();
        $this->actingAs($tourist)->delete(route('cabinet.manager.destroy-account'), ['password' => 'password'])->assertForbidden();

        $this->assertDatabaseHas('users', ['id' => $target->id]);
        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'user.deleted']);
    }

    public function test_admin_cannot_delete_their_own_account_from_the_user_list(): void
    {
        $admin = $this->userWith('admin');
        $this->userWith('admin');

        $this->adminDelete($admin, $admin)->assertSessionHas('error', 'Вы не можете удалить свой собственный аккаунт');

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    // ---------------------------------------------------------------- Сотрудники

    public function test_manager_without_history_can_delete_the_own_account(): void
    {
        $manager = $this->userWith('manager');

        $this->selfDelete($manager)->assertRedirect(route('home.index'));

        $this->assertDatabaseMissing('users', ['id' => $manager->id]);
        $this->assertDatabaseHas('audit_events', ['event_type' => 'user.deleted', 'subject_id' => $manager->id]);
    }

    /** @return array<string, array{0: string}> */
    public static function staffHistories(): array
    {
        return [
            'authored message' => ['authored_message'],
            'received message' => ['received_message'],
            'managed booking' => ['managed_booking'],
            'verification request' => ['verification_request'],
            'assigned inquiry' => ['assigned_inquiry'],
            'created inquiry' => ['created_inquiry'],
            'closed or converted inquiry evidence' => ['inquiry_closed_by'],
            'incoming inquiry assignment' => ['incoming_assignment'],
            'booking document upload' => ['document_upload'],
        ];
    }

    private function giveHistory(string $kind, User $manager): void
    {
        $client = $this->userWith('tourist');

        switch ($kind) {
            case 'authored_message':
                $booking = $this->bookingOf($client, $this->userWith('manager'));
                Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $manager->id, 'receiver_id' => $client->id, 'message' => 'Здравствуйте']);
                break;
            case 'received_message':
                $booking = $this->bookingOf($client, $this->userWith('manager'));
                Message::query()->create(['booking_id' => $booking->id, 'sender_id' => $client->id, 'receiver_id' => $manager->id, 'message' => 'Вопрос']);
                break;
            case 'managed_booking':
                $this->bookingOf($client, $manager);
                break;
            case 'verification_request':
                app(ClientVerificationService::class)->submit($manager, 'Новый Клиент', 'new-client@example.test', null);
                break;
            case 'assigned_inquiry':
                $this->independentInquiry($this->userWith('admin'), $manager);
                break;
            case 'created_inquiry':
                $inquiry = $this->independentInquiry($manager);
                DB::table('inquiries')->where('id', $inquiry->id)->update(['assigned_to' => null]);
                break;
            case 'inquiry_closed_by':
                $inquiry = $this->independentInquiry($this->userWith('admin'));
                DB::table('inquiries')->where('id', $inquiry->id)->update(['assigned_to' => null, 'closed_by' => $manager->id]);
                break;
            case 'incoming_assignment':
                $this->inquiryFor($manager, null);
                break;
            case 'document_upload':
                $booking = $this->bookingOf($client, null);
                BookingDocument::query()->create(['booking_id' => $booking->id, 'document_type' => 'voucher', 'title' => 'V', 'file_path' => 'documents/bookings/1/v.pdf', 'file_size' => 1, 'uploaded_by' => $manager->id]);
                break;
        }
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staffHistories')]
    public function test_staff_with_protected_history_is_refused_on_self_deletion_and_keeps_the_session(string $kind): void
    {
        $manager = $this->userWith('manager');
        $this->giveHistory($kind, $manager);

        $counts = [DB::table('messages')->count(), DB::table('bookings')->count(), DB::table('inquiries')->count(), DB::table('client_verification_requests')->count(), DB::table('incoming_inquiries')->count(), DB::table('booking_documents')->count()];

        $response = $this->selfDelete($manager);

        $response->assertRedirect(route('home.index'));
        $response->assertSessionHas('error');
        $this->assertAuthenticatedAs($manager);
        $this->assertRefusedAndIntact($manager, 'рабочая история');
        $this->assertSame($counts, [DB::table('messages')->count(), DB::table('bookings')->count(), DB::table('inquiries')->count(), DB::table('client_verification_requests')->count(), DB::table('incoming_inquiries')->count(), DB::table('booking_documents')->count()]);
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('staffHistories')]
    public function test_admin_cannot_delete_staff_with_protected_history(string $kind): void
    {
        $admin = $this->userWith('admin');
        $manager = $this->userWith('manager');
        $this->giveHistory($kind, $manager);

        $this->adminDelete($admin, $manager)->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $manager->id]);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'user.deleted']);
    }

    public function test_dual_role_manager_and_tourist_with_a_booking_is_refused(): void
    {
        $user = $this->userWith('manager');
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => 'Турист']);
        $user->assignRole(Role::TOURIST);
        $booking = $this->bookingOf($user, null);

        $this->selfDelete($user)->assertSessionHas('error');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        $this->assertDatabaseHas('users', ['id' => $user->id]);
    }

    // ---------------------------------------------------------------- Последний администратор

    public function test_the_last_active_admin_cannot_delete_the_own_account(): void
    {
        $admin = $this->userWith('admin');

        $response = $this->actingAs($admin)->from(route('cabinet.admin.profile'))->delete(route('cabinet.admin.destroy-account'), ['password' => 'password']);

        $response->assertRedirect(route('cabinet.admin.profile'));
        $this->assertStringContainsString('последнего активного администратора', (string) session('error'));
        $this->assertAuthenticatedAs($admin);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_an_inactive_second_admin_does_not_count_as_a_replacement(): void
    {
        $admin = $this->userWith('admin');
        $this->userWith('admin', ['is_active' => false]);

        $this->actingAs($admin)->delete(route('cabinet.manager.destroy-account'), ['password' => 'password']);

        $this->assertStringContainsString('последнего активного администратора', (string) session('error'));
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_the_last_active_admin_cannot_be_deleted_by_an_inactive_admin(): void
    {
        $active = $this->userWith('admin');
        $actor = $this->userWith('admin', ['is_active' => false]);

        $this->adminDelete($actor, $active)->assertSessionHas('error');

        $this->assertStringContainsString('последнего активного администратора', (string) session('error'));
        $this->assertDatabaseHas('users', ['id' => $active->id]);
    }

    public function test_an_admin_can_delete_another_admin_while_an_active_admin_remains(): void
    {
        $admin = $this->userWith('admin');
        $other = $this->userWith('admin');

        $this->adminDelete($admin, $other)->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $other->id]);
        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    public function test_deleting_an_inactive_admin_is_allowed_for_the_only_active_admin(): void
    {
        $admin = $this->userWith('admin');
        $inactive = $this->userWith('admin', ['is_active' => false]);

        $this->adminDelete($admin, $inactive)->assertSessionHas('success');

        $this->assertDatabaseMissing('users', ['id' => $inactive->id]);
    }

    public function test_an_admin_with_history_and_a_second_admin_is_still_protected(): void
    {
        $admin = $this->userWith('admin');
        $other = $this->userWith('admin');
        $this->bookingOf($this->userWith('tourist'), $other);

        $this->adminDelete($admin, $other)->assertSessionHas('error');

        $this->assertDatabaseHas('users', ['id' => $other->id]);
    }

    // ---------------------------------------------------------------- Атомарность, аудит, файлы

    public function test_audit_failure_rolls_back_the_deletion_and_keeps_account_records_and_files(): void
    {
        $tourist = $this->userWith('tourist');
        UserDocument::query()->create(['user_id' => $tourist->id, 'name' => 'Doc', 'document_type' => 'passport', 'file_path' => 'documents/personal/a.pdf', 'file_type' => 'pdf', 'file_size' => 1]);
        Storage::disk('local')->put('documents/personal/a.pdf', 'x');

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit down');
            }
        });

        $this->withoutExceptionHandling();

        try {
            $this->selfDelete($tourist);
            $this->fail('Expected the audit failure to propagate.');
        } catch (RuntimeException $e) {
            $this->assertSame('audit down', $e->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertDatabaseHas('user_documents', ['user_id' => $tourist->id]);
        Storage::disk('local')->assertExists('documents/personal/a.pdf');
        $this->assertAuthenticatedAs($tourist);
    }

    public function test_refusal_is_never_recorded_as_a_deletion_and_logs_only_identifiers(): void
    {
        $tourist = $this->userWith('tourist');
        $this->bookingOf($tourist, null);

        $logged = [];
        \Illuminate\Support\Facades\Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event;
        });

        $this->selfDelete($tourist);

        $this->assertDatabaseMissing('audit_events', ['event_type' => 'user.deleted']);

        $refusals = array_values(array_filter($logged, fn ($e) => $e->message === 'User account deletion refused'));
        $this->assertCount(1, $refusals);
        $this->assertSame(['actor_id', 'target_user_id', 'reason', 'categories'], array_keys($refusals[0]->context));
        $this->assertSame('protected_history', $refusals[0]->context['reason']);
        $this->assertSame(['owned_bookings'], $refusals[0]->context['categories']);
    }

    public function test_remember_token_logout_after_deletion_does_not_recreate_the_user(): void
    {
        $tourist = $this->userWith('tourist');
        DB::table('users')->where('id', $tourist->id)->update(['remember_token' => 'abcdef1234']);

        $this->selfDelete($tourist->refresh())->assertRedirect(route('home.index'));

        $this->assertDatabaseMissing('users', ['id' => $tourist->id]);
        $this->assertSame(0, User::query()->where('id', $tourist->id)->count());
    }

    public function test_the_unrouted_profile_controller_cannot_bypass_the_guard(): void
    {
        $tourist = $this->userWith('tourist');
        $booking = $this->bookingOf($tourist, null);

        $this->actingAs($tourist);
        $request = Request::create('/profile', 'DELETE', ['password' => 'password']);
        $request->setLaravelSession($this->app['session.store']);
        $request->setUserResolver(fn () => $tourist);

        $response = $this->app->make(ProfileController::class)->destroy($request, $this->app->make(UserDeletion::class));

        $this->assertTrue($response->isRedirect());
        $this->assertNotNull($request->session()->get('error'));
        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertDatabaseHas('bookings', ['id' => $booking->id]);
        $this->assertSame($tourist->id, Auth::id());
    }

    public function test_direct_model_deletion_is_refused_for_protected_users_and_for_the_last_admin(): void
    {
        $tourist = $this->userWith('tourist');
        $this->bookingOf($tourist, null);
        $admin = $this->userWith('admin');

        foreach ([$tourist, $admin] as $user) {
            try {
                $user->delete();
                $this->fail('Direct deletion must be refused.');
            } catch (UserDeletionRefused) {
                $this->assertDatabaseHas('users', ['id' => $user->id]);
            }
        }

        $this->assertSame(1, Booking::query()->count());
    }

    public function test_decision_uses_fresh_database_state_not_the_loaded_model(): void
    {
        $tourist = $this->userWith('tourist');
        $stale = User::query()->find($tourist->id); // загружен до появления заявки
        $this->bookingOf($tourist, null);          // «конкурирующий» писатель зафиксировал заявку

        $this->expectException(UserDeletionRefused::class);

        try {
            app(UserDeletion::class)->delete($stale, $this->userWith('admin'), UserDeletion::CHANNEL_ADMIN_PANEL);
        } finally {
            $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        }
    }

    public function test_unrelated_business_data_is_untouched_by_a_permitted_deletion(): void
    {
        $manager = $this->userWith('manager');
        $client = $this->userWith('tourist');
        $booking = $this->bookingOf($client, $manager);
        $inquiry = $this->independentInquiry($manager, null, $client);
        $admin = $this->userWith('admin');
        $victim = $this->userWith('tourist');

        $this->adminDelete($admin, $victim)->assertSessionHas('success');

        $this->assertDatabaseHas('bookings', ['id' => $booking->id, 'user_id' => $client->id, 'manager_id' => $manager->id]);
        $this->assertDatabaseHas('inquiries', ['id' => $inquiry->id, 'client_user_id' => $client->id, 'assigned_to' => $manager->id]);
        $this->assertDatabaseHas('users', ['id' => $manager->id]);
    }

    // ---------------------------------------------------------------- Перечень связей

    public function test_every_foreign_key_to_users_is_classified_by_the_deletion_guard(): void
    {
        $known = array_merge(
            array_keys(UserDeletion::PROTECTED_REFERENCES),
            array_keys(UserDeletion::CONDITIONAL_REFERENCES),
            UserDeletion::ACCEPTED_REFERENCES,
        );

        $found = [];

        foreach (Schema::getTables() as $table) {
            foreach (Schema::getForeignKeys($table['name']) as $foreignKey) {
                if ($foreignKey['foreign_table'] === 'users') {
                    foreach ($foreignKey['columns'] as $column) {
                        $found[] = $table['name'] . '.' . $column;
                    }
                }
            }
        }

        sort($found);
        $classified = $known;
        sort($classified);

        $this->assertSame([], array_values(array_diff($found, $known)), 'New FK to users is not classified in UserDeletion.');
        $this->assertSame([], array_values(array_diff($known, $found)), 'UserDeletion lists a reference that no longer exists.');
        $this->assertNotEmpty($classified);
    }

    public function test_user_document_files_of_booking_less_users_are_the_only_files_removed(): void
    {
        $tourist = $this->userWith('tourist');
        $other = $this->userWith('tourist');
        foreach ([[$tourist, 'documents/personal/mine.pdf'], [$other, 'documents/personal/other.pdf']] as [$owner, $path]) {
            Storage::disk('local')->put($path, 'x');
            UserDocument::query()->create(['user_id' => $owner->id, 'name' => 'D', 'document_type' => 'passport', 'file_path' => $path, 'file_type' => 'pdf', 'file_size' => 1]);
        }

        $this->selfDelete($tourist);

        Storage::disk('local')->assertMissing('documents/personal/mine.pdf');
        Storage::disk('local')->assertExists('documents/personal/other.pdf');
    }
}
