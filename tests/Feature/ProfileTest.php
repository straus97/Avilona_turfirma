<?php

namespace Tests\Feature;

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProfileTest extends TestCase
{
    use RefreshDatabase;

    public function test_profile_page_is_displayed(): void
    {
        $user = $this->createTourist();

        $response = $this
            ->actingAs($user)
            ->get(route('cabinet.profile'));

        $response->assertOk();
    }

    public function test_profile_information_can_be_updated(): void
    {
        $user = $this->createTourist();

        $response = $this
            ->actingAs($user)
            ->patch(route('cabinet.profile.update'), [
                'name' => 'Test User',
                'email' => 'test@example.com',
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('login'));

        $this->assertGuest();

        $user->refresh();

        $this->assertSame('Test User', $user->name);
        $this->assertSame('test@example.com', $user->email);
        $this->assertNull($user->email_verified_at);
    }

    public function test_email_verification_status_is_unchanged_when_the_email_address_is_unchanged(): void
    {
        $user = $this->createTourist();

        $response = $this
            ->actingAs($user)
            ->patch(route('cabinet.profile.update'), [
                'name' => 'Test User',
                'email' => $user->email,
            ]);

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('cabinet.profile'));

        $this->assertAuthenticatedAs($user);
        $this->assertNotNull($user->refresh()->email_verified_at);
    }

    public function test_user_can_delete_their_account(): void
    {
        $user = $this->createTourist();

        $response = $this
            ->actingAs($user)
            ->delete(
                route('cabinet.settings.destroy-account'),
                ['password' => 'password']
            );

        $response
            ->assertSessionHasNoErrors()
            ->assertRedirect(route('home.index'));

        $this->assertGuest();

        $this->assertDatabaseMissing('users', [
            'id' => $user->id,
        ]);
    }

    public function test_correct_password_must_be_provided_to_delete_account(): void
    {
        $user = $this->createTourist();
        $settingsUrl = route('cabinet.settings');

        $response = $this
            ->actingAs($user)
            ->from($settingsUrl)
            ->delete(
                route('cabinet.settings.destroy-account'),
                ['password' => 'wrong-password']
            );

        $response
            ->assertSessionHasErrors('password')
            ->assertRedirect($settingsUrl);

        $this->assertAuthenticatedAs($user);

        $this->assertDatabaseHas('users', [
            'id' => $user->id,
        ]);
    }

    private function createTourist(array $attributes = []): User
    {
        $role = Role::query()->firstOrCreate(
            ['name' => Role::TOURIST],
            [
                'description' =>
                    Role::availableRoles()[Role::TOURIST],
            ]
        );

        $user = User::factory()->create($attributes);

        $user->roles()->attach($role->id);

        return $user;
    }
}
