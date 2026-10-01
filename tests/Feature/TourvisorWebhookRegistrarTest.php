<?php

namespace Tests\Feature;

use App\Services\Tourvisor\TourvisorWebhookRegistrar;
use App\Services\Tourvisor\TourvisorWebhookRegistrationException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * E5-A2B — контракт клиента регистрации/снятия webhook Tourvisor на МОКАХ (Http::fake).
 *
 * Реальных запросов к Tourvisor нет; ключ и токен ниже — синтетические, не настоящие.
 */
class TourvisorWebhookRegistrarTest extends TestCase
{
    private const FAKE_KEY = 'SYNTHETIC-NOT-A-REAL-KEY-0002';

    private const FAKE_TOKEN = 'synthetic-webhook-token-0123456789abcdef';

    /** Чистая фабрика на каждый вызов: повторный Http::fake() не переопределяет прежние заглушки. */
    private function fakeHttp(mixed $stubs = null): void
    {
        Http::swap(new HttpFactory());
        Http::fake($stubs);
    }

    private function configure(): void
    {
        config([
            'services.tourvisor.export_api_key' => self::FAKE_KEY,
            'services.tourvisor.webhook_token' => self::FAKE_TOKEN,
        ]);
    }

    private function assertFailsWith(callable $call, string $reason): TourvisorWebhookRegistrationException
    {
        try {
            $call();
        } catch (TourvisorWebhookRegistrationException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertStringNotContainsString(self::FAKE_KEY, $e->getMessage());
            $this->assertStringNotContainsString(self::FAKE_TOKEN, $e->getMessage());
            $this->assertNull($e->getPrevious());

            return $e;
        }

        $this->fail('Expected TourvisorWebhookRegistrationException with reason ' . $reason);
    }

    public function test_missing_key_or_token_fails_safely_without_any_http_request(): void
    {
        $this->fakeHttp();

        config(['services.tourvisor.export_api_key' => null, 'services.tourvisor.webhook_token' => self::FAKE_TOKEN]);
        $this->assertFailsWith(fn () => app(TourvisorWebhookRegistrar::class)->register(), TourvisorWebhookRegistrationException::NOT_CONFIGURED);

        config(['services.tourvisor.export_api_key' => self::FAKE_KEY, 'services.tourvisor.webhook_token' => null]);
        $this->assertFailsWith(fn () => app(TourvisorWebhookRegistrar::class)->register(), TourvisorWebhookRegistrationException::NOT_CONFIGURED);

        // remove() только снимает регистрацию (пустой url) — authkey всё равно обязателен.
        config(['services.tourvisor.export_api_key' => null, 'services.tourvisor.webhook_token' => self::FAKE_TOKEN]);
        $this->assertFailsWith(fn () => app(TourvisorWebhookRegistrar::class)->remove(), TourvisorWebhookRegistrationException::NOT_CONFIGURED);

        Http::assertNothingSent();
    }

    public function test_register_posts_authkey_and_full_callback_url_with_token(): void
    {
        $this->configure();
        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['status' => 'ok'])]);

        app(TourvisorWebhookRegistrar::class)->register();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return $request->method() === 'POST'
                && $request->url() === 'https://tourvisor.ru/xml/webhooks.php'
                && ($body['authkey'] ?? null) === self::FAKE_KEY
                && str_contains((string) ($body['url'] ?? ''), '/webhooks/tourvisor/inquiries/' . self::FAKE_TOKEN);
        });
    }

    public function test_remove_posts_authkey_with_empty_url(): void
    {
        $this->configure();
        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['status' => 'ok'])]);

        app(TourvisorWebhookRegistrar::class)->remove();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            $body = $request->data();

            return ($body['authkey'] ?? null) === self::FAKE_KEY
                && ($body['url'] ?? null) === '';
        });
    }

    public function test_timeout_and_network_errors_never_leak_secrets(): void
    {
        $this->configure();
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message . json_encode($event->context);
        });

        $this->fakeHttp(fn () => throw new ConnectionException('cURL error 28: Operation timed out for https://tourvisor.ru/xml/webhooks.php (authkey=' . self::FAKE_KEY . '&url=' . self::FAKE_TOKEN . ')'));
        $timeout = $this->assertFailsWith(fn () => app(TourvisorWebhookRegistrar::class)->register(), TourvisorWebhookRegistrationException::TIMEOUT);
        $this->assertTrue($timeout->isRetryable());

        $this->fakeHttp(fn () => throw new ConnectionException('cURL error 6: Could not resolve host (authkey=' . self::FAKE_KEY . ')'));
        $network = $this->assertFailsWith(fn () => app(TourvisorWebhookRegistrar::class)->register(), TourvisorWebhookRegistrationException::NETWORK);
        $this->assertTrue($network->isRetryable());

        $this->assertStringNotContainsString(self::FAKE_KEY, implode("\n", $logged));
        $this->assertStringNotContainsString(self::FAKE_TOKEN, implode("\n", $logged));
    }

    public function test_non_2xx_responses_map_to_safe_reasons_without_leaking_body(): void
    {
        $this->configure();

        foreach ([
            [401, TourvisorWebhookRegistrationException::UNAUTHORIZED, false],
            [403, TourvisorWebhookRegistrationException::UNAUTHORIZED, false],
            [429, TourvisorWebhookRegistrationException::RATE_LIMITED, true],
            [500, TourvisorWebhookRegistrationException::HTTP_ERROR, true],
            [503, TourvisorWebhookRegistrationException::HTTP_ERROR, true],
            [404, TourvisorWebhookRegistrationException::HTTP_CLIENT_ERROR, false],
        ] as [$status, $reason, $retryable]) {
            $this->fakeHttp(['tourvisor.ru/*' => Http::response('authkey=' . self::FAKE_KEY . ' token=' . self::FAKE_TOKEN . ' error body', $status)]);
            $e = $this->assertFailsWith(fn () => app(TourvisorWebhookRegistrar::class)->register(), $reason);
            $this->assertSame($retryable, $e->isRetryable(), "status $status");
        }
    }
}
