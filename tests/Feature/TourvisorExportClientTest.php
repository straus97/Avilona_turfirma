<?php

namespace Tests\Feature;

use App\Services\Tourvisor\TourvisorExportClient;
use App\Services\Tourvisor\TourvisorExportException;
use App\Services\Tourvisor\TourvisorInquiryType;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Tests\TestCase;

/**
 * E5-A2A — контракт клиента Tourvisor Export API на МОКАХ (Http::fake).
 *
 * Реальных запросов к Tourvisor нет; ключ ниже — синтетический, не настоящий.
 * Форма ответа взята из публичного примера документации (живой ответ будет
 * сверен в E5-A2B).
 */
class TourvisorExportClientTest extends TestCase
{
    private const FAKE_KEY = 'SYNTHETIC-NOT-A-REAL-KEY-0001';

    /** @return array<string, string> */
    public static function documentedSampleOrder(string $id = '1688615'): array
    {
        return [
            'id' => $id,
            'date' => '05.12.2018',
            'time' => '11:05:51',
            'name' => 'Тестовый Клиент',
            'phone' => '+70000000000',
            'email' => 'synthetic@example.test',
            'comments' => '',
            'price' => '70266',
            'fuelcharge' => '0',
            'currency' => 'RUB',
            'operator' => 'Sunmar',
            'departure' => 'Пермь',
            'country' => 'Таиланд',
            'region' => 'Пхукет',
            'hotel' => 'TEST RESORT 3*',
            'flydate' => '11.12.2018',
            'nights' => '11',
            'placement' => '2 взр',
            'meal' => 'Без питания',
            'room' => 'standard room',
            'tour' => 'о.Пхукет',
            'operatorlink' => 'http://example.test/tour',
            'type' => '0',
            'typename' => 'Заявка на тур',
            'domain' => 'example.test',
        ];
    }

    /** Чистая фабрика на каждый вызов: повторный Http::fake() не переопределяет прежние заглушки. */
    private function fakeHttp(mixed $stubs = null): void
    {
        Http::swap(new HttpFactory());
        Http::fake($stubs);
    }

    private function configureKey(): void
    {
        config(['services.tourvisor.export_api_key' => self::FAKE_KEY]);
    }

    private function fetch(string $id = '1688615')
    {
        return app(TourvisorExportClient::class)->fetchInquiry(TourvisorInquiryType::Ordinary, $id);
    }

    private function assertFailsWith(string $reason, string $id = '1688615'): TourvisorExportException
    {
        try {
            $this->fetch($id);
        } catch (TourvisorExportException $e) {
            $this->assertSame($reason, $e->reason);
            $this->assertStringNotContainsString(self::FAKE_KEY, $e->getMessage());
            $this->assertNull($e->getPrevious());

            return $e;
        }

        $this->fail('Expected TourvisorExportException with reason ' . $reason);
    }

    public function test_missing_api_key_fails_safely_without_any_http_request(): void
    {
        $this->fakeHttp();
        config(['services.tourvisor.export_api_key' => null]);

        $this->assertFailsWith(TourvisorExportException::NOT_CONFIGURED);

        config(['services.tourvisor.export_api_key' => '   ']);
        $this->assertFailsWith(TourvisorExportException::NOT_CONFIGURED);

        Http::assertNothingSent();
    }

    public function test_config_maps_key_from_environment_only_with_no_default_value(): void
    {
        $source = file_get_contents(base_path('config/services.php'));

        $this->assertStringContainsString("env('TOURVISOR_EXPORT_API_KEY')", $source);
        $this->assertDoesNotMatchRegularExpression("/TOURVISOR_EXPORT_API_KEY'\s*,/", $source);
    }

    public function test_request_is_server_side_to_fixed_host_with_documented_parameters(): void
    {
        $this->configureKey();
        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['orders' => ['order' => [self::documentedSampleOrder()]]])]);

        $this->fetch();

        Http::assertSentCount(1);
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return $request->method() === 'GET'
                && str_starts_with($request->url(), 'https://tourvisor.ru/xml/orders.php?')
                && ($query['authkey'] ?? null) === self::FAKE_KEY
                && ($query['id'] ?? null) === '1688615'
                && ($query['format'] ?? null) === 'json';
        });
    }

    public function test_successful_ordinary_inquiry_is_normalized_with_optional_fields(): void
    {
        $this->configureKey();
        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['orders' => ['order' => [self::documentedSampleOrder()]]])]);

        $inquiry = $this->fetch();

        $this->assertSame(TourvisorInquiryType::Ordinary, $inquiry->type);
        $this->assertSame('1688615', $inquiry->externalId);
        $this->assertSame('Тестовый Клиент', $inquiry->name);
        $this->assertSame('+70000000000', $inquiry->phone);
        $this->assertNull($inquiry->comment, 'Пустая строка должна стать null');
        $this->assertSame('70266', $inquiry->price);
        $this->assertSame('RUB', $inquiry->currency);
        $this->assertSame('Sunmar', $inquiry->operator);
        $this->assertSame('Пермь', $inquiry->departureCity);
        $this->assertSame('Таиланд', $inquiry->country);
        $this->assertSame('2018-12-11', $inquiry->flyDate?->toDateString());
        $this->assertSame(11, $inquiry->nights);
        $this->assertSame('о.Пхукет', $inquiry->payload['tour']);
    }

    public function test_single_order_object_and_missing_optional_fields_are_accepted(): void
    {
        $this->configureKey();
        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['orders' => ['order' => ['id' => '77', 'price' => 'abc', 'flydate' => '31.02.2030', 'nights' => 'x']]])]);

        $inquiry = $this->fetch('77');

        $this->assertSame('77', $inquiry->externalId);
        $this->assertNull($inquiry->price);
        $this->assertNull($inquiry->flyDate);
        $this->assertNull($inquiry->nights);
        $this->assertNull($inquiry->name);
    }

    public function test_payload_snapshot_is_bounded_and_never_carries_key_like_fields(): void
    {
        $this->configureKey();
        $order = self::documentedSampleOrder() + ['authkey' => self::FAKE_KEY, 'token' => 'x', 'huge' => str_repeat('я', 5000), 'nested' => ['a' => 'b']];
        for ($i = 0; $i < 100; $i++) {
            $order['extra' . $i] = 'v';
        }
        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['orders' => ['order' => [$order]]])]);

        $payload = $this->fetch()->payload;

        $this->assertLessThanOrEqual(40, count($payload));
        $this->assertArrayNotHasKey('authkey', $payload);
        $this->assertArrayNotHasKey('token', $payload);
        $this->assertArrayNotHasKey('nested', $payload);
        $this->assertLessThanOrEqual(1000, mb_strlen($payload['huge'] ?? ''));
        $this->assertStringNotContainsString(self::FAKE_KEY, json_encode($payload));
    }

    public function test_malformed_remote_responses_are_handled_safely(): void
    {
        $this->configureKey();

        foreach ([
            'not json at all <xml/>',
            '',
            '[1,2,3]',
            '{"foo":"bar"}',
            '{"orders":"text"}',
            '{"orders":{"order":"text"}}',
        ] as $body) {
            $this->fakeHttp(['tourvisor.ru/*' => Http::response($body, 200)]);
            $this->assertFailsWith(TourvisorExportException::MALFORMED);
        }
    }

    public function test_empty_result_is_not_found_and_foreign_id_is_rejected(): void
    {
        $this->configureKey();

        foreach (['{"orders":""}', '{"orders":[]}', '{"orders":{"order":[]}}'] as $body) {
            $this->fakeHttp(['tourvisor.ru/*' => Http::response($body, 200)]);
            $this->assertFailsWith(TourvisorExportException::NOT_FOUND);
        }

        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['orders' => ['order' => [self::documentedSampleOrder('999')]]])]);
        $this->assertFailsWith(TourvisorExportException::ID_MISMATCH, '1688615');
    }

    public function test_timeout_and_network_errors_never_leak_the_key(): void
    {
        $this->configureKey();
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message . json_encode($event->context);
        });

        $this->fakeHttp(fn () => throw new ConnectionException('cURL error 28: Operation timed out for https://tourvisor.ru/xml/orders.php?authkey=' . self::FAKE_KEY . '&id=1'));
        $timeout = $this->assertFailsWith(TourvisorExportException::TIMEOUT);
        $this->assertTrue($timeout->isRetryable());

        $this->fakeHttp(fn () => throw new ConnectionException('cURL error 6: Could not resolve host (authkey=' . self::FAKE_KEY . ')'));
        $network = $this->assertFailsWith(TourvisorExportException::NETWORK);
        $this->assertTrue($network->isRetryable());

        $this->assertStringNotContainsString(self::FAKE_KEY, implode("\n", $logged));
    }

    public function test_non_2xx_responses_map_to_safe_reasons(): void
    {
        $this->configureKey();

        foreach ([
            [401, TourvisorExportException::UNAUTHORIZED, false],
            [403, TourvisorExportException::UNAUTHORIZED, false],
            [429, TourvisorExportException::RATE_LIMITED, true],
            [500, TourvisorExportException::HTTP_ERROR, true],
            [503, TourvisorExportException::HTTP_ERROR, true],
            [404, TourvisorExportException::HTTP_CLIENT_ERROR, false],
        ] as [$status, $reason, $retryable]) {
            $this->fakeHttp(['tourvisor.ru/*' => Http::response('authkey=' . self::FAKE_KEY . ' error body', $status)]);
            $e = $this->assertFailsWith($reason);
            $this->assertSame($retryable, $e->isRetryable(), "status $status");
        }
    }

    public function test_online_type_is_not_fetched_and_bad_ids_are_rejected_before_any_request(): void
    {
        $this->configureKey();
        $this->fakeHttp();

        try {
            app(TourvisorExportClient::class)->fetchInquiry(TourvisorInquiryType::Online, '1');
            $this->fail('Online type must not be fetched in E5-A2A');
        } catch (TourvisorExportException $e) {
            $this->assertSame(TourvisorExportException::UNSUPPORTED_TYPE, $e->reason);
        }

        $this->assertFailsWith(TourvisorExportException::MALFORMED, '1&authkey=x');

        Http::assertNothingSent();
    }
}
