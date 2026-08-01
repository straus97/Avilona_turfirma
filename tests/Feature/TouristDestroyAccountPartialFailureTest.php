<?php

namespace Tests\Feature;

use App\Models\BonusAccount;
use App\Models\Booking;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TouristDestroyAccountPartialFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_deleting_exception_preserves_tourist_booking_and_bonus_account(): void
    {
        $password = 'correct-password';

        $role = Role::query()->firstOrCreate(
            ['name' => Role::TOURIST],
            ['description' => Role::availableRoles()[Role::TOURIST]]
        );

        $tourist = User::factory()->create([
            'email' => 'partial-failure-tourist@example.com',
            'password' => bcrypt($password),
        ]);
        $tourist->roles()->attach($role->id);
        $touristId = $tourist->id;

        $booking = Booking::withoutEvents(
            fn (): Booking => Booking::query()->create([
                'user_id' => $touristId,
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
        $bookingId = $booking->id;

        $bonusAccount = BonusAccount::query()->create([
            'user_id' => $touristId,
            'balance' => 0,
            'level' => 'newbie',
            'total_earned' => 0,
            'total_spent' => 0,
            'referral_code' => strtoupper(substr(md5($touristId . '-' . uniqid()), 0, 8)),
        ]);
        $bonusAccountId = $bonusAccount->id;

        $this->assertDatabaseHas('users', ['id' => $touristId]);
        $this->assertDatabaseHas('bookings', ['id' => $bookingId]);
        $this->assertDatabaseHas('bonus_accounts', ['id' => $bonusAccountId]);

        User::deleting(function (User $user) use ($touristId): void {
            if ($user->id !== $touristId) {
                return;
            }

            throw new RuntimeException('forced tourist user deletion failure');
        });

        $this->withoutExceptionHandling();

        try {
            $this->actingAs($tourist)
                ->delete(route('cabinet.settings.destroy-account'), [
                    'password' => $password,
                ]);

            $this->fail('Expected RuntimeException was not thrown.');
        } catch (RuntimeException $e) {
            $this->assertSame('forced tourist user deletion failure', $e->getMessage());
        }

        $this->assertDatabaseHas('users', ['id' => $touristId]);

        $this->assertDatabaseHas('bookings', [
            'id' => $bookingId,
            'user_id' => $touristId,
            'deleted_at' => null,
        ]);

        $this->assertDatabaseHas('bonus_accounts', [
            'id' => $bonusAccountId,
            'user_id' => $touristId,
        ]);
    }
}
