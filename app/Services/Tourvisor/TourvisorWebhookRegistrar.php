<?php

namespace App\Services\Tourvisor;

use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Response;
use Illuminate\Support\Facades\Http;

/**
 * Серверный клиент регистрации/снятия webhook Tourvisor (экспорт заявок).
 *
 * Границы безопасности (тот же принцип, что и TourvisorExportClient):
 *  - authkey и токен callback-пути берутся только из config('services.tourvisor.*');
 *  - базовый URL зафиксирован в коде, из запроса не принимается;
 *  - все ошибки приводятся к TourvisorWebhookRegistrationException с фиксированным
 *    кодом, исходные исключения HTTP-клиента не пробрасываются (их текст может
 *    содержать тело запроса/URL с секретами);
 *  - тело запроса и ответа никогда не логируется и не попадает в сообщения.
 *
 * Контракт (официальный, из задачи E5-A2B, живой ответ не проверен):
 *  POST https://tourvisor.ru/xml/webhooks.php
 *  JSON-тело: {"authkey": "...", "url": "..."}
 *  Снятие — тот же запрос с пустым "url".
 */
class TourvisorWebhookRegistrar
{
    private const WEBHOOKS_URL = 'https://tourvisor.ru/xml/webhooks.php';

    /**
     * Зарегистрировать текущий callback-URL (маршрут webhooks.tourvisor.inquiries
     * с настроенным TOURVISOR_WEBHOOK_TOKEN) как приёмник уведомлений Tourvisor.
     *
     * @throws TourvisorWebhookRegistrationException
     */
    public function register(): void
    {
        $this->send($this->callbackUrl());
    }

    /**
     * Снять регистрацию webhook (пустой url — официальный способ отключения).
     *
     * @throws TourvisorWebhookRegistrationException
     */
    public function remove(): void
    {
        $this->send('');
    }

    private function callbackUrl(): string
    {
        $token = config('services.tourvisor.webhook_token');

        if (! is_string($token) || trim($token) === '') {
            throw new TourvisorWebhookRegistrationException(TourvisorWebhookRegistrationException::NOT_CONFIGURED);
        }

        return route('webhooks.tourvisor.inquiries', ['webhookToken' => $token]);
    }

    private function send(string $url): void
    {
        $key = config('services.tourvisor.export_api_key');

        if (! is_string($key) || trim($key) === '') {
            throw new TourvisorWebhookRegistrationException(TourvisorWebhookRegistrationException::NOT_CONFIGURED);
        }

        $response = $this->post($key, $url);

        $status = $response->status();

        if ($status === 401 || $status === 403) {
            throw new TourvisorWebhookRegistrationException(TourvisorWebhookRegistrationException::UNAUTHORIZED);
        }

        if ($status === 429) {
            throw new TourvisorWebhookRegistrationException(TourvisorWebhookRegistrationException::RATE_LIMITED);
        }

        if ($status >= 500) {
            throw new TourvisorWebhookRegistrationException(TourvisorWebhookRegistrationException::HTTP_ERROR);
        }

        if ($status < 200 || $status >= 300) {
            throw new TourvisorWebhookRegistrationException(TourvisorWebhookRegistrationException::HTTP_CLIENT_ERROR);
        }
    }

    private function post(string $key, string $url): Response
    {
        try {
            return Http::asJson()
                ->acceptJson()
                ->timeout(max(1, (int) config('services.tourvisor.timeout', 10)))
                ->connectTimeout(max(1, (int) config('services.tourvisor.connect_timeout', 5)))
                ->post(self::WEBHOOKS_URL, [
                    'authkey' => $key,
                    'url' => $url,
                ]);
        } catch (ConnectionException $e) {
            // Текст исключения может содержать тело запроса с authkey/url — используем
            // только признак таймаута, само исключение не пробрасываем.
            $timedOut = str_contains($e->getMessage(), 'cURL error 28') || stripos($e->getMessage(), 'timed out') !== false;

            throw new TourvisorWebhookRegistrationException($timedOut ? TourvisorWebhookRegistrationException::TIMEOUT : TourvisorWebhookRegistrationException::NETWORK);
        }
    }
}
