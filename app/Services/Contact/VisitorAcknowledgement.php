<?php

namespace App\Services\Contact;

use App\Mail\UserFormMail;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\RateLimiter;

/**
 * NOV-PUBLIC-02: безопасная отправка автоответа посетителю формы обратной связи.
 *
 * Автоответ уходит на адрес, который ввёл сам посетитель, поэтому форма могла
 * использоваться как рассылка от имени компании на чужие адреса (отражение +
 * усиление спама). Оставлено наименьшее изменение, сохраняющее бизнес-процесс
 * (внутреннее уведомление staff и автоответ клиенту по-прежнему отправляются):
 *   - письмо содержит только фиксированный текст; произвольный ввод посетителя
 *     (имя) попадает в приветствие, только если похож на имя (см. UserFormMail);
 *   - на один адрес получателя — не более MAX_PER_HOUR автоответов в час
 *     (поверх капчи и throttle по IP); лишние тихо пропускаются;
 *   - сбой автоответа не должен превращать уже доставленную заявку в «ошибку
 *     отправки» (посетитель отправил бы её повторно) — логируется только класс
 *     исключения, без адреса и текста.
 * Вопрос «нужен ли автоответ вообще» — решение владельца (см. NOV-PUBLIC-02).
 */
class VisitorAcknowledgement
{
    public const MAX_PER_HOUR = 2;

    public static function send(array $validatedData): void
    {
        $email = strtolower(trim((string) ($validatedData['email'] ?? '')));

        if ($email === '') {
            return;
        }

        $key = 'contact-ack:' . sha1($email);

        if (RateLimiter::tooManyAttempts($key, self::MAX_PER_HOUR)) {
            return;
        }

        RateLimiter::hit($key, 3600);

        try {
            Mail::to($validatedData['email'])->send(new UserFormMail($validatedData));
        } catch (\Throwable $e) {
            Log::warning('Visitor acknowledgement mail failed', ['exception' => get_class($e)]);
        }
    }
}
