<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\IncomingInquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Bookings\BookingAssignment;
use Illuminate\Auth\Notifications\ResetPassword;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Mail\Events\MessageSending;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-01-I5: документы заявки и «ссылка установки пароля» заново проверяют права
 * по актуальному ответственному в момент защищённой записи.
 *
 * Гонка моделируется детерминированно в одном процессе: сразу после ПЕРВОГО чтения
 * заявки запросом администратор переназначает её через настоящий BookingAssignment
 * (тот же приём, что в BookingWriteAuthorizationRaceTest, AOA-01-I4). Настоящая
 * конкурентность проверяется отдельно на одноразовом MySQL.
 */
class BookingDocumentAndClientAccessRaceTest extends TestCase
{
    use RefreshDatabase;
    use BuildsAoaI2bFixtures;

    private User $admin;
    private User $managerA;
    private User $managerB;
    private User $managerC;
    private User $tourist;
    private Booking $booking;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('local');

        $this->admin = $this->userWith(Role::ADMIN);
        $this->managerA = $this->userWith(Role::MANAGER);
        $this->managerB = $this->userWith(Role::MANAGER);
        $this->managerC = $this->userWith(Role::MANAGER);
        $this->tourist = $this->userWith(Role::TOURIST, [
            'email' => 'client.person@example.test',
            'email_verified_at' => null,
            'last_login_at' => null,
        ]);
        $this->booking = $this->bookingOf($this->tourist, $this->managerA);
    }

    /** После первого чтения заявки запросом администратор переназначает её на $to (один раз). */
    private function reassignAfterFirstRead(User $to): void
    {
        $fired = false;
        $booking = $this->booking;
        $adminId = $this->admin->id;
        $fromId = (int) $booking->manager_id;

        Booking::retrieved(function (Booking $read) use (&$fired, $booking, $to, $adminId, $fromId): void {
            if ($fired || $read->id !== $booking->id) {
                return;
            }
            $fired = true;

            app(BookingAssignment::class)->assign($booking, User::findOrFail($adminId), $to->id, $fromId, 'workload', null);
        });
    }

    private function upload(User $actor, string $title = 'Договор'): \Illuminate\Testing\TestResponse
    {
        return $this->actingAs($actor)->post(route('bookings.documents.store', $this->booking), [
            'title' => $title,
            'document_type' => 'contract',
            'file' => UploadedFile::fake()->create('contract.pdf', 64, 'application/pdf'),
        ]);
    }

    private function seedDocument(): BookingDocument
    {
        $path = "documents/bookings/{$this->booking->id}/seed.pdf";
        Storage::disk('local')->put($path, 'synthetic-document');

        return BookingDocument::create([
            'booking_id' => $this->booking->id,
            'document_type' => 'contract',
            'title' => 'Seed',
            'file_path' => $path,
            'file_size' => 18,
            'uploaded_by' => $this->managerA->id,
            'uploaded_at' => now(),
        ]);
    }

    /** @return list<string> */
    private function storedFiles(): array
    {
        return Storage::disk('local')->allFiles("documents/bookings/{$this->booking->id}");
    }

    // -----------------------------------------------------------------------
    // Положительные контроли: гонки нет
    // -----------------------------------------------------------------------

    public function test_current_manager_uploads_and_deletes(): void
    {
        $this->upload($this->managerA)->assertRedirect(route('bookings.show', $this->booking))->assertSessionHas('success');

        $document = BookingDocument::query()->firstOrFail();
        $this->assertSame($this->managerA->id, $document->uploaded_by);
        Storage::disk('local')->assertExists($document->file_path);

        $this->actingAs($this->managerA)
            ->delete(route('bookings.documents.destroy', [$this->booking, $document]))
            ->assertRedirect(route('bookings.show', $this->booking))
            ->assertSessionHas('success');

        $this->assertSoftDeleted('booking_documents', ['id' => $document->id]);
        Storage::disk('local')->assertMissing($document->file_path);
    }

    public function test_admin_uploads_deletes_and_downloads_any_booking_document(): void
    {
        $this->upload($this->admin)->assertSessionHas('success');
        $document = BookingDocument::query()->firstOrFail();

        $this->actingAs($this->admin)->get(route('bookings.documents.download', [$this->booking, $document]))->assertOk();
        $this->actingAs($this->admin)->delete(route('bookings.documents.destroy', [$this->booking, $document]))->assertSessionHas('success');
        $this->assertSoftDeleted('booking_documents', ['id' => $document->id]);
    }

    public function test_current_manager_and_admin_send_password_setup_once(): void
    {
        Notification::fake();

        $this->actingAs($this->managerA)
            ->post(route('bookings.client.password-setup', $this->booking))
            ->assertSessionHas('success');

        Notification::assertSentToTimes($this->tourist, ResetPassword::class, 1);

        // Повтор в пределах throttle брокера (60 с) не создаёт второго письма — прежнее поведение.
        $this->actingAs($this->admin)
            ->post(route('bookings.client.password-setup', $this->booking))
            ->assertSessionHasErrors('client_password_setup');

        Notification::assertSentToTimes($this->tourist, ResetPassword::class, 1);
    }

    public function test_former_and_unrelated_manager_are_refused_without_a_race(): void
    {
        Notification::fake();
        $document = $this->seedDocument();

        app(BookingAssignment::class)->assign($this->booking, $this->admin, $this->managerB->id, $this->managerA->id, 'workload', null);

        foreach ([$this->managerA, $this->managerC] as $actor) {
            $this->upload($actor)->assertForbidden();
            $this->actingAs($actor)->delete(route('bookings.documents.destroy', [$this->booking, $document]))->assertForbidden();
            $this->actingAs($actor)->get(route('bookings.documents.download', [$this->booking, $document]))->assertForbidden();
            $this->actingAs($actor)->post(route('bookings.client.password-setup', $this->booking))->assertForbidden();
        }

        $this->assertNotSoftDeleted('booking_documents', ['id' => $document->id]);
        $this->assertSame(['documents/bookings/' . $this->booking->id . '/seed.pdf'], $this->storedFiles());
        Notification::assertNothingSent();

        // Новый ответственный работает.
        $this->upload($this->managerB)->assertSessionHas('success');
        $this->actingAs($this->managerB)->get(route('bookings.documents.download', [$this->booking, $document]))->assertOk();
    }

    // -----------------------------------------------------------------------
    // Гонка: переназначение между первичной проверкой и записью
    // -----------------------------------------------------------------------

    public function test_stale_manager_upload_is_refused_and_leaves_no_row_or_file(): void
    {
        $this->reassignAfterFirstRead($this->managerB);

        $this->upload($this->managerA)->assertForbidden();

        $this->assertSame($this->managerB->id, $this->booking->fresh()->manager_id);
        $this->assertSame(0, BookingDocument::withTrashed()->count());
        $this->assertSame([], $this->storedFiles(), 'staged file must be cleaned up');
    }

    public function test_stale_manager_delete_is_refused_and_keeps_row_and_file(): void
    {
        $document = $this->seedDocument();
        $this->reassignAfterFirstRead($this->managerB);

        $this->actingAs($this->managerA)
            ->delete(route('bookings.documents.destroy', [$this->booking, $document]))
            ->assertForbidden();

        $this->assertNotSoftDeleted('booking_documents', ['id' => $document->id]);
        Storage::disk('local')->assertExists($document->file_path);
    }

    public function test_stale_manager_download_is_refused(): void
    {
        $document = $this->seedDocument();
        $this->reassignAfterFirstRead($this->managerB);

        $this->actingAs($this->managerA)
            ->get(route('bookings.documents.download', [$this->booking, $document]))
            ->assertForbidden();
    }

    public function test_stale_manager_password_setup_sends_nothing_and_creates_no_token(): void
    {
        Notification::fake();
        $this->reassignAfterFirstRead($this->managerB);

        $this->actingAs($this->managerA)
            ->post(route('bookings.client.password-setup', $this->booking))
            ->assertForbidden();

        Notification::assertNothingSent();
        $this->assertSame(0, DB::table('password_resets')->count());
    }

    public function test_admin_operations_survive_a_concurrent_reassignment(): void
    {
        $document = $this->seedDocument();
        $this->reassignAfterFirstRead($this->managerB);

        $this->upload($this->admin)->assertSessionHas('success');
        $this->assertSame(2, BookingDocument::count());

        $this->actingAs($this->admin)->get(route('bookings.documents.download', [$this->booking, $document]))->assertOk();
    }

    public function test_manager_stays_authorized_when_reassignment_targets_someone_else_after_the_write(): void
    {
        // Переназначение ПОСЛЕ завершения записи не влияет на уже выполненную операцию.
        $this->upload($this->managerA)->assertSessionHas('success');
        app(BookingAssignment::class)->assign($this->booking, $this->admin, $this->managerB->id, $this->managerA->id, 'workload', null);

        $this->assertSame(1, BookingDocument::count());
        $this->assertCount(1, $this->storedFiles());
    }

    // -----------------------------------------------------------------------
    // Турист: чтение документов определяется владельцем заявки, не ответственным
    // -----------------------------------------------------------------------

    public function test_tourist_owner_downloads_documents_regardless_of_reassignment_and_stranger_cannot(): void
    {
        $document = $this->seedDocument();
        $stranger = $this->userWith(Role::TOURIST);

        app(BookingAssignment::class)->assign($this->booking, $this->admin, $this->managerB->id, $this->managerA->id, 'workload', null);

        $this->actingAs($this->tourist)
            ->get(route('cabinet.documents.bookings.download', [$this->booking, $document]))
            ->assertOk();

        $this->actingAs($stranger)
            ->get(route('cabinet.documents.bookings.download', [$this->booking, $document]))
            ->assertForbidden();

        // Турист не получает доступа к staff-маршрутам документов.
        $this->actingAs($this->tourist)->get(route('bookings.documents.download', [$this->booking, $document]))->assertForbidden();
        $this->actingAs($this->tourist)->delete(route('bookings.documents.destroy', [$this->booking, $document]))->assertForbidden();
        $this->actingAs($this->tourist)->post(route('bookings.client.password-setup', $this->booking))->assertForbidden();
    }

    // -----------------------------------------------------------------------
    // Целостность: сбой записи строки не оставляет файл-сироту
    // -----------------------------------------------------------------------

    public function test_database_failure_during_upload_leaves_no_orphan_file(): void
    {
        BookingDocument::creating(function (): never {
            throw new \RuntimeException('synthetic database failure');
        });

        $this->upload($this->managerA)
            ->assertRedirect(route('bookings.show', $this->booking))
            ->assertSessionHas('error');

        $this->assertSame(0, BookingDocument::withTrashed()->count());
        $this->assertSame([], $this->storedFiles());
    }

    // -----------------------------------------------------------------------
    // Ссылка установки пароля по обращению: блокировка — в транзакции, письмо — после неё
    // -----------------------------------------------------------------------

    public function test_inquiry_password_setup_decides_under_a_real_lock_and_mails_after_commit(): void
    {
        $inquiry = $this->inquiryFor($this->managerA, $this->tourist);

        $base = DB::transactionLevel(); // RefreshDatabase уже держит одну внешнюю транзакцию
        $levelsOnRead = [];
        IncomingInquiry::retrieved(function () use (&$levelsOnRead): void {
            $levelsOnRead[] = DB::transactionLevel();
        });

        $levelsOnSend = [];
        Event::listen(MessageSending::class, function () use (&$levelsOnSend): void {
            $levelsOnSend[] = DB::transactionLevel();
        });

        $this->actingAs($this->managerA)
            ->post(route('cabinet.manager.inquiries.client.password-setup', $inquiry))
            ->assertSessionHas('success');

        $this->assertContains($base + 1, $levelsOnRead, 'the locking read must run inside a transaction (lockForUpdate is a no-op outside one)');
        $this->assertSame([$base], $levelsOnSend, 'the e-mail must be sent outside any transaction / row lock');
    }

    public function test_inquiry_password_setup_is_refused_for_a_former_assignee(): void
    {
        Notification::fake();
        $inquiry = $this->inquiryFor($this->managerA, $this->tourist);
        $inquiry->forceFill(['assigned_to' => $this->managerB->id])->save();

        $this->actingAs($this->managerA)
            ->post(route('cabinet.manager.inquiries.client.password-setup', $inquiry))
            ->assertNotFound();

        Notification::assertNothingSent();
    }

    public function test_physical_file_is_removed_only_after_the_row_is_soft_deleted(): void
    {
        $document = $this->seedDocument();
        $fileExistedWhenRowWasDeleted = null;

        BookingDocument::deleted(function (BookingDocument $deleted) use (&$fileExistedWhenRowWasDeleted): void {
            $fileExistedWhenRowWasDeleted = Storage::disk('local')->exists($deleted->file_path);
        });

        $this->actingAs($this->managerA)
            ->delete(route('bookings.documents.destroy', [$this->booking, $document]))
            ->assertSessionHas('success');

        $this->assertTrue($fileExistedWhenRowWasDeleted);
        Storage::disk('local')->assertMissing($document->file_path);
    }
}
