<?php

namespace Tests\Feature;

use App\Jobs\ImportTourvisorInquiry;
use App\Models\IncomingInquiry;
use Illuminate\Contracts\Bus\Dispatcher;
use Illuminate\Contracts\Foundation\MaintenanceMode;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use RuntimeException;
use Tests\TestCase;

/**
 * AOA-03-I5 — что на самом деле происходит с входящим обращением при остановке трафика.
 *
 * Эти тесты фиксируют ФАКТЫ текущего кода, на которых стоит вывод «беспотерьная заморозка не
 * доказана». Они не доказывают поведение внешнего провайдера: повторяет ли Tourvisor запрос после
 * 503/429, в репозитории и в документации не установлено (E5 research, вопрос 6 остаётся открытым).
 */
class InquiryIntakeFreezeSemanticsTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = 'synthetic-freeze-token-0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();
        config([
            'services.tourvisor.export_api_key' => 'SYNTHETIC-NOT-A-REAL-KEY-0005',
            'services.tourvisor.webhook_token' => self::TOKEN,
        ]);
    }

    private function url(string $query = 'id=1688615&type=0'): string
    {
        return '/api/webhooks/tourvisor/inquiries/' . self::TOKEN . '?' . $query;
    }

    private function maintenance(bool $active): void
    {
        $this->app->instance(MaintenanceMode::class, new class ($active) implements MaintenanceMode {
            public function __construct(private bool $active)
            {
            }

            public function activate(array $payload): void
            {
            }

            public function deactivate(): void
            {
            }

            public function active(): bool
            {
                return $this->active;
            }

            public function data(): array
            {
                return ['status' => 503, 'retry' => null];
            }
        });
    }

    public function test_the_acknowledgement_is_sent_only_after_the_inquiry_row_is_persisted(): void
    {
        Queue::fake();

        $response = $this->get($this->url());

        $response->assertStatus(202)->assertExactJson(['status' => 'accepted']);
        $row = IncomingInquiry::sole();
        $this->assertSame(IncomingInquiry::STATE_PENDING, $row->state);
        $this->assertSame('1688615', $row->external_id);
        Queue::assertPushed(ImportTourvisorInquiry::class, 1);
    }

    public function test_a_failing_queue_never_turns_a_persisted_inquiry_into_an_error_response(): void
    {
        $this->mock(Dispatcher::class, fn ($mock) => $mock->shouldReceive('dispatch')->andThrow(new RuntimeException('queue is down')));

        $this->get($this->url())->assertStatus(202);

        $this->assertSame(IncomingInquiry::STATE_PENDING, IncomingInquiry::sole()->state, 'row stays pending for a later delivery or manual retry');
    }

    public function test_a_repeated_delivery_keeps_one_row_and_counts_the_repeat(): void
    {
        Queue::fake();

        $this->get($this->url())->assertStatus(202);
        $this->get($this->url())->assertStatus(202);

        $this->assertSame(1, IncomingInquiry::query()->count());
        $this->assertSame(2, (int) IncomingInquiry::sole()->webhook_count);
    }

    public function test_during_maintenance_the_webhook_is_answered_503_and_nothing_is_persisted_or_queued(): void
    {
        Queue::fake();
        $this->maintenance(true);

        $response = $this->get($this->url());

        $this->assertSame(503, $response->getStatusCode());
        $this->assertSame(0, IncomingInquiry::query()->count(), 'a rejected inquiry exists nowhere in this system');
        Queue::assertNothingPushed();
    }

    public function test_the_webhook_is_not_excluded_from_maintenance_mode(): void
    {
        $middleware = new \App\Http\Middleware\PreventRequestsDuringMaintenance($this->app);
        $except = (new \ReflectionProperty($middleware, 'except'))->getValue($middleware);

        $this->assertSame([], $except, 'if this changes, intake keeps writing during maintenance and the freeze analysis must be redone');
    }

    public function test_after_maintenance_ends_a_redelivery_is_accepted_but_only_if_the_provider_sends_it(): void
    {
        Queue::fake();
        $this->maintenance(true);
        $this->get($this->url())->assertStatus(503);

        $this->maintenance(false);
        $this->assertSame(0, IncomingInquiry::query()->count(), 'nothing replays the rejected request by itself');

        $this->get($this->url())->assertStatus(202);
        $this->assertSame(1, IncomingInquiry::query()->count());
    }

    public function test_the_per_ip_rate_limit_also_drops_a_request_without_persisting_it(): void
    {
        Queue::fake();

        for ($i = 0; $i < 120; $i++) {
            $this->get($this->url('id=' . (1000 + $i) . '&type=0'))->assertStatus(202);
        }

        $this->assertSame(429, $this->get($this->url('id=999999&type=0'))->getStatusCode());
        $this->assertNull(IncomingInquiry::query()->where('external_id', '999999')->first());
    }

    public function test_the_application_has_no_durable_intake_buffer_separate_from_incoming_inquiries(): void
    {
        $tables = array_map(fn ($t) => strtolower($t), \Illuminate\Support\Facades\Schema::getTableListing());
        $buffers = array_values(array_filter($tables, fn (string $t): bool => str_contains($t, 'intake') || str_contains($t, 'webhook') || str_contains($t, 'inbox')));

        $this->assertSame([], $buffers, 'if a durable buffer is added, the no-loss analysis in docs/AOA-03-I5 must be redone');
    }
}
