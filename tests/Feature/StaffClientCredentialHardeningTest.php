<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Mail\BookingCreated as BookingCreatedMail;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use App\Services\Accounts\StaffClientAccounts;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Str;
use Tests\TestCase;

/**
 * E5-A3.1: у клиента, созданного сотрудником, нет пароля открытым текстом
 * ни в БД, ни в ответе, ни в логах, ни в письмах.
 */
class StaffClientCredentialHardeningTest extends TestCase
{
    use RefreshDatabase;

    /** Подменяет единственный генерируемый секрет (64 символа) узнаваемым значением. */
    private const SECRET = 'SENTINEL-staff-client-secret-0123456789-abcdefghijklmnopqrstuv';

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();

        foreach ([Role::ADMIN, Role::MANAGER, Role::TOURIST] as $roleName) {
            Role::query()->firstOrCreate(['name' => $roleName], ['description' => $roleName]);
        }

        // Только длина 64 (пароль) получает известное значение: токены сессии и
        // CSRF (длина 40) остаются настоящими случайными строками.
        Str::createRandomStringsUsing(
            fn (int $length): string => $length === 64
                ? self::SECRET
                : substr(bin2hex(random_bytes($length)), 0, $length)
        );

        $this->logged = [];
        Log::listen(function (MessageLogged $event): void {
            $this->logged[] = $event->message . ' ' . json_encode($event->context);
        });
    }

    protected function tearDown(): void
    {
        Str::createRandomStringsNormally();

        parent::tearDown();
    }

    // ------------------------------------------------------------------
    // Booking creation by staff
    // ------------------------------------------------------------------

    public function test_staff_created_tourist_has_no_plaintext_password_anywhere(): void
    {
        Event::fake([BookingCreated::class]);
        $manager = $this->makeUser(Role::MANAGER);

        $response = $this->actingAs($manager)
            ->post(route('bookings.store'), $this->payload('Иван Новый', 'ivan.new@example.test'));
        $response->assertRedirect();

        $client = User::where('email', 'ivan.new@example.test')->sole();

        // Секрет действительно использовался как пароль — но только в виде хеша.
        $this->assertTrue(Hash::check(self::SECRET, $client->password));
        $this->assertNotSame(self::SECRET, $client->password);

        $this->assertNull($client->temp_password);
        $this->assertFalse((bool) $client->password_change_required);
        $this->assertNull($client->email_verified_at);
        $this->assertTrue((bool) $client->is_active);
        $this->assertTrue($client->hasRole(Role::TOURIST));

        // Ни одна колонка ни одной строки users не содержит открытый секрет.
        foreach (DB::table('users')->get() as $row) {
            foreach ((array) $row as $column => $value) {
                $this->assertStringNotContainsString(self::SECRET, (string) $value, "users.$column");
            }
        }

        // Ответ (страница заявки после редиректа), сессия и JSON-представление.
        $page = $this->actingAs($manager)->get($response->headers->get('Location'));
        $page->assertOk();
        $this->assertStringNotContainsString(self::SECRET, $page->getContent());
        $this->assertStringNotContainsString(self::SECRET, json_encode(session()->all()));
        $this->assertArrayNotHasKey('temp_password', $client->toArray());
        $this->assertArrayNotHasKey('password', $client->toArray());

        // Логи.
        $this->assertStringNotContainsString(self::SECRET, implode("\n", $this->logged));
    }

    public function test_generated_secret_is_never_mailed_and_no_credentials_text_is_sent(): void
    {
        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->payload('Мария Почта', 'maria.mail@example.test'))
            ->assertRedirect();

        $client = User::where('email', 'maria.mail@example.test')->sole();

        $queued = 0;
        Mail::assertQueued(BookingCreatedMail::class, function (BookingCreatedMail $mail) use ($client, &$queued): bool {
            if (! $mail->hasTo($client->email)) {
                return false;
            }

            $queued++;
            $html = $mail->render();

            $this->assertStringNotContainsString(self::SECRET, $html);
            $this->assertStringNotContainsString('Временный пароль', $html);
            $this->assertStringNotContainsString('Данные для входа', $html);
            $this->assertStringContainsString(route('password.request'), $html);

            return true;
        });
        $this->assertSame(1, $queued);

        // Ссылка установки пароля автоматически не уходит — только явным действием.
        Notification::assertNothingSent();
    }

    public function test_technical_email_client_gets_no_mail_and_no_reset_link(): void
    {
        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->payload('Без Почты'))
            ->assertRedirect();

        $client = User::where('name', 'Без Почты')->sole();
        $booking = Booking::query()->sole();

        $this->assertTrue($client->hasTechnicalEmail());
        $this->assertNull($client->temp_password);
        $this->assertNull($client->email_verified_at);
        Mail::assertNotQueued(BookingCreatedMail::class, fn ($mail) => $mail->hasTo($client->email));
        Notification::assertNothingSent();

        // Явное действие для технического адреса отклоняется без отправки.
        $this->actingAs($manager)
            ->post(route('bookings.client.password-setup', $booking))
            ->assertSessionHasErrors('client_password_setup');
        Notification::assertNothingSent();
    }

    public function test_case_insensitive_duplicate_email_creates_no_user_and_does_not_touch_existing(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $existing = $this->makeUser(Role::TOURIST, ['email' => 'Client.Dup@Example.test', 'name' => 'Исходный']);
        $before = $existing->fresh()->getAttributes();
        $usersBefore = User::count();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->payload('Другой Человек', 'client.dup@example.test'))
            ->assertSessionHasErrors('client_email');

        $this->assertSame($usersBefore, User::count());
        $this->assertSame(0, Booking::count());
        $this->assertEquals($before, $existing->fresh()->getAttributes());
        $this->assertFalse(Hash::check(self::SECRET, $existing->fresh()->password));
        Notification::assertNothingSent();
    }

    // ------------------------------------------------------------------
    // Explicit password-setup action
    // ------------------------------------------------------------------

    public function test_assigned_manager_sends_standard_reset_link_only_when_asked(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->payload('Ссылка Клиент', 'link.client@example.test'))
            ->assertRedirect();

        $client = User::where('email', 'link.client@example.test')->sole();
        $booking = Booking::query()->sole();
        Notification::assertNothingSent();

        $this->actingAs($manager)
            ->get(route('bookings.show', $booking))
            ->assertOk()
            ->assertSee('Отправить клиенту ссылку для установки пароля');

        $this->actingAs($manager)
            ->post(route('bookings.client.password-setup', $booking))
            ->assertSessionHas('success');

        Notification::assertSentTo($client, ResetPassword::class, function (ResetPassword $n) use ($client): bool {
            $url = $n->toMail($client)->actionUrl;

            return str_contains($url, '/reset-password/') && ! str_contains($url, self::SECRET);
        });
        $this->assertNull($client->fresh()->email_verified_at);
        $this->assertNull($client->fresh()->temp_password);
    }

    public function test_password_setup_is_refused_for_already_active_clients(): void
    {
        $manager = $this->makeUser(Role::MANAGER);

        foreach ([
            'verified' => ['email_verified_at' => now()],
            'logged_in' => ['email_verified_at' => null, 'last_login_at' => now()],
        ] as $label => $attributes) {
            $client = $this->makeUser(Role::TOURIST, $attributes);
            $booking = $this->bookingFor($client, $manager);

            $this->actingAs($manager)
                ->post(route('bookings.client.password-setup', $booking))
                ->assertSessionHasErrors('client_password_setup');

            Notification::assertNotSentTo($client, ResetPassword::class);
            $this->assertNotNull($label);
        }
    }

    public function test_password_setup_authorization_is_assigned_staff_only(): void
    {
        $assigned = $this->makeUser(Role::MANAGER);
        $otherManager = $this->makeUser(Role::MANAGER);
        $admin = $this->makeUser(Role::ADMIN);
        $client = $this->makeUser(Role::TOURIST, ['email_verified_at' => null]);
        $stranger = $this->makeUser(Role::TOURIST);
        $booking = $this->bookingFor($client, $assigned);
        $route = route('bookings.client.password-setup', $booking);

        // Турист (в том числе владелец заявки) и чужой менеджер — нельзя.
        foreach ([$client, $stranger, $otherManager] as $actor) {
            $status = $this->actingAs($actor)->post($route)->getStatusCode();
            $this->assertContains($status, [302, 403], 'actor must be refused');
            Notification::assertNothingSent();
        }
        $this->assertSame(403, $this->actingAs($otherManager)->post($route)->getStatusCode());
        $this->assertSame(403, $this->actingAs($stranger)->post($route)->getStatusCode());

        // Гость — на вход.
        auth()->logout();
        $this->post($route)->assertRedirect(route('login'));
        Notification::assertNothingSent();

        // Админ — можно.
        $this->actingAs($admin)->post($route)->assertSessionHas('success');
        Notification::assertSentTo($client, ResetPassword::class);
    }

    // ------------------------------------------------------------------
    // Shared service / E5-A3 contract
    // ------------------------------------------------------------------

    public function test_shared_service_creates_the_same_safe_account_for_every_caller(): void
    {
        $service = app(StaffClientAccounts::class);

        $client = $service->createTourist('Сервис Клиент', ' svc@example.test ', ' +79990001122 ');

        $this->assertSame('svc@example.test', $client->email);
        $this->assertSame('+79990001122', $client->phone);
        $this->assertTrue(Hash::check(self::SECRET, $client->password));
        $this->assertNull($client->temp_password);
        $this->assertFalse((bool) $client->password_change_required);
        $this->assertNull($client->email_verified_at);
        $this->assertTrue($client->hasRole(Role::TOURIST));
        $this->assertTrue($service->emailExists('SVC@EXAMPLE.TEST'));

        $technical = $service->createTourist('Без Email', null);
        $this->assertTrue($technical->hasTechnicalEmail());
        $this->assertFalse($service->canSendPasswordSetup($technical));
        $this->assertFalse($service->sendPasswordSetup($technical));
        Notification::assertNothingSent();

        // Контейнер отдаёт оба потребителя с тем же сервисом (одна реализация).
        $this->assertInstanceOf(StaffClientAccounts::class, app(StaffClientAccounts::class));
        $workflow = new \ReflectionProperty(\App\Services\IncomingInquiries\IncomingInquiryWorkflow::class, 'accounts');
        $this->assertSame(StaffClientAccounts::class, $workflow->getType()->getName());
        $controller = new \ReflectionProperty(\App\Http\Controllers\Booking\BookingController::class, 'accounts');
        $this->assertSame(StaffClientAccounts::class, $controller->getType()->getName());
    }

    public function test_temp_password_is_not_mass_assignable_and_is_hidden(): void
    {
        $user = new User();
        $user->fill(['name' => 'X', 'temp_password' => 'plain-text-secret']);

        $this->assertNull($user->temp_password);
        $this->assertContains('temp_password', $user->getHidden());

        $user->forceFill(['temp_password' => 'legacy']);
        $this->assertArrayNotHasKey('temp_password', $user->toArray());
    }

    // ------------------------------------------------------------------
    // password_change_required semantics
    // ------------------------------------------------------------------

    public function test_password_change_required_flow_still_works_for_legacy_accounts(): void
    {
        $legacy = $this->makeUser(Role::TOURIST, [
            'password' => Hash::make('Legacy-temp-1'),
            'password_change_required' => true,
        ]);
        DB::table('users')->where('id', $legacy->id)->update(['temp_password' => 'Legacy-temp-1']);

        $this->actingAs($legacy)->get(route('cabinet.dashboard'))->assertRedirect(route('password.change'));

        $this->actingAs($legacy)->post(route('password.change.update'), [
            'current_password' => 'Legacy-temp-1',
            'password' => 'Brand-new-Pass-77!',
            'password_confirmation' => 'Brand-new-Pass-77!',
        ])->assertRedirect(route('cabinet.dashboard'));

        $fresh = $legacy->fresh();
        $this->assertFalse((bool) $fresh->password_change_required);
        $this->assertNull($fresh->temp_password);
        $this->assertTrue(Hash::check('Brand-new-Pass-77!', $fresh->password));
    }

    public function test_new_staff_client_is_not_forced_through_temporary_password_change(): void
    {
        $client = app(StaffClientAccounts::class)->createTourist('Не принуждён', 'nf@example.test');

        $this->assertFalse((bool) $client->password_change_required);
    }

    // ------------------------------------------------------------------
    // Legacy data migration
    // ------------------------------------------------------------------

    public function test_cleanup_migration_nulls_temp_password_and_changes_nothing_else(): void
    {
        $a = $this->makeUser(Role::TOURIST, ['password_change_required' => true]);
        $b = $this->makeUser(Role::TOURIST);
        $c = $this->makeUser(Role::MANAGER);
        DB::table('users')->where('id', $a->id)->update(['temp_password' => 'PlainOne1234']);
        DB::table('users')->where('id', $c->id)->update(['temp_password' => 'PlainTwo5678']);

        $before = DB::table('users')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $migration = require base_path('database/migrations/2026_10_02_000000_clear_legacy_temp_passwords_from_users_table.php');
        $migration->up();
        $migration->up(); // идемпотентна

        $after = DB::table('users')->orderBy('id')->get()->map(fn ($r) => (array) $r)->all();

        $this->assertSame(0, DB::table('users')->whereNotNull('temp_password')->count());
        $this->assertSame(3, count($after));

        foreach ($before as $i => $row) {
            $expected = $row;
            $expected['temp_password'] = null;
            $this->assertEquals($expected, $after[$i], 'only temp_password may change');
        }
        $this->assertTrue((bool) DB::table('users')->where('id', $a->id)->value('password_change_required'));
        $this->assertSame($b->password, DB::table('users')->where('id', $b->id)->value('password'));
    }

    // ------------------------------------------------------------------
    // Source guard: no active code writes a non-null temp_password
    // ------------------------------------------------------------------

    public function test_no_active_code_writes_displays_or_mails_temp_password(): void
    {
        $skipFiles = [
            // Импорт: временная колонка только перечислена в схеме/сверке (запись — null).
            'Console' . DIRECTORY_SEPARATOR . 'Commands' . DIRECTORY_SEPARATOR . 'ImportLegacyDataToV4.php',
        ];

        $offences = [];

        foreach ([base_path('app'), base_path('resources'), base_path('routes')] as $root) {
            $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DOTS));

            foreach ($iterator as $file) {
                if (! $file->isFile() || ! preg_match('/\.(php|js)$/', $file->getFilename())) {
                    continue;
                }

                foreach ($skipFiles as $skip) {
                    if (str_ends_with($file->getPathname(), $skip)) {
                        continue 2;
                    }
                }

                foreach (file($file->getPathname()) as $number => $line) {
                    if (! str_contains($line, 'temp_password') && ! str_contains($line, 'tempPassword')) {
                        continue;
                    }

                    $trimmed = ltrim($line);

                    // Комментарии допустимы; допустимо только обнуление и скрытие в User::$hidden.
                    if (str_starts_with($trimmed, '//') || str_starts_with($trimmed, '*') || str_starts_with($trimmed, '/*')) {
                        continue;
                    }
                    if (preg_match('/temp_password[\'"]?\s*(=|=>)\s*null\b/', $line)) {
                        continue;
                    }
                    if (preg_match('/^\s*\'temp_password\',\s*$/', $line)) {
                        continue; // User::$hidden
                    }

                    $offences[] = $file->getPathname() . ':' . ($number + 1) . ' ' . trim($line);
                }
            }
        }

        $this->assertSame([], $offences, "Active temp_password usage found:\n" . implode("\n", $offences));
    }

    // ------------------------------------------------------------------
    // Helpers
    // ------------------------------------------------------------------

    /** @param array<string, mixed> $attributes */
    private function makeUser(string $roleName, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $user->roles()->attach(Role::where('name', $roleName)->value('id'));

        return $user;
    }

    private function bookingFor(User $client, User $manager): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create([
            'user_id' => $client->id,
            'manager_id' => $manager->id,
            'status' => Booking::STATUS_PROGRESS,
            'departure_city' => 'Moscow',
            'destination_country' => 'Turkey',
            'destination_city' => 'Kemer',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
        ]));
    }

    /** @return array<string, mixed> */
    private function payload(string $name, ?string $email = null): array
    {
        return array_filter([
            'is_new_client' => 1,
            'client_name' => $name,
            'client_email' => $email,
            'departure_city' => 'Moscow',
            'destination_country' => 'Turkey',
            'destination_city' => 'Kemer',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
        ], fn ($v) => $v !== null);
    }
}
