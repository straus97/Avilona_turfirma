<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\InquiryContact;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Services\Inquiries\InquiryContactData;
use App\Services\Inquiries\InquiryContactException;
use App\Services\Inquiries\InquiryContactStore;
use App\Support\Audit\AuditEventType;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Log\Events\MessageLogged;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use PHPUnit\Framework\Attributes\DataProvider;
use RuntimeException;
use Tests\Feature\Concerns\BuildsInquiryAwareFixtures;
use Tests\TestCase;

/**
 * AOA-04-I4, Gate A — непроверенный контакт клиента на самостоятельном обращении: права, версия
 * (включая ABA после очистки), приватность и атомарность с аудитом. Синтетические данные, SQLite :memory:.
 */
class InquiryContactCaptureTest extends TestCase
{
    use BuildsInquiryAwareFixtures;
    use RefreshDatabase;

    private const NAME = 'Синтетический Контакт-77';
    private const PHONE = '+7 900 555-44-33';
    private const MESSENGER = 'Telegram @synthetic_contact_77';

    private User $admin;
    private User $a;
    private User $b;
    private User $tourist;

    /** @var list<string> */
    private array $logged = [];

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager', ['name' => 'Анна Менеджер']);
        $this->b = $this->userWith('manager', ['name' => 'Борис Менеджер']);
        $this->tourist = $this->userWith('tourist');

        $this->logged = [];
        Event::listen(MessageLogged::class, function (MessageLogged $e): void {
            $this->logged[] = $e->message . ' ' . json_encode($e->context, JSON_UNESCAPED_UNICODE);
        });
    }

    private function mine(?User $manager = null): Inquiry
    {
        return Inquiry::factory()->managerCreated($manager ?? $this->a)->create()->refresh();
    }

    private function url(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.contact.update', $inquiry);
    }

    private function show(Inquiry $inquiry): string
    {
        return route('cabinet.manager.agency-inquiries.show', $inquiry);
    }

    /** @return array<string, mixed> */
    private function payload(int $revision, array $overrides = []): array
    {
        return array_merge([
            'revision' => $revision,
            'contact_name' => self::NAME,
            'contact_phone' => self::PHONE,
            'contact_messenger' => self::MESSENGER,
        ], $overrides);
    }

    private function putContact(User $actor, Inquiry $inquiry, array $data)
    {
        return $this->actingAs($actor)->put($this->url($inquiry), $data);
    }

    private function row(Inquiry $inquiry): ?InquiryContact
    {
        return InquiryContact::query()->find($inquiry->id);
    }

    private function events(?Inquiry $inquiry = null)
    {
        return AuditEvent::query()
            ->where('event_type', AuditEventType::InquiryContactUpdated->value)
            ->when($inquiry, fn ($q) => $q->where('subject_id', $inquiry->id))
            ->orderBy('id')
            ->get();
    }

    // ---- create / update / clear / recreate ---------------------------------------------------

    public function test_responsible_manager_creates_corrects_and_clears_the_contact(): void
    {
        $inquiry = $this->mine();
        $users = User::query()->count();
        $bookings = Booking::query()->count();
        $incoming = IncomingInquiry::query()->count();

        $this->putContact($this->a, $inquiry, $this->payload(0))
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHas('success')
            ->assertSessionHasNoErrors();

        $row = $this->row($inquiry);
        $this->assertSame(self::NAME, $row->contact_name);
        $this->assertSame(self::PHONE, $row->contact_phone);
        $this->assertSame(self::MESSENGER, $row->contact_messenger);
        $this->assertSame(1, $row->revision);
        $this->assertSame($this->a->id, $row->updated_by);

        $this->putContact($this->a, $inquiry, $this->payload(1, ['contact_phone' => '+7 900 111-22-33', 'contact_messenger' => '']))
            ->assertSessionHasNoErrors();
        $row = $this->row($inquiry);
        $this->assertSame('+7 900 111-22-33', $row->contact_phone);
        $this->assertNull($row->contact_messenger);
        $this->assertSame(2, $row->revision);

        $this->actingAs($this->a)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 2])
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHas('success');
        $row = $this->row($inquiry);
        $this->assertNotNull($row, 'очистка оставляет строку-«надгробие»');
        $this->assertFalse($row->hasValues());
        $this->assertSame(3, $row->revision);

        $this->assertSame($users, User::query()->count(), 'контакт не создаёт аккаунтов');
        $this->assertSame($bookings, Booking::query()->count());
        $this->assertSame($incoming, IncomingInquiry::query()->count());
        $this->assertNull($inquiry->refresh()->client_user_id, 'контакт не привязывает клиента');
        Mail::assertNothingSent();
        Notification::assertNothingSent();

        $chain = $this->events($inquiry)->map(fn ($e) => [$e->metadata['previous_revision'], $e->metadata['new_revision'], $e->metadata['changed_fields'], $e->metadata['cleared']])->all();
        $this->assertSame([[0, 1, 'name,phone,messenger', false], [1, 2, 'phone,messenger', false], [2, 3, 'name,phone', true]], $chain);
    }

    public function test_clearing_an_empty_contact_and_identical_values_are_no_ops(): void
    {
        $inquiry = $this->mine();

        // Нет строки и пустая форма: строка не создаётся, события нет.
        $this->putContact($this->a, $inquiry, $this->payload(0, ['contact_name' => '', 'contact_phone' => '', 'contact_messenger' => '']))
            ->assertSessionHasNoErrors();
        $this->assertNull($this->row($inquiry));
        $this->assertCount(0, $this->events());

        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();
        $this->putContact($this->a, $inquiry, $this->payload(1))->assertSessionHasNoErrors(); // те же значения
        $this->assertSame(1, $this->row($inquiry)->revision);
        $this->assertCount(1, $this->events());

        $this->actingAs($this->a)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 1]);
        $this->actingAs($this->a)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 2])
            ->assertSessionHas('success');
        $this->assertSame(2, $this->row($inquiry)->revision, 'повторная очистка пустого контакта версию не поднимает');
        $this->assertCount(2, $this->events());
    }

    public function test_values_are_normalised_without_changing_meaning(): void
    {
        $inquiry = $this->mine();

        $this->putContact($this->a, $inquiry, $this->payload(0, [
            'contact_name' => "  Иван \t  Петров\n ",
            'contact_phone' => '  +7  (900)  123-45-67 ',
            'contact_messenger' => '  @name ',
        ]))->assertSessionHasNoErrors();

        $row = $this->row($inquiry);
        $this->assertSame('Иван Петров', $row->contact_name);
        $this->assertSame('+7 (900) 123-45-67', $row->contact_phone);
        $this->assertSame('@name', $row->contact_messenger);
    }

    // ---- ABA ---------------------------------------------------------------------------------

    public function test_a_form_opened_before_clear_cannot_overwrite_after_clear_and_recreate(): void
    {
        $inquiry = $this->mine();

        $this->putContact($this->a, $inquiry, $this->payload(0, ['contact_name' => 'Первый ввод']))->assertSessionHasNoErrors();
        $staleRevision = $this->row($inquiry)->revision; // вкладка 1 открыта на версии 1

        $this->putContact($this->admin, $inquiry, $this->payload($staleRevision, ['contact_name' => 'Правка администратора']));
        $this->actingAs($this->admin)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 2]);
        $this->putContact($this->a, $inquiry, $this->payload(3, ['contact_name' => 'Повторный ввод']))->assertSessionHasNoErrors();

        $row = $this->row($inquiry);
        $this->assertSame(4, $row->revision, 'версия не сбрасывается очисткой');
        $this->assertSame('Повторный ввод', $row->contact_name);

        // Устаревшая вкладка 1 (версия 1) и версия 0 («контакта не было») отклоняются.
        foreach ([$staleRevision, 0, 2, 3] as $stale) {
            $this->putContact($this->a, $inquiry, $this->payload($stale, ['contact_name' => 'ЗАТИРАНИЕ']))
                ->assertRedirect($this->show($inquiry))
                ->assertSessionHas('error')
                ->assertSessionHas('contact_conflict');
        }

        $row = $this->row($inquiry)->refresh();
        $this->assertSame('Повторный ввод', $row->contact_name);
        $this->assertSame(4, $row->revision);
        $this->assertCount(4, $this->events($inquiry));
    }

    public function test_two_first_creations_from_version_zero_have_one_winner(): void
    {
        $inquiry = $this->mine();

        $this->putContact($this->a, $inquiry, $this->payload(0, ['contact_name' => 'Вкладка 1']))->assertSessionHasNoErrors();
        $this->putContact($this->a, $inquiry, $this->payload(0, ['contact_name' => 'Вкладка 2']))->assertSessionHas('error');

        $this->assertSame('Вкладка 1', $this->row($inquiry)->contact_name);
        $this->assertSame(1, InquiryContact::query()->count());
    }

    public function test_concurrent_clear_versus_update_loses_for_the_stale_side(): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();

        // Оба видели версию 1: очистка выигрывает, правка устарела и не воскрешает значения.
        $this->actingAs($this->admin)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 1]);
        $this->putContact($this->a, $inquiry, $this->payload(1, ['contact_name' => 'Воскрешение']))->assertSessionHas('error');

        $row = $this->row($inquiry)->refresh();
        $this->assertFalse($row->hasValues());
        $this->assertSame(2, $row->revision);
    }

    public function test_service_level_revision_is_strict_and_error_is_marked_stale(): void
    {
        $inquiry = $this->mine();
        $store = app(InquiryContactStore::class);

        $store->save($inquiry, $this->a, ['contact_name' => 'А'], 0);
        $store->clear($inquiry, $this->a, 1);

        try {
            $store->save($inquiry, $this->a, ['contact_name' => 'Б'], 1);
            $this->fail('Ожидался отказ по версии');
        } catch (InquiryContactException $e) {
            $this->assertTrue($e->isStale());
            $this->assertStringNotContainsString('Б', $e->getMessage());
        }

        $this->assertSame(2, $this->row($inquiry)->revision);
    }

    // ---- authorization -----------------------------------------------------------------------

    public function test_admin_edits_any_independent_inquiry_as_admin(): void
    {
        $inquiry = $this->mine();

        $this->putContact($this->admin, $inquiry, $this->payload(0))->assertRedirect($this->show($inquiry))->assertSessionHasNoErrors();

        $this->assertSame($this->admin->id, $this->row($inquiry)->updated_by);
        $this->assertSame($this->admin->id, (int) $this->events($inquiry)->sole()->actor_id);
    }

    public function test_foreign_manager_tourist_and_guest_are_refused_and_nothing_changes(): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();
        $before = $this->row($inquiry)->only(['contact_name', 'contact_phone', 'contact_messenger', 'revision']);

        $this->putContact($this->b, $inquiry, $this->payload(1, ['contact_name' => 'Чужой']))->assertNotFound();
        $this->actingAs($this->b)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 1])->assertNotFound();
        $this->putContact($this->tourist, $inquiry, $this->payload(1))->assertForbidden();
        $this->actingAs($this->tourist)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 1])->assertForbidden();

        auth()->logout();
        $this->putContact($this->tourist, $inquiry, [])->assertForbidden();
        auth()->logout();
        $this->call('PUT', $this->url($inquiry), $this->payload(1))->assertRedirect(route('login'));
        $this->call('DELETE', route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 1])->assertRedirect(route('login'));

        $this->assertSame($before, $this->row($inquiry)->only(array_keys($before)));
        $this->assertCount(1, $this->events());
    }

    public function test_unassigned_and_linked_inquiries_are_not_editable_through_these_routes(): void
    {
        $unassigned = Inquiry::factory()->managerCreated($this->a)->create();
        DB::table('inquiries')->where('id', $unassigned->id)->update(['assigned_to' => null]);

        $this->putContact($this->a, $unassigned, $this->payload(0))->assertNotFound();

        [, $linked] = $this->linkedPair($this->a, $this->a);
        $this->putContact($this->a, $linked, $this->payload(0))->assertNotFound();
        $this->putContact($this->admin, $linked, $this->payload(0))->assertNotFound();

        $this->assertSame(0, InquiryContact::query()->count());
    }

    public function test_reassignment_revokes_the_former_manager_immediately_and_keeps_the_contact(): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();

        $this->actingAs($this->admin)->post(route('cabinet.manager.agency-inquiries.assign', $inquiry), [
            'assignee_id' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'Синтетическая передача',
        ])->assertSessionHasNoErrors();

        // A открыл форму до передачи — запись отклоняется как для постороннего, без утечки значений.
        $this->putContact($this->a, $inquiry, $this->payload(1, ['contact_name' => 'Бывший']))->assertNotFound();
        $this->actingAs($this->a)->get($this->show($inquiry))->assertNotFound();

        // Контакт сохранился и виден новому ответственному.
        $this->actingAs($this->b)->get($this->show($inquiry))->assertOk()->assertSee(self::NAME)->assertSee(self::PHONE);
        $this->putContact($this->b, $inquiry, $this->payload(1, ['contact_name' => 'Новый ответственный']))->assertSessionHasNoErrors();
        $this->assertSame('Новый ответственный', $this->row($inquiry)->contact_name);
    }

    public function test_service_rechecks_the_actor_under_lock_and_refuses_closed_inquiries(): void
    {
        $inquiry = $this->mine();
        $store = app(InquiryContactStore::class);
        $store->save($inquiry, $this->a, ['contact_name' => 'Был'], 0);

        $this->a->update(['is_active' => false]);
        try {
            $store->save($inquiry, $this->a, ['contact_name' => 'Стал'], 1);
            $this->fail('Деактивированный сотрудник не должен менять контакт');
        } catch (\Illuminate\Auth\Access\AuthorizationException) {
        }
        $this->a->update(['is_active' => true]);

        DB::table('inquiries')->where('id', $inquiry->id)->update(['status' => Inquiry::STATUS_CLOSED, 'closed_at' => now()]);
        try {
            $store->save($inquiry, $this->a, ['contact_name' => 'Закрыто'], 1);
            $this->fail('Закрытое обращение нельзя менять');
        } catch (InquiryContactException $e) {
            $this->assertFalse($e->isStale());
        }

        $this->assertSame('Был', $this->row($inquiry)->contact_name);
    }

    // ---- validation and mass assignment ------------------------------------------------------

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidInputs(): array
    {
        return [
            'name too long' => [['contact_name' => str_repeat('Я', 121)], 'contact_name'],
            'phone too long' => [['contact_phone' => '+7 900 123-45-67 8999'], 'contact_phone'],
            'phone with letters' => [['contact_phone' => '8-900-ABC-45-67'], 'contact_phone'],
            'phone too short' => [['contact_phone' => '12-34'], 'contact_phone'],
            'phone injection' => [['contact_phone' => '+79001234567;DROP'], 'contact_phone'],
            'messenger too long' => [['contact_messenger' => str_repeat('a', 121)], 'contact_messenger'],
            'NUL in name' => [['contact_name' => "Иван\0Петров"], 'contact_name'],
            'control char in messenger' => [['contact_messenger' => "ab\x07cd"], 'contact_messenger'],
            'bidi override in name' => [['contact_name' => "Иван\u{202E}Петров"], 'contact_name'],
            'array name' => [['contact_name' => ['a']], 'contact_name'],
        ];
    }

    #[DataProvider('invalidInputs')]
    public function test_invalid_input_is_rejected_without_a_write_and_without_echoing_values(array $override, string $field): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0, ['contact_name' => 'Исходное']))->assertSessionHasNoErrors();
        $eventsBefore = $this->events()->count();

        $response = $this->putContact($this->a, $inquiry, $this->payload(1, $override));

        $response->assertSessionHasErrors($field);
        $this->assertSame('Исходное', $this->row($inquiry)->contact_name);
        $this->assertSame(1, $this->row($inquiry)->revision);
        $this->assertCount($eventsBefore, $this->events());
        foreach (session('errors')->getBag('default')->all() as $message) {
            $this->assertStringNotContainsString('DROP', $message);
        }
    }

    public function test_ordinary_validation_input_is_preserved_for_correction(): void
    {
        $inquiry = $this->mine();

        $this->actingAs($this->a)->from($this->show($inquiry))
            ->put($this->url($inquiry), $this->payload(0, ['contact_phone' => 'не телефон']))
            ->assertRedirect($this->show($inquiry))
            ->assertSessionHasErrors('contact_phone')
            ->assertSessionHasInput('contact_name', self::NAME);

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->assertSee(self::NAME);
    }

    public function test_forged_identifiers_and_protected_fields_are_rejected_wholesale(): void
    {
        $inquiry = $this->mine();
        $other = $this->mine($this->b);

        foreach ([
            ['inquiry_id' => $other->id],
            ['assigned_to' => $this->b->id],
            ['created_by' => $this->b->id],
            ['client_user_id' => $this->tourist->id],
            ['user_id' => $this->tourist->id],
            ['status' => Inquiry::STATUS_CLOSED],
            ['booking_id' => 1],
            ['updated_by' => $this->b->id],
            ['contact_email' => 'x@example.test'],
            ['passport' => '4500 123456'],
            ['revision_new' => 99],
        ] as $forged) {
            $this->putContact($this->a, $inquiry, $this->payload(0) + $forged)->assertSessionHasErrors('contact');
            $this->actingAs($this->a)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 0] + $forged)->assertSessionHasErrors('contact');
        }

        $this->assertSame(0, InquiryContact::query()->count());
        $inquiry->refresh();
        $this->assertSame($this->a->id, $inquiry->assigned_to);
        $this->assertNull($inquiry->client_user_id);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->status);
        $this->assertCount(0, $this->events());
        $this->assertSame(0, $this->events($other)->count());
    }

    public function test_missing_form_fields_are_refused_instead_of_clearing_the_contact(): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();

        $this->actingAs($this->a)->put($this->url($inquiry), ['revision' => 1])->assertSessionHasErrors(['contact_name', 'contact_phone', 'contact_messenger']);
        $this->actingAs($this->a)->put($this->url($inquiry), ['contact_name' => 'x', 'contact_phone' => '', 'contact_messenger' => ''])->assertSessionHasErrors('revision');

        $this->assertSame(self::NAME, $this->row($inquiry)->contact_name);
        $this->assertSame(1, $this->row($inquiry)->revision);
    }

    public function test_contact_model_is_closed_to_mass_assignment(): void
    {
        $inquiry = $this->mine();

        $this->expectException(MassAssignmentException::class);
        InquiryContact::query()->create(['inquiry_id' => $inquiry->id, 'contact_name' => 'x']);
    }

    public function test_schema_carries_exactly_the_approved_fields(): void
    {
        $this->assertEqualsCanonicalizing(
            ['inquiry_id', 'contact_name', 'contact_phone', 'contact_messenger', 'revision', 'updated_by', 'created_at', 'updated_at'],
            Schema::getColumnListing('inquiry_contacts')
        );
        $this->assertSame(120, InquiryContactData::NAME_MAX_LENGTH);
        $this->assertSame(20, InquiryContactData::PHONE_MAX_LENGTH);
        $this->assertSame(120, InquiryContactData::MESSENGER_MAX_LENGTH);
    }

    // ---- privacy -----------------------------------------------------------------------------

    public function test_contact_values_never_reach_audit_logs_serialisation_notifications_or_urls(): void
    {
        $inquiry = $this->mine();

        $response = $this->putContact($this->a, $inquiry, $this->payload(0));
        $response->assertRedirect($this->show($inquiry));
        $this->putContact($this->a, $inquiry, $this->payload(1, ['contact_phone' => '+7 900 000-00-01']));
        $this->actingAs($this->a)->delete(route('cabinet.manager.agency-inquiries.contact.clear', $inquiry), ['revision' => 2]);
        $this->putContact($this->a, $inquiry, $this->payload(2, ['contact_name' => 'Устаревший ПОПЫТКА-ЗАТИРАНИЯ']));

        $audit = json_encode(DB::table('audit_events')->get()->all(), JSON_UNESCAPED_UNICODE);
        $logs = implode("\n", $this->logged);
        $json = json_encode($inquiry->refresh()->load('contact')->toArray(), JSON_UNESCAPED_UNICODE)
            . json_encode($this->row($inquiry)->toArray(), JSON_UNESCAPED_UNICODE);

        foreach ([self::NAME, self::PHONE, self::MESSENGER, '900 555', 'synthetic_contact_77', '+7 900 000-00-01', 'ПОПЫТКА-ЗАТИРАНИЯ'] as $secret) {
            $this->assertStringNotContainsString($secret, $audit, "в аудите: {$secret}");
            $this->assertStringNotContainsString($secret, $logs, "в логах: {$secret}");
            $this->assertStringNotContainsString($secret, $json, "в сериализации: {$secret}");
            $this->assertStringNotContainsString($secret, (string) $response->headers->get('Location'), "в URL: {$secret}");
        }

        $this->assertSame('[]', json_encode($this->row($inquiry)->toArray()));
        Notification::assertNothingSent();
        Mail::assertNothingSent();
        $this->assertSame(0, DB::table('notifications')->count());
    }

    public function test_contact_is_visible_only_on_the_authorised_card_and_never_in_foreign_lists(): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();

        $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->assertSee(self::NAME)->assertSee(self::PHONE)->assertSee(self::MESSENGER);
        $this->actingAs($this->admin)->get($this->show($inquiry))->assertOk()->assertSee(self::NAME);
        $this->actingAs($this->b)->get($this->show($inquiry))->assertNotFound();

        foreach ([$this->b, $this->tourist] as $outsider) {
            $status = $this->actingAs($outsider)->get(route('cabinet.manager.agency-inquiries'))->getStatusCode();
            if ($status === 200) {
                $this->actingAs($outsider)->get(route('cabinet.manager.agency-inquiries'))->assertDontSee(self::NAME)->assertDontSee(self::PHONE);
            }
        }
    }

    public function test_contact_text_is_html_escaped(): void
    {
        $inquiry = $this->mine();
        $this->putContact($this->a, $inquiry, $this->payload(0, ['contact_name' => '<script>alert(1)</script>', 'contact_messenger' => '"><img src=x onerror=alert(2)>']))
            ->assertSessionHasNoErrors();

        $html = $this->actingAs($this->a)->get($this->show($inquiry))->assertOk()->getContent();
        $this->assertStringNotContainsString('<script>alert(1)</script>', $html);
        $this->assertStringNotContainsString('<img src=x', $html);
        $this->assertStringContainsString('&lt;script&gt;alert(1)&lt;/script&gt;', $html);
    }

    public function test_backfill_and_reconciliation_tooling_never_read_the_contact_table(): void
    {
        foreach (['Backfill', 'Convergence'] as $dir) {
            foreach (glob(app_path("Services/Inquiries/{$dir}/*.php")) ?: [] as $file) {
                $this->assertStringNotContainsString('inquiry_contacts', (string) file_get_contents($file), basename($file));
                $this->assertStringNotContainsString('InquiryContact', (string) file_get_contents($file), basename($file));
            }
        }
    }

    // ---- atomicity ---------------------------------------------------------------------------

    public function test_audit_failure_rolls_back_create_update_and_clear(): void
    {
        $inquiry = $this->mine();
        $store = app(InquiryContactStore::class);
        $store->save($inquiry, $this->a, ['contact_name' => 'Исходное'], 0);

        $this->app->bind(AuditRecorder::class, fn () => new class extends AuditRecorder {
            public function record(AuditEventType $type, User|int|null $actor, string $subjectType, ?int $subjectId, array $metadata = []): AuditEvent
            {
                throw new RuntimeException('audit store unavailable');
            }
        });
        $failing = app(InquiryContactStore::class);
        $second = $this->mine($this->b);

        foreach ([
            fn () => $failing->save($inquiry, $this->a, ['contact_name' => 'Правка'], 1),
            fn () => $failing->clear($inquiry, $this->a, 1),
            fn () => $failing->save($second, $this->b, ['contact_name' => 'Создание'], 0),
        ] as $attempt) {
            try {
                $attempt();
                $this->fail('Сбой аудита должен прерывать операцию');
            } catch (RuntimeException $e) {
                $this->assertSame('audit store unavailable', $e->getMessage());
            }
        }

        $row = $this->row($inquiry)->refresh();
        $this->assertSame('Исходное', $row->contact_name);
        $this->assertSame(1, $row->revision);
        $this->assertNull($this->row($second), 'создание откатывается вместе с аудитом');
    }

    public function test_draft_and_contact_revisions_are_independent(): void
    {
        $inquiry = $this->mine();

        $this->putContact($this->a, $inquiry, $this->payload(0))->assertSessionHasNoErrors();
        $this->actingAs($this->a)->put(route('cabinet.manager.agency-inquiries.draft.update', $inquiry), ['revision' => 0, 'destination_country' => 'Турция'])
            ->assertSessionHas('success');

        $inquiry->refresh();
        $this->assertSame(1, $inquiry->draft_revision);
        $this->assertSame(1, $this->row($inquiry)->revision);
        $this->putContact($this->a, $inquiry, $this->payload(1, ['contact_name' => 'Ещё раз']))->assertSessionHasNoErrors();
        $this->assertSame('Турция', $inquiry->refresh()->destination_country);
    }
}
