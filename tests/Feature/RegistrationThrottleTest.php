<?php

namespace Tests\Feature;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Valid registration payload, matching the existing baseline
     * registration test's field set.
     */
    private function validPayload(string $email): array
    {
        return [
            'name' => 'Test User',
            'email' => $email,
            'password' => 'password',
            'password_confirmation' => 'password',
        ];
    }

    /**
     * Intentionally invalid payload (password confirmation mismatch):
     * reaches the controller's validation, always fails, and never
     * creates a user or authenticates the requester — so repeating it
     * cannot itself trip the guest-middleware redirect that a
     * successful registration would cause.
     */
    private function invalidPayload(): array
    {
        return [
            'name' => 'Test User',
            'email' => 'throttle-invalid@example.com',
            'password' => 'password',
            'password_confirmation' => 'not-matching',
        ];
    }

    public function test_registration_succeeds_when_under_the_throttle_limit(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.11']);

        $response = $this->post('/register', $this->validPayload('throttle-success@example.com'));

        $response->assertRedirect(RouteServiceProvider::HOME);
        $this->assertAuthenticated();
        $this->assertDatabaseHas('users', ['email' => 'throttle-success@example.com']);
    }

    public function test_repeated_invalid_registration_attempts_are_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.12']);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->post('/register', $this->invalidPayload());

            $response->assertStatus(302);
            $response->assertSessionHasErrors('password');
        }

        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();

        $seventhResponse = $this->post('/register', $this->invalidPayload());

        $seventhResponse->assertStatus(429);
        $this->assertDatabaseCount('users', 0);
        $this->assertGuest();
    }

    public function test_registration_throttle_expires_after_the_window(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.13']);

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/register', $this->invalidPayload());
        }

        $this->post('/register', $this->invalidPayload())->assertStatus(429);

        $this->travel(61)->seconds();

        try {
            $response = $this->post('/register', $this->validPayload('throttle-window-reset@example.com'));

            $response->assertRedirect(RouteServiceProvider::HOME);
            $this->assertAuthenticated();
            $this->assertDatabaseHas('users', ['email' => 'throttle-window-reset@example.com']);
        } finally {
            $this->travelBack();
        }
    }
}
