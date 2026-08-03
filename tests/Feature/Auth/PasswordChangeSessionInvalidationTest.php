<?php

namespace Tests\Feature\Auth;

use App\Models\User;
use App\Providers\RouteServiceProvider;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Notification;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class PasswordChangeSessionInvalidationTest extends TestCase
{
    use RefreshDatabase;

    private const INVALID_SESSION_COOKIE_VALUE = 'not-a-real-encrypted-session-cookie';

    public function test_authenticated_password_update_keeps_current_session_and_invalidates_other_primed_session(): void
    {
        $cookieName = config('session.cookie');
        $oldPassword = 'old-password-A1';
        $newPassword = 'new-password-B2';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
            'password_change_required' => false,
        ]);

        $cookieA = $this->loginAsNewSession($cookieName, $user->email, $oldPassword);
        $cookieB = $this->loginAsNewSession($cookieName, $user->email, $oldPassword);

        $this->primeSession($cookieName, $cookieA)->assertOk();
        $this->primeSession($cookieName, $cookieB)->assertOk();

        $this->useSession($cookieName, $cookieA);

        $updateResponse = $this->put('/password', [
            'current_password' => $oldPassword,
            'password' => $newPassword,
            'password_confirmation' => $newPassword,
        ]);

        $updateResponse->assertSessionHasNoErrors();

        $cookieA = $this->captureSessionCookie($updateResponse, $cookieName) ?? $cookieA;

        $this->useSession($cookieName, $cookieA);
        $probeA = $this->get(route('home.index'));
        $probeA->assertOk();
        $this->assertAuthenticatedAs($user->fresh());

        $this->useSession($cookieName, $cookieB);
        $probeB = $this->get(route('home.index'));
        $probeB->assertRedirect(route('login'));
        $this->assertGuest();

        $this->assertTrue(Hash::check($newPassword, $user->fresh()->password));
    }

    public function test_forgot_password_reset_invalidates_primed_authenticated_session(): void
    {
        $cookieName = config('session.cookie');
        $oldPassword = 'old-password-C3';
        $newPassword = 'new-password-D4';

        $user = User::factory()->create([
            'password' => Hash::make($oldPassword),
            'password_change_required' => false,
        ]);

        $cookieB = $this->loginAsNewSession($cookieName, $user->email, $oldPassword);
        $this->primeSession($cookieName, $cookieB)->assertOk();

        Notification::fake();

        $this->forceGuestCookie($cookieName);

        $forgotPasswordResponse = $this->post('/forgot-password', ['email' => $user->email]);

        $forgotPasswordResponse->assertSessionHasNoErrors();
        $forgotPasswordResponse->assertSessionHas('status');

        Notification::assertSentTo($user, ResetPassword::class, function ($notification) use ($user, $newPassword, $cookieName) {
            $this->forceGuestCookie($cookieName);

            $resetResponse = $this->post('/reset-password', [
                'token' => $notification->token,
                'email' => $user->email,
                'password' => $newPassword,
                'password_confirmation' => $newPassword,
            ]);

            $resetResponse->assertSessionHasNoErrors();
            $resetResponse->assertRedirect(route('login'));

            return true;
        });

        $this->useSession($cookieName, $cookieB);
        $probeB = $this->get(route('home.index'));
        $probeB->assertRedirect(route('login'));
        $this->assertGuest();

        $this->forceGuestCookie($cookieName);

        $loginResponse = $this->post('/login', [
            'email' => $user->email,
            'password' => $newPassword,
        ]);

        $this->assertAuthenticated();
        $loginResponse->assertRedirect(RouteServiceProvider::HOME);
    }

    /**
     * Switch to a specific virtual browser session: forget the resolved
     * guard instance so a stale in-memory user cannot leak into the next
     * request, flush the shared in-memory session store (Laravel's
     * Store::loadSession() merges freshly-read handler data into whatever
     * is already in $attributes rather than replacing it, and the same
     * Store instance is reused for every simulated request within one test
     * method, so a previous session's keys such as login_web_* otherwise
     * survive the switch even though setId() points at a different id),
     * then set the raw (already-encrypted) session cookie for that session.
     * flushSession() only clears this transient in-memory buffer; each
     * session's own data was already durably written to the session
     * handler at the end of its prior request and is unaffected.
     */
    private function useSession(string $cookieName, string $cookieValue): void
    {
        Auth::forgetGuards();

        $this->flushSession();

        $this->withUnencryptedCookie($cookieName, $cookieValue);
    }

    /**
     * Force the next request to be treated as a guest by replacing the
     * session cookie with a value that cannot decrypt to a real session.
     */
    private function forceGuestCookie(string $cookieName): void
    {
        $this->useSession($cookieName, self::INVALID_SESSION_COOKIE_VALUE);
    }

    /**
     * Log the given credentials in as a brand new, independent virtual
     * browser session and return the raw session cookie value issued for it.
     */
    private function loginAsNewSession(string $cookieName, string $email, string $password): string
    {
        $this->forceGuestCookie($cookieName);

        $response = $this->post('/login', [
            'email' => $email,
            'password' => $password,
        ]);

        $response->assertRedirect(RouteServiceProvider::HOME);

        $cookieValue = $this->captureSessionCookie($response, $cookieName);

        $this->assertNotNull($cookieValue, 'Login response did not return a session cookie.');

        return $cookieValue;
    }

    /**
     * Prime a session through a real, unauthenticated-safe web route so
     * AuthenticateSession stores its current password hash.
     */
    private function primeSession(string $cookieName, string $cookieValue): TestResponse
    {
        $this->useSession($cookieName, $cookieValue);

        return $this->get(route('home.index'));
    }

    /**
     * Find the raw (already-encrypted) session cookie value issued on a
     * response, matching the repository's configured session cookie name.
     */
    private function captureSessionCookie(TestResponse $response, string $cookieName): ?string
    {
        foreach ($response->headers->getCookies() as $cookie) {
            if ($cookie->getName() === $cookieName) {
                return $cookie->getValue();
            }
        }

        return null;
    }
}
