<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Mail\AdminBookingCreated;
use App\Mail\BookingCreated as BookingCreatedMail;
use App\Models\Booking;
use App\Models\DestinationCity;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Tests\TestCase;

class BookingCreationOwnershipAndAtomicityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        // No mail ever leaves the process in this suite.
        Mail::fake();

        // The application roles exist in every environment; a request must not
        // depend on the test happening to create a user of that role first.
        foreach ([Role::ADMIN, Role::MANAGER, Role::TOURIST] as $roleName) {
            Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );
        }
    }

    // -----------------------------------------------------------------------
    // 1. Tourist ownership
    // -----------------------------------------------------------------------

    public function test_tourist_booking_is_owned_by_the_tourist_with_no_manager_and_new_status(): void
    {
        Event::fake([BookingCreated::class]);

        $tourist = $this->makeUser(Role::TOURIST);

        $this->actingAs($tourist)
            ->post(route('bookings.store'), $this->validPayload())
            ->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame($tourist->id, $booking->user_id);
        $this->assertNull($booking->manager_id);
        $this->assertSame(Booking::STATUS_NEW, $booking->status);

        Event::assertDispatched(BookingCreated::class, 1);
    }

    public function test_tourist_cannot_forge_another_owner_through_client_fields(): void
    {
        Event::fake([BookingCreated::class]);

        $tourist = $this->makeUser(Role::TOURIST);
        $victim  = $this->makeUser(Role::TOURIST);

        $this->actingAs($tourist)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 0,
                'client_id'     => $victim->id,
                'client_name'   => 'Подставной Клиент',
                'client_email'  => 'forged@example.com',
            ]))
            ->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame($tourist->id, $booking->user_id);
        $this->assertNull($booking->manager_id);
        $this->assertDatabaseMissing('users', ['email' => 'forged@example.com']);
    }

    // -----------------------------------------------------------------------
    // 2. Roleless actor
    // -----------------------------------------------------------------------

    public function test_authenticated_user_without_any_role_cannot_create_booking(): void
    {
        Event::fake([BookingCreated::class]);

        $roleless = User::factory()->create();

        $before = $this->baselineCounts();

        $this->actingAs($roleless)
            ->post(route('bookings.store'), $this->validPayload())
            ->assertForbidden();

        $this->assertDatabaseCount('bookings', 0);
        $this->assertSame($before, $this->baselineCounts());

        Event::assertNotDispatched(BookingCreated::class);
    }

    // -----------------------------------------------------------------------
    // 3. Staff creation for an existing client
    // -----------------------------------------------------------------------

    public function test_admin_creates_booking_for_existing_tourist(): void
    {
        Event::fake([BookingCreated::class]);

        $admin   = $this->makeUser(Role::ADMIN);
        $tourist = $this->makeUser(Role::TOURIST);

        $this->actingAs($admin)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $tourist->id,
            ]))
            ->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame($tourist->id, $booking->user_id);
        $this->assertNull($booking->manager_id);
        $this->assertSame(Booking::STATUS_NEW, $booking->status);
    }

    public function test_manager_creates_booking_for_existing_tourist_with_self_assignment_and_progress_status(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);
        $tourist = $this->makeUser(Role::TOURIST);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $tourist->id,
            ]))
            ->assertRedirect();

        $booking = Booking::query()->sole();

        $this->assertSame($tourist->id, $booking->user_id);
        $this->assertSame($manager->id, $booking->manager_id);
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);

        // The redirect target must be reachable for the creating manager.
        $this->actingAs($manager)
            ->get(route('bookings.show', $booking))
            ->assertOk();
    }

    public function test_existing_client_creation_does_not_write_an_extra_role_pivot_row(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);
        $tourist = $this->makeUser(Role::TOURIST);

        $pivotBefore = DB::table('role_user')->count();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $tourist->id,
            ]))
            ->assertRedirect();

        $this->assertSame($pivotBefore, DB::table('role_user')->count());
    }

    // -----------------------------------------------------------------------
    // 4. Forged / invalid client choices
    // -----------------------------------------------------------------------

    public function test_manager_cannot_select_another_manager_as_booking_owner(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);
        $other   = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $other->id,
            ]))
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount('bookings', 0);
        Event::assertNotDispatched(BookingCreated::class);
    }

    public function test_admin_cannot_select_an_administrator_as_booking_owner(): void
    {
        Event::fake([BookingCreated::class]);

        $admin      = $this->makeUser(Role::ADMIN);
        $otherAdmin = $this->makeUser(Role::ADMIN);

        $this->actingAs($admin)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $otherAdmin->id,
            ]))
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_manager_cannot_select_an_inactive_tourist_as_booking_owner(): void
    {
        Event::fake([BookingCreated::class]);

        $manager  = $this->makeUser(Role::MANAGER);
        $inactive = $this->makeUser(Role::TOURIST);
        $inactive->forceFill(['is_active' => false])->save();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $inactive->id,
            ]))
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount('bookings', 0);
    }

    public function test_staff_request_without_any_client_choice_is_rejected(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload())
            ->assertSessionHasErrors('client_id');

        $this->assertDatabaseCount('bookings', 0);
        Event::assertNotDispatched(BookingCreated::class);
    }

    public function test_new_client_mode_without_a_name_is_rejected(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
            ]))
            ->assertSessionHasErrors('client_name');

        $this->assertDatabaseCount('bookings', 0);
    }

    // -----------------------------------------------------------------------
    // 5. New client creation
    // -----------------------------------------------------------------------

    public function test_new_client_receives_the_tourist_role_and_secure_temporary_password(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
                'client_email'  => 'client@example.com',
            ]))
            ->assertRedirect();

        $client = User::query()->where('email', 'client@example.com')->sole();

        $this->assertTrue($client->hasRole(Role::TOURIST));
        $this->assertTrue((bool) $client->password_change_required);
        $this->assertNotEmpty($client->temp_password);
        $this->assertSame(12, strlen($client->temp_password));
        $this->assertDoesNotMatchRegularExpression('/^AV\d{4}[0-9A-F]{4}$/', $client->temp_password);
    }

    public function test_new_client_booking_created_by_manager_gets_manager_and_progress_status(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
            ]))
            ->assertRedirect();

        $booking = Booking::query()->sole();
        $client  = User::query()->where('name', 'Новый Клиент')->sole();

        $this->assertSame($client->id, $booking->user_id);
        $this->assertSame($manager->id, $booking->manager_id);
        $this->assertSame(Booking::STATUS_PROGRESS, $booking->status);
    }

    public function test_generated_technical_emails_do_not_collide(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        for ($i = 1; $i <= 2; $i++) {
            $this->actingAs($manager)
                ->post(route('bookings.store'), $this->validPayload([
                    'is_new_client' => 1,
                    'client_name'   => 'Клиент ' . $i,
                ]))
                ->assertRedirect();
        }

        $emails = User::query()
            ->where('email', 'like', '%@' . User::TECHNICAL_EMAIL_DOMAIN)
            ->pluck('email');

        $this->assertCount(2, $emails);
        $this->assertCount(2, $emails->unique());
        $this->assertDatabaseCount('bookings', 2);
    }

    // -----------------------------------------------------------------------
    // 6. Rollback
    // -----------------------------------------------------------------------

    public function test_missing_tourist_role_rolls_back_every_record_of_the_request(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        // Remove the tourist role entirely.
        Role::query()->where('name', Role::TOURIST)->forceDelete();

        $before = $this->baselineCounts();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('booking');

        $this->assertSame($before, $this->baselineCounts());
        $this->assertDatabaseMissing('users', ['name' => 'Новый Клиент']);
        $this->assertDatabaseMissing('destination_cities', ['country' => 'Turkey', 'city' => 'Kemer']);
        $this->assertDatabaseCount('bookings', 0);

        Event::assertNotDispatched(BookingCreated::class);
    }

    public function test_failure_after_all_writes_rolls_back_user_pivot_city_and_booking(): void
    {
        Event::fake([BookingCreated::class]);

        // Fires inside the transaction, after the user, the role pivot and the
        // booking row have been written. Booking model events are suppressed by
        // withoutEvents, DestinationCity events are not.
        Event::listen(
            'eloquent.created: ' . DestinationCity::class,
            fn (): never => throw new \RuntimeException('forced failure')
        );

        $manager = $this->makeUser(Role::MANAGER);

        $before = $this->baselineCounts();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
            ]))
            ->assertRedirect()
            ->assertSessionHasErrors('booking');

        $this->assertSame($before, $this->baselineCounts());
        $this->assertDatabaseMissing('users', ['name' => 'Новый Клиент']);
        $this->assertDatabaseMissing('destination_cities', ['country' => 'Turkey', 'city' => 'Kemer']);
        $this->assertDatabaseCount('bookings', 0);

        Event::assertNotDispatched(BookingCreated::class);
    }

    // -----------------------------------------------------------------------
    // 7. Notification routing
    // -----------------------------------------------------------------------

    public function test_real_client_email_receives_the_booking_created_mail(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $this->makeUser(Role::ADMIN);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
                'client_email'  => 'client@example.com',
            ]))
            ->assertRedirect();

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo('client@example.com')
        );
        Mail::assertQueued(AdminBookingCreated::class);
    }

    public function test_generated_technical_email_receives_no_credentials_mail(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $this->makeUser(Role::ADMIN);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
            ]))
            ->assertRedirect();

        Mail::assertNotQueued(BookingCreatedMail::class);
        Mail::assertQueued(AdminBookingCreated::class);
    }

    public function test_legacy_technical_email_receives_no_credentials_mail(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $this->makeUser(Role::ADMIN);

        $legacyClient = $this->makeUser(Role::TOURIST);
        $legacyClient->forceFill(['email' => 'temp_1736600000@avilona.ru'])->save();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $legacyClient->id,
            ]))
            ->assertRedirect();

        Mail::assertNotQueued(BookingCreatedMail::class);
        Mail::assertQueued(AdminBookingCreated::class);
    }

    public function test_real_avilona_address_is_not_treated_as_technical(): void
    {
        $manager = $this->makeUser(Role::MANAGER);
        $this->makeUser(Role::ADMIN);

        $client = $this->makeUser(Role::TOURIST);
        $client->forceFill(['email' => 'ivanov@avilona.ru'])->save();

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $client->id,
            ]))
            ->assertRedirect();

        Mail::assertQueued(
            BookingCreatedMail::class,
            fn (BookingCreatedMail $mail): bool => $mail->hasTo('ivanov@avilona.ru')
        );
    }

    public function test_notification_failure_after_commit_keeps_the_booking(): void
    {
        Event::listen(
            BookingCreated::class,
            fn (): never => throw new \RuntimeException('notification exploded')
        );

        $tourist = $this->makeUser(Role::TOURIST);

        $response = $this->actingAs($tourist)
            ->post(route('bookings.store'), $this->validPayload());

        $booking = Booking::query()->sole();

        $response->assertRedirect(route('bookings.show', $booking));
        $response->assertSessionHasNoErrors();

        $this->assertSame($tourist->id, $booking->user_id);
    }

    // -----------------------------------------------------------------------
    // 8. Notes
    // -----------------------------------------------------------------------

    public function test_notes_for_a_client_with_email_record_the_intended_recipient(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
                'client_email'  => 'client@example.com',
            ]))
            ->assertRedirect();

        $notes = Booking::query()->sole()->notes;

        $this->assertStringContainsString(
            'Клиент создан автоматически. Email для отправки данных для входа: client@example.com.',
            $notes
        );
    }

    public function test_notes_for_a_client_without_email_do_not_claim_credentials_were_sent(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'is_new_client' => 1,
                'client_name'   => 'Новый Клиент',
            ]))
            ->assertRedirect();

        $notes = Booking::query()->sole()->notes;

        $this->assertStringContainsString(
            'Клиент создан автоматически. Email не указан. Данные для входа автоматически не отправлялись.',
            $notes
        );
        $this->assertStringNotContainsString('Отправлены данные для входа', $notes);
    }

    public function test_notes_for_an_existing_client_are_preserved_exactly(): void
    {
        Event::fake([BookingCreated::class]);

        $manager = $this->makeUser(Role::MANAGER);
        $tourist = $this->makeUser(Role::TOURIST);

        $submitted = 'Отель 5*, первая линия.';

        $this->actingAs($manager)
            ->post(route('bookings.store'), $this->validPayload([
                'client_id' => $tourist->id,
                'notes'     => $submitted,
            ]))
            ->assertRedirect();

        $this->assertSame($submitted, Booking::query()->sole()->notes);
    }

    // -----------------------------------------------------------------------
    // 9. Create-form initial mode synchronization (source regression)
    // -----------------------------------------------------------------------

    public function test_create_form_synchronizes_client_mode_on_every_initial_load(): void
    {
        $manager = $this->makeUser(Role::MANAGER);

        $response = $this->actingAs($manager)->get(route('bookings.create'));

        $response->assertOk();

        // The initial sync runs unconditionally, in both client modes.
        $response->assertSee('syncClientMode(false);', false);

        // The old "sync only when the checkbox is checked" dispatch is gone.
        $response->assertDontSee('isNewClientCheckbox.dispatchEvent', false);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function makeUser(string $roleName): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create();
        $user->roles()->attach($role->id);

        return $user;
    }

    /**
     * @param array<string, mixed> $overrides
     * @return array<string, mixed>
     */
    private function validPayload(array $overrides = []): array
    {
        return array_merge([
            'departure_city'      => 'Moscow',
            'destination_country' => 'Turkey',
            'destination_city'    => 'Kemer',
            'start_date'          => now()->addMonth()->toDateString(),
            'nights'              => 7,
            'adults'              => 2,
            'children'            => 0,
        ], $overrides);
    }

    /**
     * @return array<string, int>
     */
    private function baselineCounts(): array
    {
        return [
            'users'              => User::query()->count(),
            'role_user'          => DB::table('role_user')->count(),
            'destination_cities' => DestinationCity::query()->count(),
        ];
    }
}
