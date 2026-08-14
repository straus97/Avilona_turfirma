<?php

namespace Tests\Feature;

use App\Mail\ContactFormMail;
use App\Mail\HomeFormMail;
use App\Mail\UserFormMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Validator;
use Tests\TestCase;

/**
 * Проверяет контракт публичных форм обратной связи (контактная страница и
 * главная): сбой отправки письма после успешной валидации логируется и не
 * превращает ответ в HTTP 500, а успешный путь по-прежнему ставит оба письма
 * (сайту и пользователю) в очередь на отправку.
 *
 * Капча: продовская валидация ('captcha' => 'required|captcha') не меняется.
 * Как и в ReviewPublicCacheConsistencyTest, правило captcha детерминированно
 * стабится через Validator::extend() — это не отключает FormRequest-правило,
 * а лишь подменяет резолвер самого правила на время теста.
 */
class PublicContactMailFailureLoggingTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        Validator::extend('captcha', function () {
            return true;
        });
    }

    private function validPayload(): array
    {
        return [
            'name'    => 'Test User',
            'email'   => 'submitter@example.com',
            'subject' => 'Hello',
            'message' => str_repeat('A', 60),
            'captcha' => 'anything',
            'agree'   => '1',
            'personal_data_consent' => '1',
        ];
    }

    // -----------------------------------------------------------------------
    // 1. Сбой письма с контактной страницы логируется без потери ответа
    // -----------------------------------------------------------------------

    public function test_contact_page_mail_failure_is_logged(): void
    {
        Mail::shouldReceive('to')
            ->with('straus97@mail.ru')
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Public contact form mail send failed',
                \Mockery::on(function (array $context): bool {
                    return ($context['exception'] ?? null) === \RuntimeException::class;
                })
            );

        $this->post(route('contact.send_contact'), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('error', 'Ошибка отправки сообщения.');
    }

    // -----------------------------------------------------------------------
    // 2. Сбой письма с главной страницы логируется без потери ответа
    // -----------------------------------------------------------------------

    public function test_home_page_mail_failure_is_logged(): void
    {
        Mail::shouldReceive('to')
            ->with('straus97@mail.ru')
            ->once()
            ->andThrow(new \RuntimeException('smtp down'));

        Log::shouldReceive('error')
            ->once()
            ->with(
                'Home contact form mail send failed',
                \Mockery::on(function (array $context): bool {
                    return ($context['exception'] ?? null) === \RuntimeException::class;
                })
            );

        $this->post(route('contact.send_home'), $this->validPayload())
            ->assertRedirect()
            ->assertSessionHas('error', 'Ошибка отправки сообщения.');
    }

    // -----------------------------------------------------------------------
    // 3. Успешная отправка с контактной страницы не изменилась
    // -----------------------------------------------------------------------

    public function test_contact_page_success_behavior_is_unchanged(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->post(route('contact.send_contact'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success', 'Ваше сообщение успешно отправлено.');

        Mail::assertSent(
            ContactFormMail::class,
            fn (ContactFormMail $mail): bool => $mail->hasTo('straus97@mail.ru')
        );

        Mail::assertSent(
            UserFormMail::class,
            fn (UserFormMail $mail): bool => $mail->hasTo($payload['email'])
        );

        Mail::assertSentCount(2);
    }

    // -----------------------------------------------------------------------
    // 4. Успешная отправка с главной страницы не изменилась
    // -----------------------------------------------------------------------

    public function test_home_page_success_behavior_is_unchanged(): void
    {
        Mail::fake();

        $payload = $this->validPayload();

        $this->post(route('contact.send_home'), $payload)
            ->assertRedirect()
            ->assertSessionHas('success', 'Ваше сообщение успешно отправлено.');

        Mail::assertSent(
            HomeFormMail::class,
            fn (HomeFormMail $mail): bool => $mail->hasTo('straus97@mail.ru')
        );

        Mail::assertSent(
            UserFormMail::class,
            fn (UserFormMail $mail): bool => $mail->hasTo($payload['email'])
        );

        Mail::assertSentCount(2);
    }
}
