<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Notification;
use Tests\TestCase;

class PasswordResetLinkThrottleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Intentionally invalid payload (malformed email): reaches the
     * controller's validation, always fails before Password::sendResetLink()
     * runs, and so can never itself trigger a ResetPassword notification —
     * repeating it exercises only the throttle middleware's request count.
     */
    private function invalidPayload(): array
    {
        return [
            'email' => 'not-an-email',
        ];
    }

    public function test_password_reset_link_request_succeeds_when_under_the_throttle_limit(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.21']);
        Notification::fake();

        $user = User::factory()->create();

        $response = $this->post('/forgot-password', ['email' => $user->email]);

        $response->assertStatus(302);
        $response->assertSessionHas('status');
        Notification::assertSentTo($user, ResetPassword::class);
    }

    public function test_repeated_invalid_password_reset_link_requests_are_rate_limited(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.22']);
        Notification::fake();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $response = $this->post('/forgot-password', $this->invalidPayload());

            $response->assertStatus(302);
            $response->assertSessionHasErrors('email');
        }

        Notification::assertNothingSent();
        $this->assertGuest();

        $seventhResponse = $this->post('/forgot-password', $this->invalidPayload());

        $seventhResponse->assertStatus(429);
        Notification::assertNothingSent();
        $this->assertGuest();
    }

    public function test_password_reset_link_throttle_expires_after_the_window(): void
    {
        $this->withServerVariables(['REMOTE_ADDR' => '203.0.113.23']);
        Notification::fake();

        $user = User::factory()->create();

        for ($attempt = 1; $attempt <= 6; $attempt++) {
            $this->post('/forgot-password', $this->invalidPayload());
        }

        $this->post('/forgot-password', $this->invalidPayload())->assertStatus(429);

        $this->travel(61)->seconds();

        try {
            $response = $this->post('/forgot-password', ['email' => $user->email]);

            $response->assertStatus(302);
            $response->assertSessionHas('status');
            Notification::assertSentToTimes($user, ResetPassword::class, 1);
            $this->assertGuest();
        } finally {
            $this->travelBack();
        }
    }
}
