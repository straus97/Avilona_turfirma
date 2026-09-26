<?php

namespace Tests\Feature;

use App\Jobs\ImportTourvisorInquiry;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Http\Client\Request;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Tests\TestCase;

/**
 * E5-A2A.1 — секретный токен в пути callback-URL Tourvisor webhook.
 *
 * Токен проверяется до любых побочных эффектов: неверный/отсутствующий токен не
 * даёт ни записи в БД, ни задачи очереди, ни запроса к Tourvisor. Все значения
 * ниже синтетические; реальных запросов к Tourvisor нет.
 */
class TourvisorWebhookTokenTest extends TestCase
{
    use RefreshDatabase;

    private const EXPORT_KEY = 'SYNTHETIC-NOT-A-REAL-KEY-0003';
    private const TOKEN = 'synthetic-webhook-token-0123456789abcdef0123456789abcdef';
    private const BASE = '/api/webhooks/tourvisor/inquiries/';

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'services.tourvisor.export_api_key' => self::EXPORT_KEY,
            'services.tourvisor.webhook_token' => self::TOKEN,
        ]);
    }

    private function fakeExport(): void
    {
        Http::swap(new HttpFactory());
        Http::fake(['tourvisor.ru/*' => Http::response([
            'orders' => ['order' => [TourvisorExportClientTest::documentedSampleOrder('1688615')]],
        ])]);
    }

    private function hook(string $token, string $query = 'id=1688615&type=0')
    {
        return $this->get(self::BASE . $token . '?' . $query);
    }

    private function assertNoSideEffects(): void
    {
        $this->assertSame(0, IncomingInquiry::count());
        $this->assertSame(0, Booking::count());
        Http::assertNothingSent();
        Queue::assertNothingPushed();
    }

    public function test_correct_token_and_valid_request_follow_the_existing_flow(): void
    {
        $this->fakeExport();

        $this->hook(self::TOKEN)->assertOk()->assertExactJson(['status' => 'accepted']);

        $inquiry = IncomingInquiry::sole();
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $inquiry->state);
        $this->assertSame('1688615', $inquiry->external_id);
        Http::assertSentCount(1);
    }

    public function test_wrong_token_is_rejected_with_404_and_no_side_effects(): void
    {
        $this->fakeExport();
        Queue::fake();

        foreach ([
            'wrong-token-wrong-token-wrong-token-wrong-token',
            substr(self::TOKEN, 0, -1),
            self::TOKEN . 'x',
            strtoupper(self::TOKEN),
            'a',
        ] as $token) {
            $response = $this->hook($token)->assertNotFound();

            // Пустое тело: HTML-страница 404 сайта вывела бы URL с токеном (canonical/og:url).
            $this->assertSame('', $response->getContent());
        }

        $this->assertNoSideEffects();
    }

    public function test_malformed_token_shapes_are_rejected_without_persistence(): void
    {
        $this->fakeExport();
        Queue::fake();

        foreach ([
            '%20',
            '..%2F..%2Fetc',
            "a'%20OR%201=1",
            str_repeat('a', 129),
            self::TOKEN . '%00',
        ] as $token) {
            $this->hook($token)->assertNotFound();
        }

        // Старый маршрут без токена больше не существует.
        $this->get('/api/webhooks/tourvisor/inquiries?id=1688615&type=0')->assertNotFound();
        $this->get('/api/webhooks/tourvisor/inquiries/?id=1688615&type=0')->assertNotFound();

        $this->assertNoSideEffects();
    }

    public function test_unconfigured_server_token_fails_closed(): void
    {
        $this->fakeExport();
        Queue::fake();

        foreach ([null, '', '   ', 'short', str_repeat('x', 200), 'has space in it 0123456789abcdef0123456789abcdef'] as $configured) {
            config(['services.tourvisor.webhook_token' => $configured]);

            // Даже «совпадающий» с настройкой токен не принимается, если настройка пуста/слаба.
            $this->hook(self::TOKEN)->assertNotFound();
            $this->hook('short')->assertNotFound();
            $this->hook('null')->assertNotFound();
        }

        $this->assertNoSideEffects();
    }

    public function test_unrelated_routes_keep_working_without_a_webhook_token(): void
    {
        config(['services.tourvisor.webhook_token' => null]);

        $this->get('/tours')->assertOk();
        $this->get('/')->assertOk();
    }

    public function test_wrong_token_never_calls_tourvisor_or_dispatches_import(): void
    {
        $this->fakeExport();
        Queue::fake();

        $this->hook('wrong-token-wrong-token-wrong-token-wrong-token')->assertNotFound();

        Http::assertNothingSent();
        Queue::assertNotPushed(ImportTourvisorInquiry::class);
        $this->assertSame(0, IncomingInquiry::count());

        // Контроль: с верным токеном та же очередь и тот же запрос — уже эффект есть.
        $this->hook(self::TOKEN)->assertSuccessful();
        Queue::assertPushed(ImportTourvisorInquiry::class, 1);
        $this->assertSame(1, IncomingInquiry::count());
    }

    public function test_tokens_never_appear_in_responses_logs_or_stored_data(): void
    {
        $this->fakeExport();
        $logged = [];
        Log::listen(function ($event) use (&$logged): void {
            $logged[] = $event->message . json_encode($event->context);
        });

        $responses = [
            $this->hook('wrong-token-wrong-token-wrong-token-wrong-token'),
            $this->hook(self::TOKEN),
            $this->hook(self::TOKEN, 'id=bad&type=0'),
        ];
        config(['services.tourvisor.webhook_token' => null]);
        $responses[] = $this->hook(self::TOKEN);

        $everything = implode("\n", $logged);
        foreach ($responses as $response) {
            $everything .= "\n" . $response->getContent() . json_encode($response->headers->all());
        }
        $everything .= json_encode(IncomingInquiry::query()->get()->makeVisible('vendor_payload')->toArray());

        foreach ([self::TOKEN, 'wrong-token-wrong-token', self::EXPORT_KEY] as $secret) {
            $this->assertStringNotContainsString($secret, $everything);
        }
    }

    public function test_export_key_and_webhook_token_are_distinct_secrets(): void
    {
        $source = file_get_contents(base_path('config/services.php'));
        $this->assertStringContainsString("'export_api_key' => env('TOURVISOR_EXPORT_API_KEY')", $source);
        $this->assertStringContainsString("'webhook_token' => env('TOURVISOR_WEBHOOK_TOKEN')", $source);
        $this->assertDoesNotMatchRegularExpression("/TOURVISOR_WEBHOOK_TOKEN'\s*,/", $source, 'Токен не должен иметь значения по умолчанию');

        $this->assertNotSame(config('services.tourvisor.export_api_key'), config('services.tourvisor.webhook_token'));

        $this->fakeExport();

        // Ключ Export API не открывает webhook…
        $this->hook(self::EXPORT_KEY)->assertNotFound();
        $this->assertSame(0, IncomingInquiry::count());

        // …а токен webhook не уходит к Tourvisor и не подменяет ключ выгрузки.
        $this->hook(self::TOKEN)->assertSuccessful();
        Http::assertSent(function (Request $request): bool {
            parse_str((string) parse_url($request->url(), PHP_URL_QUERY), $query);

            return ($query['authkey'] ?? null) === self::EXPORT_KEY
                && ! str_contains($request->url(), self::TOKEN)
                && ! str_contains(json_encode($request->headers()), self::TOKEN);
        });
    }

    public function test_duplicate_valid_webhook_still_preserves_idempotency(): void
    {
        $this->fakeExport();

        $this->hook(self::TOKEN)->assertOk()->assertExactJson(['status' => 'accepted']);
        $this->hook(self::TOKEN)->assertOk()->assertExactJson(['status' => 'duplicate']);
        $this->hook(self::TOKEN)->assertOk()->assertExactJson(['status' => 'duplicate']);

        $this->assertSame(1, IncomingInquiry::count());
        $this->assertSame(3, IncomingInquiry::sole()->webhook_count);
        Http::assertSentCount(1);
    }

    public function test_valid_token_does_not_weaken_id_and_type_validation(): void
    {
        $this->fakeExport();
        Queue::fake();

        foreach (['', 'id=1688615', 'type=0', 'id=abc&type=0', 'id=1688615&type=2', 'id=' . str_repeat('9', 19) . '&type=0', 'id[]=1&type=0'] as $query) {
            $this->hook(self::TOKEN, $query)->assertStatus(400)->assertExactJson(['status' => 'invalid']);
        }

        $this->assertNoSideEffects();
    }

    public function test_wrong_tokens_still_consume_the_ip_rate_limit(): void
    {
        $this->fakeExport();

        for ($i = 0; $i < 120; $i++) {
            $this->hook('wrong-token-wrong-token-wrong-token-wrong-token')->assertNotFound();
        }

        // Лимит срабатывает раньше проверки токена — перебор токена ограничен по частоте.
        $this->hook('wrong-token-wrong-token-wrong-token-wrong-token')->assertStatus(429);
        $this->hook(self::TOKEN)->assertStatus(429);
    }

    public function test_webhook_still_never_touches_bookings_users_or_online_data(): void
    {
        $sameContact = User::factory()->create(['email' => 'synthetic@example.test', 'phone' => '+70000000000']);
        $booking = Booking::withoutEvents(fn () => Booking::create([
            'user_id' => $sameContact->id,
            'status' => Booking::STATUS_NEW,
            'departure_city' => 'X', 'destination_country' => 'Y', 'destination_city' => 'Z',
            'start_date' => now()->addMonth()->toDateString(),
            'nights' => 7, 'adults' => 2, 'children' => 0,
            'total_price' => 1000, 'paid_amount' => 0,
        ]));
        $users = User::count();

        $this->fakeExport();
        $this->hook(self::TOKEN)->assertOk();
        $this->hook(self::TOKEN, 'id=42&type=1')->assertOk();

        $this->assertSame(1, Booking::count());
        $this->assertSame(Booking::STATUS_NEW, $booking->fresh()->status);
        $this->assertSame($users, User::count());
        $online = IncomingInquiry::where('external_type', 1)->sole();
        $this->assertSame(IncomingInquiry::STATE_UNSUPPORTED, $online->state);
        $this->assertNull($online->client_name);
        Http::assertSentCount(1);
    }
}
