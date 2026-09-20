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

    public function test_login_register_and_forgot_password_have_main_landmark_and_single_h1(): void
    {
        foreach (['/login' => 'Вход', '/register' => 'Регистрация', '/forgot-password' => 'Восстановление пароля'] as $url => $heading) {
            $content = $this->get($url)->assertOk()->getContent();

            $this->assertSame(1, substr_count($content, '<main'), $url);
            $this->assertSame(1, preg_match_all('/<h1[\s>]/', $content), $url);
            $this->assertStringContainsString($heading, $content, $url);
        }
    }

    public function test_custom_password_reveal_fields_are_marked_for_native_reveal_suppression(): void
    {
        $login = $this->get('/login')->getContent();
        $register = $this->get('/register')->getContent();

        $this->assertSame(1, preg_match_all('/<input[^>]*data-custom-reveal/s', $login));
        $this->assertSame(2, preg_match_all('/<input[^>]*data-custom-reveal/s', $register));
        $this->assertStringContainsString('input[data-custom-reveal]::-ms-reveal', $login);
    }

    public function test_registration_validation_errors_use_russian_attribute_names(): void
    {
        $response = $this->from('/register')->post('/register', [
            'name' => '',
            'email' => '',
            'password' => 'password',
            'password_confirmation' => 'password',
        ]);

        $response->assertSessionHasErrors(['name', 'email']);

        $errors = session('errors')->getBag('default');
        $this->assertStringContainsString('имя', $errors->first('name'));
        $this->assertStringContainsString('электронная почта', $errors->first('email'));
        $this->assertStringNotContainsString('name', $errors->first('name'));
        $this->assertStringNotContainsString('email', $errors->first('email'));
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
