<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TouristDestroyAccountSessionTeardownTest extends TestCase
{
    use RefreshDatabase;

    private const SENTINEL_KEY = 'tourist_delete_session_sentinel';
    private const SENTINEL_VALUE = 'must-be-cleared';

    public function test_successful_tourist_deletion_invalidates_session_and_rotates_csrf_token_but_preserves_redirect_and_flash(): void
    {
        $password = 'correct-password';
        $tourist = $this->createUserWithRole(Role::TOURIST, $password);
        $touristId = $tourist->id;
        $oldToken = str_repeat('a', 40);

        $response = $this->actingAs($tourist)
            ->withSession([
                self::SENTINEL_KEY => self::SENTINEL_VALUE,
                '_token' => $oldToken,
            ])
            ->delete(route('cabinet.settings.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertRedirect(route('home.index'));
        $response->assertSessionHas('status', 'Ваш аккаунт успешно удален.');
        $response->assertSessionMissing(self::SENTINEL_KEY);
        $response->assertSessionHas('_token', function ($token) use ($oldToken): bool {
            return is_string($token) && $token !== '' && $token !== $oldToken;
        });

        $this->assertGuest();

        $this->assertDatabaseMissing('users', ['id' => $touristId]);
    }

    public function test_incorrect_password_preserves_the_active_session_and_csrf_token(): void
    {
        $password = 'correct-password';
        $tourist = $this->createUserWithRole(Role::TOURIST, $password);
        $settingsUrl = route('cabinet.settings');
        $oldToken = str_repeat('a', 40);

        $response = $this->actingAs($tourist)
            ->withSession([
                self::SENTINEL_KEY => self::SENTINEL_VALUE,
                '_token' => $oldToken,
            ])
            ->from($settingsUrl)
            ->delete(route('cabinet.settings.destroy-account'), [
                'password' => 'wrong-password',
            ]);

        $response->assertSessionHasErrors('password');
        $response->assertRedirect($settingsUrl);
        $response->assertSessionMissing('status');
        $response->assertSessionHas(self::SENTINEL_KEY, self::SENTINEL_VALUE);
        $response->assertSessionHas('_token', $oldToken);

        $this->assertAuthenticatedAs($tourist);

        $this->assertDatabaseHas('users', ['id' => $tourist->id]);
    }

    public function test_admin_redirect_preserves_the_active_session_without_teardown(): void
    {
        $password = 'correct-password';
        $admin = $this->createUserWithRole(Role::ADMIN, $password);
        $oldToken = str_repeat('a', 40);

        $response = $this->actingAs($admin)
            ->withSession([
                self::SENTINEL_KEY => self::SENTINEL_VALUE,
                '_token' => $oldToken,
            ])
            ->delete(route('cabinet.settings.destroy-account'), [
                'password' => $password,
            ]);

        $response->assertRedirect(route('cabinet.admin.dashboard'));
        $response->assertSessionHas(self::SENTINEL_KEY, self::SENTINEL_VALUE);
        $response->assertSessionHas('_token', $oldToken);

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
}
