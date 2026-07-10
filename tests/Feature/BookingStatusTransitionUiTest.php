<?php

namespace Tests\Feature;

use App\Events\BookingStatusChanged;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Event;
use Tests\TestCase;

class BookingStatusTransitionUiTest extends TestCase
{
    use RefreshDatabase;

    // -----------------------------------------------------------------------
    // 1. Edit view — status select options
    // -----------------------------------------------------------------------

    /**
     * Assigned manager editing a PROGRESS booking sees PROGRESS, CONFIRMED,
     * CANCELLED in the select, but not NEW or COMPLETED.
     */
    public function test_manager_editing_progress_booking_sees_allowed_statuses_only(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($manager)
            ->get(route('bookings.edit', $booking));

        $response->assertOk();

        // Present
        $response->assertSee('value="progress"', false);
        $response->assertSee('value="confirmed"', false);
        $response->assertSee('value="cancelled"', false);

        // Absent
        $response->assertDontSee('value="new"', false);
        $response->assertDontSee('value="completed"', false);
    }

    /**
     * Admin editing an unassigned NEW booking sees NEW and CANCELLED;
     * PROGRESS, CONFIRMED and COMPLETED must not appear (PROGRESS needs a manager).
     */
    public function test_admin_editing_unassigned_new_booking_sees_allowed_statuses_only(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_NEW);

        $response = $this->actingAs($admin)
            ->get(route('bookings.edit', $booking));

        $response->assertOk();

        // Present
        $response->assertSee('value="new"', false);
        $response->assertSee('value="cancelled"', false);

        // Absent
        $response->assertDontSee('value="progress"', false);
        $response->assertDontSee('value="confirmed"', false);
        $response->assertDontSee('value="completed"', false);
    }

    /**
     * Admin editing a COMPLETED booking sees only COMPLETED in the select,
     * while the manager notes and total price fields remain visible.
     */
    public function test_admin_editing_completed_booking_sees_only_completed_status(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_COMPLETED);

        $response = $this->actingAs($admin)
            ->get(route('bookings.edit', $booking));

        $response->assertOk();

        // Only COMPLETED in the select
        $response->assertSee('value="completed"', false);
        $response->assertDontSee('value="new"', false);
        $response->assertDontSee('value="progress"', false);
        $response->assertDontSee('value="confirmed"', false);
        $response->assertDontSee('value="cancelled"', false);

        // Manager fields still rendered
        $response->assertSee('name="manager_notes"', false);
        $response->assertSee('name="total_price"', false);
    }

    // -----------------------------------------------------------------------
    // 2. Show view — transition buttons for manager
    // -----------------------------------------------------------------------

    /**
     * Assigned manager viewing a PROGRESS booking sees Confirm and Cancel,
     * but not Complete.
     */
    public function test_manager_viewing_progress_booking_sees_confirm_and_cancel_not_complete(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($manager)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        $response->assertSee(route('bookings.confirm', $booking), false);
        $response->assertSee(route('bookings.cancel', $booking), false);
        $response->assertDontSee(route('bookings.complete', $booking), false);
    }

    /**
     * Assigned manager viewing a CONFIRMED booking sees Complete and Cancel,
     * but not Confirm.
     */
    public function test_manager_viewing_confirmed_booking_sees_complete_and_cancel_not_confirm(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_CONFIRMED);

        $response = $this->actingAs($manager)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        $response->assertSee(route('bookings.complete', $booking), false);
        $response->assertSee(route('bookings.cancel', $booking), false);
        $response->assertDontSee(route('bookings.confirm', $booking), false);
    }

    // -----------------------------------------------------------------------
    // 3. Show view — terminal bookings for admin
    // -----------------------------------------------------------------------

    /**
     * Admin viewing a CANCELLED booking sees no transition buttons,
     * but Edit and Delete remain visible.
     */
    public function test_admin_viewing_cancelled_booking_sees_no_transition_buttons(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_CANCELLED);

        $response = $this->actingAs($admin)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        // No transition action routes
        $response->assertDontSee(route('bookings.confirm', $booking), false);
        $response->assertDontSee(route('bookings.complete', $booking), false);
        $response->assertDontSee(route('bookings.cancel', $booking), false);

        // Edit and Delete still present
        $response->assertSee(route('bookings.edit', $booking), false);
        $response->assertSee(route('bookings.destroy', $booking), false);
    }

    /**
     * Admin viewing a COMPLETED booking sees no transition buttons,
     * but Edit and Delete remain visible.
     */
    public function test_admin_viewing_completed_booking_sees_no_transition_buttons(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        $booking = $this->makeBooking($owner, null, Booking::STATUS_COMPLETED);

        $response = $this->actingAs($admin)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        // No transition action routes
        $response->assertDontSee(route('bookings.confirm', $booking), false);
        $response->assertDontSee(route('bookings.complete', $booking), false);
        $response->assertDontSee(route('bookings.cancel', $booking), false);

        // Edit and Delete still present
        $response->assertSee(route('bookings.edit', $booking), false);
        $response->assertSee(route('bookings.destroy', $booking), false);
    }

    // -----------------------------------------------------------------------
    // 4. Show view — status error alert rendered after invalid action
    // -----------------------------------------------------------------------

    /**
     * After an invalid dedicated action the redirect back to the show page
     * renders a visible status validation error alert.
     */
    public function test_invalid_dedicated_action_renders_status_error_on_show_page(): void
    {
        Event::fake([BookingStatusChanged::class]);

        $owner   = $this->makeUser(Role::TOURIST);
        $admin   = $this->makeUser(Role::ADMIN);
        // CANCELLED — confirm is invalid from this state
        $booking = $this->makeBooking($owner, null, Booking::STATUS_CANCELLED);

        $response = $this->actingAs($admin)
            ->from(route('bookings.show', $booking))
            ->followingRedirects()
            ->post(route('bookings.confirm', $booking));

        $response->assertOk();
        $response->assertSee('alert-danger', false);
        $response->assertSee('Нельзя подтвердить заявку в текущем статусе', false);

        Event::assertNotDispatched(BookingStatusChanged::class);
    }

    // -----------------------------------------------------------------------
    // 5. Show view — tourist guidance
    // -----------------------------------------------------------------------

    /**
     * Tourist viewing an active assigned (PROGRESS) booking does not see
     * a cancellation button and does see the manager-contact guidance.
     */
    public function test_tourist_viewing_assigned_progress_booking_sees_manager_contact_guidance(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_PROGRESS);

        $response = $this->actingAs($owner)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        // No cancel button (policy denies it once manager is assigned)
        $response->assertDontSee(route('bookings.cancel', $booking), false);

        // Manager-contact guidance is visible
        $response->assertSee('Заявка взята в работу менеджером', false);
        $response->assertSee('свяжитесь с менеджером', false);
    }

    /**
     * Tourist viewing a terminal (CANCELLED) booking does not see the
     * active "taken into work / contact manager" guidance.
     */
    public function test_tourist_viewing_terminal_booking_does_not_see_active_guidance(): void
    {
        $owner   = $this->makeUser(Role::TOURIST);
        $manager = $this->makeUser(Role::MANAGER);
        // CANCELLED with a manager — must not show the active-state guidance
        $booking = $this->makeBooking($owner, $manager->id, Booking::STATUS_CANCELLED);

        $response = $this->actingAs($owner)
            ->get(route('bookings.show', $booking));

        $response->assertOk();

        $response->assertDontSee('Заявка взята в работу менеджером', false);
        $response->assertDontSee('свяжитесь с менеджером', false);
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

    private function makeBooking(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW,
        array $overrides = []
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create(array_merge([
                'user_id'             => $owner->id,
                'manager_id'          => $managerId,
                'status'              => $status,
                'departure_city'      => 'Moscow',
                'destination_country' => 'Turkey',
                'destination_city'    => 'Antalya',
                'start_date'          => '2026-08-15',
                'nights'              => 7,
                'adults'              => 2,
                'children'            => 0,
            ], $overrides))
        );
    }
}
