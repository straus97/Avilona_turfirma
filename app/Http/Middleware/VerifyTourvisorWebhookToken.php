<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Секретный токен в пути callback-URL Tourvisor webhook.
 *
 * Tourvisor не подписывает webhook, поэтому единственный проверяемый сервером
 * барьер — непредсказуемый токен в URL, который мы сами задаём при регистрации
 * (TOURVISOR_WEBHOOK_TOKEN, НЕ ключ Export API). Проверка выполняется ДО любого
 * контроллера: при неверном/отсутствующем токене нет ни записи в БД, ни задач
 * очереди, ни запросов к Tourvisor.
 *
 * Fail closed: если токен не настроен или слишком слабый, не принимается ничто.
 * Ответ всегда одинаковый пустой 404 (не раскрывает, существует ли маршрут и что не так).
 * Тело пустое намеренно: HTML-страница 404 сайта выводит URL запроса (canonical/og:url),
 * то есть подставила бы токен из пути в разметку.
 * Значения токена (ожидаемое и полученное) никогда не логируются.
 *
 * Известное ограничение: токен в URL может попасть в логи веб-сервера/прокси и
 * в сохранённые настройки Tourvisor. Ротация — сменой значения и перерегистрацией.
 */
class VerifyTourvisorWebhookToken
{
    /** Допустимый формат токена: 32–128 символов URL-безопасного алфавита (напр. bin2hex(random_bytes(32))). */
    public const TOKEN_PATTERN = '/^[A-Za-z0-9_-]{32,128}$/';

    public function handle(Request $request, Closure $next): Response
    {
        $expected = config('services.tourvisor.webhook_token');
        $provided = $request->route('webhookToken');

        if (! is_string($expected) || ! preg_match(self::TOKEN_PATTERN, $expected) || ! is_string($provided)) {
            return $this->notFound();
        }

        // Сравнение хэшей: одинаковая длина и постоянное время независимо от входа.
        if (! hash_equals(hash('sha256', $expected), hash('sha256', $provided))) {
            return $this->notFound();
        }

        return $next($request);
    }

    private function notFound(): Response
    {
        return response('', 404);
    }
}
