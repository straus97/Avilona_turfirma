<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

/**
 * Правило: сайдбар в shared-вьюхах заявок (bookings/create, bookings/edit,
 * bookings/show) должен выбираться с тем же приоритетом ролей, что уже
 * используется в остальных местах Stage 7: admin > manager > tourist.
 *
 * Пользователи с несколькими ролями — поддерживаемый сценарий, поэтому
 * admin+manager, admin+tourist и manager+tourist должны получать сайдбар
 * самой старшей из своих ролей, а не сайдбар туриста/менеджера.
 */
class BookingViewSidebarRolePrecedenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_create_view_gives_tourist_only_user_tourist_sidebar(): void
    {
        $user = $this->makeUser([Role::TOURIST]);

        $html = $this->getAsUser($user, route('bookings.create'));

        $this->assertTouristSidebar($html);
    }

    public function test_create_view_gives_manager_only_user_manager_sidebar(): void
    {
        $user = $this->makeUser([Role::MANAGER]);

        $html = $this->getAsUser($user, route('bookings.create'));

        $this->assertManagerSidebar($html);
    }

    public function test_create_view_gives_admin_only_user_admin_sidebar(): void
    {
        $user = $this->makeUser([Role::ADMIN]);

        $html = $this->getAsUser($user, route('bookings.create'));

        $this->assertAdminSidebar($html);
    }

    public function test_create_view_gives_admin_manager_user_admin_sidebar(): void
    {
        $user = $this->makeUser([Role::ADMIN, Role::MANAGER]);

        $html = $this->getAsUser($user, route('bookings.create'));

        $this->assertAdminSidebar($html);
    }

    public function test_create_view_gives_admin_tourist_user_admin_sidebar(): void
    {
        $user = $this->makeUser([Role::ADMIN, Role::TOURIST]);

        $html = $this->getAsUser($user, route('bookings.create'));

        $this->assertAdminSidebar($html);
    }

    public function test_create_view_gives_manager_tourist_user_manager_sidebar(): void
    {
        $user = $this->makeUser([Role::MANAGER, Role::TOURIST]);

        $html = $this->getAsUser($user, route('bookings.create'));

        $this->assertManagerSidebar($html);
    }

    public function test_edit_view_gives_admin_manager_user_admin_sidebar(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::MANAGER]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBookingFor($owner);

        $html = $this->getAsUser($user, route('bookings.edit', $booking));

        $this->assertAdminSidebar($html);
    }

    public function test_edit_view_gives_admin_tourist_user_admin_sidebar(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::TOURIST]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBookingFor($owner);

        $html = $this->getAsUser($user, route('bookings.edit', $booking));

        $this->assertAdminSidebar($html);
    }

    public function test_edit_view_gives_manager_tourist_user_manager_sidebar(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBookingFor($user, $user->id);

        $html = $this->getAsUser($user, route('bookings.edit', $booking));

        $this->assertManagerSidebar($html);
    }

    public function test_show_view_gives_admin_manager_user_admin_sidebar(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::MANAGER]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBookingFor($owner);

        $html = $this->getAsUser($user, route('bookings.show', $booking));

        $this->assertAdminSidebar($html);
    }

    public function test_show_view_gives_admin_tourist_user_admin_sidebar(): void
    {
        $user    = $this->makeUser([Role::ADMIN, Role::TOURIST]);
        $owner   = $this->makeUser([Role::TOURIST]);
        $booking = $this->makeBookingFor($owner);

        $html = $this->getAsUser($user, route('bookings.show', $booking));

        $this->assertAdminSidebar($html);
    }

    public function test_show_view_gives_manager_tourist_user_manager_sidebar(): void
    {
        $user    = $this->makeUser([Role::MANAGER, Role::TOURIST]);
        $booking = $this->makeBookingFor($user, $user->id);

        $html = $this->getAsUser($user, route('bookings.show', $booking));

        $this->assertManagerSidebar($html);
    }

    // -----------------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------------

    private function getAsUser(User $user, string $url): string
    {
        $response = $this->actingAs($user)->get($url);
        $response->assertOk();

        return $response->getContent();
    }

    private function assertAdminSidebar(string $html): void
    {
        $this->assertStringContainsString('href="' . route('cabinet.admin.users') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.clients') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.wishlist') . '"', $html);
    }

    private function assertManagerSidebar(string $html): void
    {
        $this->assertStringContainsString('href="' . route('cabinet.manager.clients') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.admin.users') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.wishlist') . '"', $html);
    }

    private function assertTouristSidebar(string $html): void
    {
        $this->assertStringContainsString('href="' . route('cabinet.wishlist') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.admin.users') . '"', $html);
        $this->assertStringNotContainsString('href="' . route('cabinet.manager.clients') . '"', $html);
    }

    private function makeUser(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $name) {
            $role = Role::query()->firstOrCreate(
                ['name' => $name],
                ['description' => Role::availableRoles()[$name] ?? $name]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function makeBookingFor(
        User $owner,
        ?int $managerId = null,
        string $status = Booking::STATUS_NEW
    ): Booking {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
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
            ])
        );
    }
}
