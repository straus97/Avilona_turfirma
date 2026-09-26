<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\User;
use App\Services\Tourvisor\TourvisorInquiryImporter;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * E5-A2A — приёмник webhook Tourvisor, идемпотентность и границы безопасности.
 * Все обращения к Tourvisor замоканы (Http::fake); реальных запросов нет.
 */
class TourvisorInquiryWebhookTest extends TestCase
{
    use RefreshDatabase;

    private const FAKE_KEY = 'SYNTHETIC-NOT-A-REAL-KEY-0002';
    private const WEBHOOK_TOKEN = 'synthetic-webhook-token-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tourvisor.export_api_key' => self::FAKE_KEY,
            'services.tourvisor.webhook_token' => self::WEBHOOK_TOKEN,
        ]);
    }

    private function webhookUrl(): string
    {
        return '/api/webhooks/tourvisor/inquiries/' . self::WEBHOOK_TOKEN;
    }

    /** Чистая фабрика на каждый вызов: повторный Http::fake() не переопределяет прежние заглушки. */
    private function fakeHttp(mixed $stubs = null): void
    {
        Http::swap(new HttpFactory());
        Http::fake($stubs);
    }

    private function fakeExport(string $id = '1688615', array $override = []): void
    {
        $this->fakeHttp(['tourvisor.ru/*' => Http::response([
            'orders' => ['order' => [array_merge(TourvisorExportClientTest::documentedSampleOrder($id), $override)]],
        ])]);
    }

    private function notify(string $query = 'id=1688615&type=0')
    {
        return $this->get($this->webhookUrl() . '?' . $query);
    }

    public function test_valid_webhook_is_accepted_and_authoritative_data_is_fetched_server_side(): void
    {
        $this->fakeExport();

        $response = $this->notify();

        $response->assertOk()->assertExactJson(['status' => 'accepted']);
        $this->assertStringNotContainsString(self::FAKE_KEY, $response->getContent());

        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r): bool => str_starts_with($r->url(), 'https://tourvisor.ru/xml/orders.php?'));

        $inquiry = IncomingInquiry::sole();
        $this->assertSame('tourvisor', $inquiry->provider);
        $this->assertSame(0, $inquiry->external_type);
        $this->assertSame('1688615', $inquiry->external_id);
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->state);
        $this->assertSame('Sunmar', $inquiry->operator_name);
        $this->assertSame(1, $inquiry->normalizer_version);
        $this->assertNotNull($inquiry->imported_at);
    }

    public function test_webhook_body_and_extra_query_fields_are_not_trusted(): void
    {
        $this->fakeExport();

        $this->get($this->webhookUrl() . '?id=1688615&type=0&name=Evil&phone=1&url=' . urlencode('https://evil.example/x') . '&price=1')
            ->assertOk();

        // Данные — только из авторитетной выгрузки, а не из query webhook.
        $this->assertSame('Тестовый Клиент', IncomingInquiry::sole()->client_name);
        Http::assertSentCount(1);
        Http::assertSent(fn (Request $r): bool => parse_url($r->url(), PHP_URL_HOST) === 'tourvisor.ru');
    }

    public function test_invalid_webhooks_are_rejected_without_side_effects(): void
    {
        $this->fakeHttp();

        foreach ([
            '',
            'id=1688615',
            'type=0',
            'id=abc&type=0',
            'id=1688615&type=2',
            'id=1688615&type=x',
            'id=' . str_repeat('9', 19) . '&type=0',
            'id=1%27%20OR%201=1&type=0',
            'id[]=1&type=0',
            'id=-5&type=0',
        ] as $query) {
            $this->notify($query)->assertStatus(400)->assertExactJson(['status' => 'invalid']);
        }

        $this->assertSame(0, IncomingInquiry::count());
        Http::assertNothingSent();
    }

    public function test_webhook_needs_no_session_and_only_accepts_get(): void
    {
        $this->fakeExport();

        $this->assertGuest();
        $this->notify()->assertOk();

        $this->post($this->webhookUrl() . '?id=1&type=0')->assertStatus(405);
        $this->assertSame(1, IncomingInquiry::count());
    }

    public function test_csrf_exceptions_were_not_broadened(): void
    {
        $this->assertSame(
            [],
            (new \ReflectionClass(\App\Http\Middleware\VerifyCsrfToken::class))->getDefaultProperties()['except'] ?? []
        );
    }

    public function test_duplicate_webhook_creates_one_inquiry_and_does_not_refetch_or_repeat_work(): void
    {
        $this->fakeExport();

        $this->notify()->assertOk()->assertExactJson(['status' => 'accepted']);
        $this->notify()->assertOk()->assertExactJson(['status' => 'duplicate']);
        $this->notify()->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame(1, IncomingInquiry::count());
        $this->assertSame(3, IncomingInquiry::sole()->webhook_count);
        Http::assertSentCount(1);
    }

    public function test_same_id_with_different_type_is_a_different_inquiry(): void
    {
        $this->fakeExport();

        $this->notify('id=1688615&type=0')->assertOk();
        $this->notify('id=1688615&type=1')->assertOk();

        $this->assertSame(2, IncomingInquiry::count());
        Http::assertSentCount(1);
    }

    public function test_online_type_is_recorded_but_never_fetched(): void
    {
        $this->fakeHttp();

        $this->notify('id=42&type=1')->assertOk();

        $inquiry = IncomingInquiry::sole();
        $this->assertSame(1, $inquiry->external_type);
        $this->assertSame(IncomingInquiry::STATE_UNSUPPORTED, $inquiry->state);
        $this->assertNull($inquiry->client_name);
        Http::assertNothingSent();
    }

    public function test_database_uniqueness_protects_the_identity_even_without_application_checks(): void
    {
        $attributes = [
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => '555',
            'state' => IncomingInquiry::STATE_PENDING,
        ];

        (new IncomingInquiry())->forceFill($attributes)->save();

        $this->expectException(UniqueConstraintViolationException::class);
        (new IncomingInquiry())->forceFill($attributes)->save();
    }

    public function test_concurrent_delivery_race_is_absorbed_by_the_unique_index(): void
    {
        $this->fakeExport('555');

        // Параллельный обработчик уже вставил строку между «нашей» проверкой и вставкой.
        (new IncomingInquiry())->forceFill([
            'provider' => 'tourvisor', 'external_type' => 0, 'external_id' => '555',
            'state' => IncomingInquiry::STATE_PENDING, 'webhook_count' => 1,
        ])->save();

        $this->notify('id=555&type=0')->assertSuccessful();

        $this->assertSame(1, IncomingInquiry::count());
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, IncomingInquiry::sole()->state);
    }

    public function test_repeated_import_is_idempotent_and_claims_are_exclusive(): void
    {
        $this->fakeExport();
        $this->notify()->assertOk();
        $id = IncomingInquiry::sole()->id;
        $importer = app(TourvisorInquiryImporter::class);

        $this->assertSame(TourvisorInquiryImporter::RESULT_SKIPPED, $importer->import($id));
        $this->assertSame(1, IncomingInquiry::count());
        $this->assertSame(1, IncomingInquiry::sole()->attempts);
        Http::assertSentCount(1);

        // Свежий захват (importing) другим процессом не перехватывается…
        IncomingInquiry::whereKey($id)->update(['state' => IncomingInquiry::STATE_IMPORTING, 'last_attempt_at' => now()]);
        $this->assertSame(TourvisorInquiryImporter::RESULT_SKIPPED, $importer->import($id));

        // …а зависший — перехватывается.
        IncomingInquiry::whereKey($id)->update(['last_attempt_at' => now()->subMinutes(IncomingInquiry::IMPORT_CLAIM_TTL_MINUTES + 1)]);
        $this->assertSame(TourvisorInquiryImporter::RESULT_IMPORTED, $importer->import($id));
        $this->assertSame(1, IncomingInquiry::count());
    }

    public function test_failed_import_is_recorded_and_recovered_by_a_later_delivery(): void
    {
        $this->fakeHttp(['tourvisor.ru/*' => Http::response('boom', 500)]);

        $this->notify()->assertStatus(202);

        $inquiry = IncomingInquiry::sole();
        $this->assertSame(IncomingInquiry::STATE_FAILED, $inquiry->state);
        $this->assertSame('http_error', $inquiry->last_error_code);

        $this->fakeExport();
        $this->notify()->assertOk();

        $inquiry->refresh();
        $this->assertSame(1, IncomingInquiry::count());
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->state);
        $this->assertNull($inquiry->last_error_code);
        $this->assertSame(2, $inquiry->attempts);
    }

    public function test_timeout_and_malformed_remote_data_leave_a_failed_record_not_a_server_error(): void
    {
        $this->fakeHttp(fn () => throw new \Illuminate\Http\Client\ConnectionException('cURL error 28: timed out ' . self::FAKE_KEY));
        $this->notify('id=1&type=0')->assertStatus(202);
        $this->assertSame('timeout', IncomingInquiry::where('external_id', '1')->value('last_error_code'));

        $this->fakeHttp(['tourvisor.ru/*' => Http::response('<html>oops</html>', 200)]);
        $this->notify('id=2&type=0')->assertStatus(202);
        $this->assertSame('malformed', IncomingInquiry::where('external_id', '2')->value('last_error_code'));

        $this->fakeHttp(['tourvisor.ru/*' => Http::response(['orders' => ['order' => []]], 200)]);
        $this->notify('id=3&type=0')->assertStatus(202);
        $this->assertSame('not_found', IncomingInquiry::where('external_id', '3')->value('last_error_code'));
    }

    public function test_missing_key_records_failure_without_outbound_request_or_leak(): void
    {
        config(['services.tourvisor.export_api_key' => null]);
        $this->fakeHttp();
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message . json_encode($event->context);
        });

        $response = $this->notify();

        $response->assertStatus(202);
        $this->assertSame('not_configured', IncomingInquiry::sole()->last_error_code);
        Http::assertNothingSent();
        $this->assertStringNotContainsString(self::FAKE_KEY, $response->getContent() . implode('', $logged));
    }

    public function test_secret_never_reaches_logs_responses_or_stored_payload(): void
    {
        $this->fakeExport('1688615', ['authkey' => self::FAKE_KEY]);
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message . json_encode($event->context);
        });

        $response = $this->notify();

        $inquiry = IncomingInquiry::sole();
        $this->assertStringNotContainsString(self::FAKE_KEY, $response->getContent());
        $this->assertStringNotContainsString(self::FAKE_KEY, json_encode($inquiry->vendor_payload));
        $this->assertStringNotContainsString(self::FAKE_KEY, implode('', $logged));
        $this->assertArrayNotHasKey('vendor_payload', $inquiry->toArray());
    }

    public function test_no_booking_user_or_status_changes_and_no_ownership_inferred_from_contacts(): void
    {
        // Существующий пользователь с теми же email/телефоном, что в обращении.
        $sameContact = User::factory()->create([
            'email' => 'synthetic@example.test',
            'phone' => '+70000000000',
        ]);
        $booking = Booking::withoutEvents(fn () => Booking::create([
            'user_id' => $sameContact->id,
            'status' => Booking::STATUS_NEW,
            'departure_city' => 'X', 'destination_country' => 'Y', 'destination_city' => 'Z',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7, 'adults' => 2, 'children' => 0,
            'total_price' => 1000, 'paid_amount' => 0,
        ]));
        $usersBefore = User::count();

        $this->fakeExport();
        $this->notify()->assertOk();
        $this->notify()->assertOk();

        $this->assertSame(1, Booking::count(), 'Новых Booking быть не должно');
        $this->assertSame(Booking::STATUS_NEW, $booking->fresh()->status);
        $this->assertSame('1000.00', (string) $booking->fresh()->total_price);
        $this->assertSame($usersBefore, User::count(), 'Пользователи не создаются');
        $this->assertSame(0, Booking::where('user_id', '!=', $sameContact->id)->count());

        foreach (['user_id', 'booking_id', 'manager_id'] as $column) {
            $this->assertFalse(Schema::hasColumn('incoming_inquiries', $column), "incoming_inquiries.$column не должна существовать в E5-A2A");
        }
        $this->assertSame('synthetic@example.test', IncomingInquiry::sole()->client_email);
    }

    public function test_webhook_is_rate_limited_per_ip(): void
    {
        $this->fakeHttp();

        for ($i = 0; $i < 120; $i++) {
            $this->notify('id=bad&type=0')->assertStatus(400);
        }

        $this->notify('id=bad&type=0')->assertStatus(429);
    }
}
