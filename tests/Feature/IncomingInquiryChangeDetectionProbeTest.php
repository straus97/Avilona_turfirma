<?php

namespace Tests\Feature;

use App\Events\BookingCreated;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\IncomingInquiries\IncomingInquiryWorkflow;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\Factory as HttpFactory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-03-I4 — зонды обнаружения изменений IncomingInquiry для будущего catch-up.
 *
 * Тесты двух видов, и различие важно:
 *  - «ПОКРЫТИЕ»: что реально делают поддерживаемые пути E5 (updated_at растёт — это опора, но не
 *    доказательство полноты);
 *  - «НЕБЕЗОПАСНО»: кандидаты в высокие отметки (max(id), updated_at, updated_at с равенством),
 *    которые на синтетике ПРОПУСКАЮТ изменение. Эти тесты проходят, потому что фиксируют
 *    ПРОВАЛ кандидата; они — свидетельство, что точный catch-up требует отдельного захвата
 *    изменений (журнал изменений E5), а не успех выбранной отметки. Проверка порядка коммитов
 *    между двумя соединениями выполняется на отдельном MySQL (см. docs/AOA-03-I4).
 *
 * Только синтетические данные, SQLite :memory:.
 */
class IncomingInquiryChangeDetectionProbeTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const TOKEN = 'synthetic-probe-token-0123456789abcdef0123456789abcdef';

    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;
    private CarbonImmutable $t0;

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Notification::fake();
        Event::fake([BookingCreated::class]);
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        config([
            'services.tourvisor.export_api_key' => 'probe-export-key-not-real',
            'services.tourvisor.webhook_token' => self::TOKEN,
        ]);

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->tourist = $this->userWith('tourist');
        $this->t0 = CarbonImmutable::parse('2026-03-01 10:00:00');
    }

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        \Illuminate\Support\Carbon::setTestNow();
        parent::tearDown();
    }

    private function at(CarbonImmutable $moment): void
    {
        \Illuminate\Support\Carbon::setTestNow($moment);
        CarbonImmutable::setTestNow($moment);
    }

    private function updatedAt(IncomingInquiry|int $row): string
    {
        return (string) DB::table('incoming_inquiries')->where('id', $row instanceof IncomingInquiry ? $row->id : $row)->value('updated_at');
    }

    private function fakeExport(string $id): void
    {
        Http::swap(new HttpFactory());
        Http::fake(['tourvisor.ru/*' => Http::response([
            'orders' => ['order' => [array_merge(TourvisorExportClientTest::documentedSampleOrder($id), [])]],
        ])]);
    }

    private function webhook(string $id): void
    {
        $this->get('/api/webhooks/tourvisor/inquiries/' . self::TOKEN . '?id=' . $id . '&type=0')->assertSuccessful();
    }

    // ====================================================================== ПОКРЫТИЕ

    public function test_coverage_every_supported_e5_workflow_mutation_advances_updated_at(): void
    {
        $workflow = app(IncomingInquiryWorkflow::class);
        $later = $this->t0->addHour();
        $convertData = [
            'departure_city' => 'Москва', 'destination_country' => 'Турция', 'start_date' => '2027-06-01',
            'nights' => 7, 'adults' => 2, 'total_price' => 150000,
        ];

        $steps = [
            'assign' => [fn () => $this->inquiryFor(null), fn (IncomingInquiry $r) => $workflow->assign($r, $this->admin, $this->a->id), 'assigned_to'],
            'reassign' => [fn () => $this->inquiryFor($this->a), fn (IncomingInquiry $r) => $workflow->reassign($r, $this->admin, $this->b->id, 'Передача между менеджерами'), 'assigned_to'],
            'select_client' => [fn () => $this->inquiryFor($this->a), fn (IncomingInquiry $r) => $workflow->selectClient($r, $this->admin, $this->tourist->id), 'client_user_id'],
            'create_client' => [fn () => $this->inquiryFor($this->a), fn (IncomingInquiry $r) => $workflow->createClient($r, $this->admin, 'Проба Клиент', 'probe.client@example.test', null), 'client_user_id'],
            'convert' => [fn () => $this->inquiryFor($this->a, $this->tourist), fn (IncomingInquiry $r) => $workflow->convert($r, $this->admin, $convertData), 'booking_id'],
            'close' => [fn () => $this->inquiryFor($this->a), fn (IncomingInquiry $r) => $workflow->close($r, $this->admin, 'Проба закрытия'), 'closed_at'],
        ];

        foreach ($steps as $label => [$make, $mutate, $businessField]) {
            $this->at($this->t0);
            $row = $make();
            $beforeValue = $row->getAttribute($businessField);
            $this->assertSame($this->t0->format('Y-m-d H:i:s'), $this->updatedAt($row), "{$label}: исходная отметка");

            $this->at($later);
            $mutate($row);

            $this->assertNotEquals($beforeValue, DB::table('incoming_inquiries')->where('id', $row->id)->value($businessField), "{$label}: бизнес-поле изменилось");
            $this->assertSame($later->format('Y-m-d H:i:s'), $this->updatedAt($row), "{$label}: updated_at должен вырасти");
        }
    }

    public function test_coverage_intake_and_import_paths_advance_updated_at(): void
    {
        $this->at($this->t0);
        $this->fakeExport('700001');
        $this->webhook('700001');
        $row = IncomingInquiry::query()->where('external_id', '700001')->sole();
        $this->assertSame(IncomingInquiry::STATE_IMPORTED, $row->state);
        $this->assertSame($this->t0->format('Y-m-d H:i:s'), $this->updatedAt($row));

        // Повторная доставка того же уведомления: меняются только webhook_count/last_notified_at — но отметка растёт.
        $this->at($this->t0->addHour());
        $this->webhook('700001');
        $fresh = IncomingInquiry::query()->findOrFail($row->id);
        $this->assertSame(2, $fresh->webhook_count);
        $this->assertSame($fresh->workflow_state, $row->workflow_state, 'бизнес-состояние повтор не трогает');
        $this->assertNull($fresh->assigned_to);
        $this->assertSame($this->t0->addHour()->format('Y-m-d H:i:s'), $this->updatedAt($row), 'шум для catch-up, но безопасный: отметка растёт');
        $this->assertSame(1, IncomingInquiry::query()->count(), 'повтор не создаёт второй источник');
    }

    public function test_coverage_failed_import_is_recorded_with_a_new_timestamp(): void
    {
        $this->at($this->t0);
        Http::swap(new HttpFactory());
        Http::fake(['tourvisor.ru/*' => Http::response('boom', 500)]);
        $this->get('/api/webhooks/tourvisor/inquiries/' . self::TOKEN . '?id=700002&type=0');

        $row = IncomingInquiry::query()->where('external_id', '700002')->sole();
        $this->assertSame(IncomingInquiry::STATE_FAILED, $row->state);
        $this->assertSame($this->t0->format('Y-m-d H:i:s'), $this->updatedAt($row));
    }

    // ====================================================================== НЕБЕЗОПАСНО

    public function test_unsafe_updated_at_misses_an_ownership_change_made_by_user_deletion(): void
    {
        $this->at($this->t0);
        $row = $this->inquiryFor($this->a, $this->tourist);
        $marker = $this->updatedAt($row);

        // Удаление аккаунта сотрудника и клиента (так делают Admin и сам пользователь) срабатывает через
        // внешний ключ ON DELETE SET NULL: приложение в incoming_inquiries ничего не записывает.
        $this->at($this->t0->addDay());
        \Illuminate\Support\Facades\DB::table('users')->where('id', $this->a->id)->delete(); // прямое удаление (сырой SQL): модельное удаление защищено UserDeletion (AOA-05-I3)
        \Illuminate\Support\Facades\DB::table('users')->where('id', $this->tourist->id)->delete();

        $after = DB::table('incoming_inquiries')->where('id', $row->id)->first();
        $this->assertNull($after->assigned_to, 'ответственный изменился (стал NULL)');
        $this->assertNull($after->client_user_id, 'клиент отвязался');
        $this->assertSame($marker, $this->updatedAt($row), 'НО updated_at не изменился');

        $seenByTimestampCursor = DB::table('incoming_inquiries')->where('updated_at', '>', $marker)->count();
        $this->assertSame(0, $seenByTimestampCursor, 'НЕБЕЗОПАСНО: курсор по updated_at изменение не видит');
    }

    public function test_unsafe_strict_updated_at_cursor_misses_a_second_change_in_the_same_second(): void
    {
        $this->at($this->t0);
        $first = $this->inquiryFor(null);
        $second = $this->inquiryFor(null);

        $this->at($this->t0->addMinute());
        app(IncomingInquiryWorkflow::class)->assign($first, $this->admin, $this->a->id);
        $marker = DB::table('incoming_inquiries')->max('updated_at'); // курсор снят после первого изменения

        // Второе изменение в ту же секунду (точность колонки — секунда).
        app(IncomingInquiryWorkflow::class)->assign($second, $this->admin, $this->b->id);
        $this->assertSame($marker, $this->updatedAt($second), 'обе правки имеют одинаковую отметку');

        $strict = DB::table('incoming_inquiries')->where('updated_at', '>', $marker)->pluck('id')->all();
        $inclusive = DB::table('incoming_inquiries')->where('updated_at', '>=', $marker)->pluck('id')->sort()->values()->all();

        $this->assertSame([], $strict, 'НЕБЕЗОПАСНО: строгое «больше» пропускает вторую правку');
        $this->assertSame([$first->id, $second->id], $inclusive, 'нестрогое «не меньше» находит её, но перечитывает первую → нужна идемпотентность');
    }

    public function test_unsafe_marker_misses_a_row_that_becomes_visible_with_an_older_timestamp(): void
    {
        $this->at($this->t0);
        $this->inquiryFor($this->a);
        $this->at($this->t0->addMinutes(10));
        $other = $this->inquiryFor($this->b);
        $marker = DB::table('incoming_inquiries')->max('updated_at'); // курсор снят: видна запись $other

        // Транзакция, начатая раньше (отметка времени взята до коммита), фиксируется после снятия курсора.
        // Здесь это смоделировано вставкой со «старой» отметкой; реальный порядок коммитов двух
        // соединений проверен на MySQL (docs/AOA-03-I4).
        $late = new IncomingInquiry();
        $late->forceFill([
            'provider' => 'tourvisor', 'external_type' => 0, 'external_id' => '799999',
            'state' => IncomingInquiry::STATE_IMPORTED, 'webhook_count' => 1,
            'created_at' => $this->t0->addMinutes(5), 'updated_at' => $this->t0->addMinutes(5),
        ])->saveQuietly();
        $this->assertNotNull($late->id);
        $this->assertGreaterThan($other->id, $late->id, 'id больше — но отметка меньше курсора');

        $this->assertSame(0, DB::table('incoming_inquiries')->where('updated_at', '>', $marker)->count(), 'НЕБЕЗОПАСНО: курсор по времени строку пропустил');
        $this->assertSame(1, DB::table('incoming_inquiries')->where('id', '>', $other->id)->count(), 'монотонный id её видит (но см. следующий тест)');
    }

    public function test_unsafe_max_id_cursor_sees_new_rows_but_misses_changes_to_old_rows(): void
    {
        $this->at($this->t0);
        $old = $this->inquiryFor(null);
        $markerId = (int) DB::table('incoming_inquiries')->max('id');

        $this->at($this->t0->addHour());
        app(IncomingInquiryWorkflow::class)->assign($old, $this->admin, $this->a->id);
        $this->assertSame([], DB::table('incoming_inquiries')->where('id', '>', $markerId)->pluck('id')->all(), 'НЕБЕЗОПАСНО: правка старой записи курсором id не видна');

        $new = $this->inquiryFor($this->b);
        $this->assertSame([$new->id], DB::table('incoming_inquiries')->where('id', '>', $markerId)->pluck('id')->map(fn ($v) => (int) $v)->all(), 'создание нового источника id-курсор видит');
    }

    public function test_unsafe_writes_outside_eloquent_do_not_move_updated_at(): void
    {
        $this->at($this->t0);
        $row = $this->inquiryFor($this->a);
        $this->at($this->t0->addHour());

        // Любой прямой SQL / query builder, не выставляющий updated_at сам (ручная правка оператором,
        // разовый скрипт), оставляет следа в отметке нет. В приложении такого пути нет — это граница гарантии.
        DB::table('incoming_inquiries')->where('id', $row->id)->update(['assigned_to' => $this->b->id]);

        $this->assertSame($this->t0->format('Y-m-d H:i:s'), $this->updatedAt($row));
        $this->assertSame(0, DB::table('incoming_inquiries')->where('updated_at', '>', $this->t0->format('Y-m-d H:i:s'))->count());
    }

    // ====================================================================== ПЕРЕЧЕНЬ ПУТЕЙ ЗАПИСИ

    /**
     * Защита от дрейфа перечня: любой НОВЫЙ код приложения, записывающий в incoming_inquiries, должен
     * появиться в инвентаризации I4 (docs/AOA-03-I4) и получить решение о захвате изменений — иначе
     * будущий catch-up молча перестанет быть полным.
     */
    public function test_the_set_of_application_files_writing_incoming_inquiries_is_the_documented_inventory(): void
    {
        $writers = [];

        foreach (File::allFiles(app_path()) as $file) {
            if ($file->getExtension() !== 'php') {
                continue;
            }

            $code = (string) preg_replace('~/\*.*?\*/|//[^\n]*|^\s*\*[^\n]*~ms', '', $file->getContents());
            $touchesTable = str_contains($code, 'IncomingInquiry') || str_contains($code, 'incoming_inquiries');
            if (! $touchesTable) {
                continue;
            }

            $path = str_replace([app_path() . DIRECTORY_SEPARATOR, '\\'], ['', '/'], $file->getPathname());

            // Эвристика, а не доказательство: оператор (между «;», «{», «}»), обращающийся к модели/таблице
            // и вызывающий записывающий метод, либо создание модели с последующим save().
            foreach (preg_split('/[;{}]/', $code) as $statement) {
                $mentions = str_contains($statement, 'IncomingInquiry::') || str_contains($statement, "table('incoming_inquiries')");
                $writes = preg_match('/->(update|delete|forceDelete|insert|insertGetId|upsert|increment|decrement)\(|::create\(/', $statement) === 1;

                if ($mentions && $writes) {
                    $writers[$path] = true;
                }
            }

            if (str_contains($code, 'new IncomingInquiry') && preg_match('/->(save|saveQuietly)\(/', $code) === 1) {
                $writers[$path] = true;
            }
        }

        $found = array_keys($writers);
        sort($found);

        // Inquiry-сторона (InquiryBackfillService и т. п.) пишет в inquiries, а не в incoming_inquiries —
        // такие файлы допускаются отдельным списком только если запись идёт НЕ в IncomingInquiry.
        $expected = [
            'Services/IncomingInquiries/IncomingInquiryWorkflow.php',
            'Services/Tourvisor/TourvisorInquiryImporter.php',
            'Services/Tourvisor/TourvisorInquiryIntake.php',
        ];

        $unexpected = array_values(array_diff($found, $expected));
        $this->assertSame([], $unexpected, 'Новый путь записи в incoming_inquiries: обновите инвентаризацию I4 и решение о захвате изменений.');
        $this->assertSame($expected, array_values(array_intersect($expected, $found)), 'Каждый документированный путь записи обнаружен');
    }
}
