<?php

namespace Tests\Feature;

use App\Events\ManagerAssigned;
use App\Mail\NewMessageReceived as NewMessageReceivedMail;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Services\Bookings\BookingAssignment;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Monolog\Handler\TestHandler;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I4 (I3-F2): письмо в очереди, поставленное прежнему ответственному до переназначения,
 * при отправке не должно ни уходить, ни падать: оно пропускается намеренно, без повторов,
 * без записи в failed_jobs и без раскрытия данных заявки. Реальный сбой доставки остаётся виден.
 *
 * Используется настоящая очередь (database), настоящий воркер и почтовый транспорт array.
 */
class StaleBookingMailDeliveryTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAoaI2bFixtures;

    private const SECRET_TEXT = 'Секретный текст переписки ZX-4471';

    private User $admin;
    private User $managerA;
    private User $managerB;
    private User $tourist;
    private TestHandler $logs;

    protected function setUp(): void
    {
        parent::setUp();

        config([
            'mail.default' => 'array',
            'queue.default' => 'database',
            'queue.failed.driver' => 'database-uuids',
            'queue.failed.database' => config('database.default'),
        ]);

        $this->logs = new TestHandler();
        Log::driver()->getLogger()->pushHandler($this->logs);

        $this->admin = $this->userWith(Role::ADMIN);
        $this->managerA = $this->userWith(Role::MANAGER, ['email' => 'manager.a@example.test']);
        $this->managerB = $this->userWith(Role::MANAGER, ['email' => 'manager.b@example.test']);
        $this->tourist = $this->userWith(Role::TOURIST, ['email' => 'tourist@example.test']);
    }

    private function runWorker(int $tries = 3): void
    {
        $this->artisan('queue:work', [
            '--stop-when-empty' => true,
            '--tries' => $tries,
            '--sleep' => 0,
            '--quiet' => true,
        ])->run();
    }

    /** @return array<int, \Symfony\Component\Mime\Email> */
    private function sent(): array
    {
        return Mail::mailer('array')->getSymfonyTransport()->messages()
            ->map(fn ($m) => $m->getOriginalMessage())->all();
    }

    private function recipients(): array
    {
        return collect($this->sent())
            ->flatMap(fn ($mail) => collect($mail->getTo())->map(fn ($a) => $a->getAddress()))
            ->values()->all();
    }

    private function reassign(Booking $booking, User $from, User $to): void
    {
        app(BookingAssignment::class)->assign($booking, $this->admin, $to->id, $from->id, 'workload', null);
    }

    /** Турист пишет ответственному A; письмо ставится в очередь, но воркер ещё не запущен. */
    private function touristWritesTo(Booking $booking, User $manager): Message
    {
        $this->actingAs($this->tourist)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $manager->id,
            'message' => self::SECRET_TEXT,
        ])->assertOk();

        return Message::query()->latest('id')->firstOrFail();
    }

    private function assertNoSensitiveLogs(): void
    {
        foreach ($this->logs->getRecords() as $record) {
            $dump = $record->message . json_encode($record->context, JSON_UNESCAPED_UNICODE);
            $this->assertStringNotContainsString(self::SECRET_TEXT, $dump);
            $this->assertStringNotContainsString('example.test', $dump, 'В логах не должно быть адресов почты.');
        }
    }

    // -----------------------------------------------------------------------
    // NewMessageReceived
    // -----------------------------------------------------------------------

    public function test_message_email_is_delivered_to_authorized_recipient(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->runWorker();

        $this->assertSame(['manager.a@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_stale_message_email_for_former_manager_is_skipped_cleanly(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);

        $this->reassign($booking, $this->managerA, $this->managerB);
        $this->runWorker();

        $this->assertSame([], $this->recipients(), 'Прежнему ответственному письмо уходить не должно.');
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'Ожидаемое бизнес-условие не должно создавать failed job.');
        $this->assertSame(0, DB::table('jobs')->count(), 'Задача завершена, а не оставлена на повтор.');
        $this->assertNoSensitiveLogs();
    }

    public function test_stale_message_email_is_not_retried(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);
        $this->reassign($booking, $this->managerA, $this->managerB);

        $this->runWorker(tries: 3);

        // Пропущенное письмо — успешное завершение: ни повторов, ни записей об исключениях.
        $this->assertSame(0, DB::table('jobs')->count());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertFalse(
            collect($this->logs->getRecords())->contains(fn ($r) => str_contains($r->message, 'LogicException') || str_contains((string) json_encode($r->context), 'LogicException'))
        );
    }

    public function test_reassignment_away_and_back_before_processing_delivers_normally(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);

        $this->reassign($booking, $this->managerA, $this->managerB);
        $this->reassign($booking->refresh(), $this->managerB, $this->managerA);
        $this->runWorker();

        $this->assertSame(['manager.a@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_stale_job_does_not_redirect_mail_to_the_new_manager(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);
        $this->reassign($booking, $this->managerA, $this->managerB);
        $this->runWorker();

        $this->assertNotContains('manager.b@example.test', $this->recipients());
    }

    public function test_tourist_recipient_is_unaffected_by_reassignment(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->actingAs($this->managerA)->postJson(route('messages.store'), [
            'booking_id' => $booking->id,
            'receiver_id' => $this->tourist->id,
            'message' => self::SECRET_TEXT,
        ])->assertOk();

        $this->reassign($booking, $this->managerA, $this->managerB);
        $this->runWorker();

        $this->assertSame(['tourist@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_notification_records_are_not_deleted_by_skipping_the_mail(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);
        $before = DB::table('notifications')->count();
        $this->assertSame(1, $before);

        $this->reassign($booking, $this->managerA, $this->managerB);
        $this->runWorker();

        $this->assertSame($before, DB::table('notifications')->count());
    }

    public function test_genuine_delivery_failure_is_not_swallowed(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);

        Event::listen(MessageSending::class, function (): void {
            throw new \RuntimeException('synthetic transport failure');
        });

        $this->runWorker(tries: 1);

        $this->assertSame([], $this->recipients());
        $this->assertSame(1, DB::table('failed_jobs')->count(), 'Реальный сбой доставки должен остаться виден.');
        $this->assertStringContainsString('synthetic transport failure', (string) DB::table('failed_jobs')->value('exception'));
    }

    public function test_mailable_send_is_a_noop_for_a_stale_recipient_and_throws_for_nothing_else(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $message = Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $this->tourist->id,
            'receiver_id' => $this->managerA->id,
            'message' => self::SECRET_TEXT,
        ]);
        $this->reassign($booking, $this->managerA, $this->managerB);

        $result = (new NewMessageReceivedMail($message->refresh()))->send(Mail::mailer('array'));

        $this->assertNull($result);
        $this->assertSame([], $this->sent());
    }

    // -----------------------------------------------------------------------
    // AOA-01-I5: сообщение удалено до доставки письма
    // -----------------------------------------------------------------------

    public function test_email_for_a_deleted_message_is_skipped_without_failed_job_or_content(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $message = $this->touristWritesTo($booking, $this->managerA);
        $this->assertSame(1, DB::table('jobs')->count());

        $this->actingAs($this->tourist)->deleteJson(route('messages.destroy', $message))->assertOk();
        $this->assertNull(Message::query()->find($message->id));

        $this->runWorker(tries: 3);

        $this->assertSame([], $this->recipients(), 'Удалённое сообщение не должно рассылаться.');
        $this->assertSame(0, DB::table('failed_jobs')->count(), 'Намеренно удалённое сообщение не должно создавать failed job.');
        $this->assertSame(0, DB::table('jobs')->count(), 'Задача завершена, а не оставлена на повтор.');
        $this->assertNoSensitiveLogs();
    }

    public function test_admin_deleting_a_message_also_skips_its_pending_email(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $message = $this->touristWritesTo($booking, $this->managerA);

        $this->actingAs($this->admin)->deleteJson(route('messages.destroy', $message))->assertOk();
        $this->runWorker();

        $this->assertSame([], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_only_the_deleted_messages_email_is_skipped(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $deleted = $this->touristWritesTo($booking, $this->managerA);
        $this->touristWritesTo($booking, $this->managerA);
        $this->assertSame(2, DB::table('jobs')->count());

        $this->actingAs($this->tourist)->deleteJson(route('messages.destroy', $deleted))->assertOk();
        $this->runWorker();

        $this->assertSame(['manager.a@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_deleted_message_mailable_send_is_a_noop_and_real_failures_still_surface(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $message = Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $this->tourist->id,
            'receiver_id' => $this->managerA->id,
            'message' => self::SECRET_TEXT,
        ]);
        $mailable = new NewMessageReceivedMail($message);
        $message->delete();

        $this->assertNull($mailable->to($this->managerA->email)->send(Mail::mailer('array')));
        $this->assertSame([], $this->sent());

        // Неожиданный сбой (не «записи нет») не подавляется.
        Event::listen(MessageSending::class, function (): void {
            throw new \RuntimeException('synthetic transport failure');
        });
        $kept = Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $this->tourist->id,
            'receiver_id' => $this->managerA->id,
            'message' => 'ok',
        ]);
        $this->expectException(\RuntimeException::class);
        (new NewMessageReceivedMail($kept))->to($this->managerA->email)->send(Mail::mailer('array'));
    }

    // -----------------------------------------------------------------------
    // BookingStatusChanged / ManagerAssigned — тот же дефект у соседних писем
    // -----------------------------------------------------------------------

    public function test_stale_status_changed_email_for_former_manager_is_skipped(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);

        $this->actingAs($this->managerA)->post(route('bookings.confirm', $booking))->assertRedirect();
        $this->assertSame(2, DB::table('jobs')->count(), 'Письма клиенту и ответственному поставлены в очередь.');

        $this->reassign($booking->refresh(), $this->managerA, $this->managerB);
        $this->runWorker();

        $this->assertSame(['tourist@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
        $this->assertSame(0, DB::table('jobs')->count());
    }

    public function test_stale_manager_assigned_email_for_former_manager_is_skipped(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        event(new ManagerAssigned($booking->refresh()));
        $this->assertSame(2, DB::table('jobs')->count());

        $this->reassign($booking->refresh(), $this->managerA, $this->managerB);
        $this->runWorker();

        $this->assertSame(['tourist@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }

    public function test_status_changed_email_is_delivered_to_current_manager_and_owner(): void
    {
        $booking = $this->bookingOf($this->tourist, $this->managerA);
        $this->actingAs($this->managerA)->post(route('bookings.confirm', $booking))->assertRedirect();
        $this->runWorker();

        $this->assertEqualsCanonicalizing(['tourist@example.test', 'manager.a@example.test'], $this->recipients());
        $this->assertSame(0, DB::table('failed_jobs')->count());
    }
}
