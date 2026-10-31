<?php

namespace Tests\Feature;

use App\Models\BonusAccount;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use RuntimeException;
use Tests\TestCase;

class TouristDestroyAccountPartialFailureTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_deleting_exception_preserves_tourist_account_bonus_account_and_audit_state(): void
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

        $this->assertDatabaseHas('bonus_accounts', [
            'id' => $bonusAccountId,
            'user_id' => $touristId,
        ]);
        $this->assertDatabaseMissing('audit_events', ['event_type' => 'user.deleted']);
    }
}
