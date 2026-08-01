<?php

namespace Tests\Feature;

use App\Models\BonusAccount;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

class TouristDestroyAccountAuditLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_tourist_only_self_deletion_emits_the_audit_log_exactly_once_with_expected_context(): void
    {
        $password = 'correct-password';
        $tourist = $this->createUserWithRole(Role::TOURIST, $password);
        $touristId = $tourist->id;

        $booking = $this->createBookingFor($tourist);
        $bookingId = $booking->id;

        $bonusAccount = $this->createBonusAccountFor($tourist);
        $bonusAccountId = $bonusAccount->id;

        Log::shouldReceive('warning')
            ->once()
            ->with(
                'Tourist deleted own account',
                \Mockery::on(function ($context) use ($touristId): bool {
                    if (!is_array($context)) {
                        return false;
                    }

                    if (array_keys($context) !== ['actor_id']) {
                        return false;
                    }

                    return $context['actor_id'] === $touristId;
                })
            );

        $response = $this->actingAs($tourist)
            ->delete(route('cabinet.settings.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertRedirect(route('home.index'));
        $response->assertSessionHas('status', 'Ваш аккаунт успешно удален.');

        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['id' => $touristId]);
        $this->assertDatabaseMissing('bookings', ['id' => $bookingId]);
        $this->assertDatabaseMissing('bonus_accounts', ['id' => $bonusAccountId]);
    }

    public function test_incorrect_password_does_not_emit_the_audit_log_and_does_not_delete_the_account(): void
    {
        $password = 'correct-password';
        $tourist = $this->createUserWithRole(Role::TOURIST, $password);
        $settingsUrl = route('cabinet.settings');

        $booking = $this->createBookingFor($tourist);
        $bookingId = $booking->id;

        $bonusAccount = $this->createBonusAccountFor($tourist);
        $bonusAccountId = $bonusAccount->id;

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($tourist)
            ->from($settingsUrl)
            ->delete(route('cabinet.settings.destroy-account'), [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('password');
        $response->assertRedirect($settingsUrl);

        $this->assertAuthenticatedAs($tourist);

        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId]);
        $this->assertDatabaseHas('bonus_accounts', ['id' => $bonusAccountId]);
    }

    public function test_admin_only_actor_is_redirected_before_reaching_the_tourist_deletion_audit_log(): void
    {
        $password = 'correct-password';
        $admin = $this->createUserWithRole(Role::ADMIN, $password);

        Log::shouldReceive('warning')->never();

        $response = $this->actingAs($admin)
            ->delete(route('cabinet.settings.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertRedirect(route('cabinet.admin.dashboard'));

        $this->assertAuthenticatedAs($admin);

        $this->assertDatabaseHas('users', ['id' => $admin->id]);
    }

    private function createUserWithRole(string $roleName, string $password): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => $roleName],
            ['description' => Role::availableRoles()[$roleName] ?? $roleName]
        );

        $user = User::factory()->create([
            'password' => bcrypt($password),
        ]);

        $user->roles()->attach($role->id);

        return $user;
    }

    private function createBookingFor(User $tourist): Booking
    {
        return Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id' => $tourist->id,
                'status' => Booking::STATUS_NEW,
                'departure_city' => 'Saint Petersburg',
                'destination_country' => 'Tunisia',
                'destination_city' => 'Hammamet',
                'start_date' => '2026-08-20',
                'nights' => 7,
                'adults' => 2,
                'children' => 0,
            ])
        );
    }

    private function createBonusAccountFor(User $tourist): BonusAccount
    {
        return BonusAccount::query()->create([
            'user_id' => $tourist->id,
            'balance' => 0,
            'level' => 'newbie',
            'total_earned' => 0,
            'total_spent' => 0,
            'referral_code' => strtoupper(substr(md5($tourist->id . '-' . uniqid()), 0, 8)),
        ]);
    }
}
