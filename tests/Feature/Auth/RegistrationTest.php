<?php

namespace Tests\Feature\Auth;

use App\Providers\RouteServiceProvider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegistrationTest extends TestCase
{
    use RefreshDatabase;

    public function test_registration_screen_can_be_rendered(): void
    {
        $response = $this->get('/register');

        $response->assertStatus(200);

        $content = $response->getContent();

        // Two independent password-visibility toggle buttons, one per password field.
        $this->assertSame(2, substr_count($content, 'x-bind:aria-label="showPassword'));
        $this->assertSame(2, substr_count($content, '<button type="button" @click="showPassword = !showPassword"'));
        $this->assertStringContainsString('id="password"', $content);
        $this->assertStringContainsString('id="password_confirmation"', $content);

        // Toggle for password appears before password_confirmation, keeping each scoped to its own field.
        $passwordFieldPos = strpos($content, 'id="password"');
        $confirmationFieldPos = strpos($content, 'id="password_confirmation"');
        $this->assertLessThan($confirmationFieldPos, $passwordFieldPos);
    }

    public function test_new_users_can_register(): void
    {
        $response = $this->post('/register', [
            'name' => 'Test User',
            'email' => 'test@example.com',
            'password' => 'password',
            'password_confirmation' => 'password',
            'user_agreement_accepted' => '1',
            'personal_data_consent_accepted' => '1',
        ]);

        $this->assertAuthenticated();
        $response->assertRedirect(RouteServiceProvider::HOME);
    }
}
