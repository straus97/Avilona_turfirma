<?php

namespace Tests\Feature;

use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Tests\Feature\Concerns\BuildsAoaI2bFixtures;
use Tests\TestCase;

/**
 * AOA-02 — the staff access matrix (docs/AOA-01-I1-staff-access-surface-matrix) as one
 * route × actor regression test, plus a guard that every route carrying an ownership-bearing
 * parameter is classified here. Only synthetic data.
 *
 * Owner rules under test (roadmap §A3): a Manager reaches only currently assigned inquiries and
 * bookings and the private conversation/documents of that work; Admin inspects everything;
 * Admin may WRITE to a Booking chat only when personally assigned; a Tourist reaches only their own.
 */
class StaffAccessPolicyMatrixTest extends TestCase
{
    use BuildsAoaI2bFixtures;
    use RefreshDatabase;

    private User $admin;
    private User $a;
    private User $b;
    private User $t;
    private User $u;

    private Booking $x;        // T's booking, handled by A
    private Booking $y;        // U's booking, handled by B
    private Booking $z;        // T's booking, handled personally by the Admin
    private Booking $w;        // T's booking, unassigned

    private BookingDocument $doc;
    private Message $message;
    private Message $attachmentMessage;

    private IncomingInquiry $inquiryA;
    private IncomingInquiry $inquiryB;
    private IncomingInquiry $inquiryUnassigned;
    private IncomingInquiry $inquiryAdmin;

    /** Routes that carry an ownership-bearing parameter and are exercised by this matrix. */
    private const MATRIX_ROUTES = [
        'bookings.show', 'bookings.edit', 'bookings.update', 'bookings.destroy', 'bookings.confirm', 'bookings.cancel',
        'bookings.complete', 'bookings.assign-manager', 'bookings.client.password-setup', 'bookings.documents.store',
        'bookings.documents.download', 'bookings.documents.destroy', 'cabinet.documents.bookings.download',
        'messages.read', 'messages.attachment', 'messages.destroy',
        'cabinet.chat', 'cabinet.manager.chat', 'cabinet.admin.chats',
        'cabinet.notifications.open',
        'cabinet.manager.inquiries.show', 'cabinet.manager.inquiries.claim', 'cabinet.manager.inquiries.assign',
        'cabinet.manager.inquiries.reassign', 'cabinet.manager.inquiries.close', 'cabinet.manager.inquiries.convert',
        'cabinet.manager.inquiries.client.select', 'cabinet.manager.inquiries.client.create',
        'cabinet.manager.inquiries.client.password-setup',
        // AOA-04-I1: Inquiry (manual business inquiries), exercised in test_agency_inquiry_pages_follow_current_assignment.
        'cabinet.manager.agency-inquiries.show', 'cabinet.manager.agency-inquiries.draft.update',
        // AOA-04-I2: Admin-only assignment, classified here (manager: 403 own / 404 foreign).
        'cabinet.manager.agency-inquiries.assign',
    ];

    /** Admin-only by route middleware (`role:admin`), asserted below. */
    private const ADMIN_ONLY_ROUTES = [
        'admin.assign-role', 'cabinet.admin.assign-role', 'admin.remove-role', 'cabinet.admin.remove-role',
        'admin.delete-user', 'cabinet.admin.delete-user', 'admin.user-roles', 'cabinet.admin.user-roles',
        'cabinet.admin.user-show', 'cabinet.admin.user-update-role', 'cabinet.admin.user-document.download',
        'cabinet.admin.client-requests.show', 'cabinet.admin.client-requests.link',
        'cabinet.admin.client-requests.create', 'cabinet.admin.client-requests.reject',
    ];

    /** Own-record routes: the record is looked up by the authenticated user's id. */
    private const OWN_RECORD_ROUTES = [
        'cabinet.documents.personal.download', 'cabinet.documents.personal.delete',
        'cabinet.manager.documents.download', 'cabinet.manager.documents.delete',
    ];

    protected function setUp(): void
    {
        parent::setUp();
        Mail::fake();
        Storage::fake('local');
        Role::query()->firstOrCreate(['name' => Role::TOURIST], ['description' => Role::availableRoles()[Role::TOURIST]]);

        $this->admin = $this->userWith('admin');
        $this->a = $this->userWith('manager');
        $this->b = $this->userWith('manager');
        $this->t = $this->userWith('tourist');
        $this->u = $this->userWith('tourist');

        $this->x = $this->bookingOf($this->t, $this->a);
        $this->y = $this->bookingOf($this->u, $this->b);
        $this->z = $this->bookingOf($this->t, $this->admin);
        $this->w = $this->bookingOf($this->t, null, Booking::STATUS_NEW);

        Storage::disk('local')->put('documents/bookings/matrix.pdf', 'synthetic');
        $this->doc = BookingDocument::query()->create([
            'booking_id' => $this->x->id,
            'document_type' => 'voucher',
            'title' => 'Synthetic voucher',
            'file_path' => 'documents/bookings/matrix.pdf',
            'file_size' => 9,
            'uploaded_by' => $this->a->id,
        ]);

        $this->message = Message::query()->create([
            'booking_id' => $this->x->id, 'sender_id' => $this->t->id, 'receiver_id' => $this->a->id,
            'message' => 'Синтетика', 'is_read' => false,
        ]);

        Storage::disk('local')->put('messages/matrix.pdf', 'synthetic');
        $this->attachmentMessage = Message::query()->create([
            'booking_id' => $this->x->id, 'sender_id' => $this->t->id, 'receiver_id' => $this->a->id,
            'message' => 'Вложение', 'attachment_url' => 'messages/matrix.pdf', 'is_read' => false,
        ]);

        $this->inquiryA = $this->inquiryFor($this->a);
        $this->inquiryB = $this->inquiryFor($this->b);
        $this->inquiryUnassigned = $this->inquiryFor(null);
        $this->inquiryAdmin = $this->inquiryFor($this->admin);
    }

    /** @return array<string, User> */
    private function actors(): array
    {
        return ['admin' => $this->admin, 'A' => $this->a, 'B' => $this->b, 'T' => $this->t, 'U' => $this->u];
    }

    /** @param array<string, int> $expected status per actor key */
    private function assertMatrix(string $label, string $method, string $url, array $expected, array $data = []): void
    {
        foreach ($expected as $key => $status) {
            auth()->logout();
            $actual = $this->actingAs($this->actors()[$key])->call($method, $url, $data)->getStatusCode();
            $this->assertSame($status, $actual, "$label as $key: expected $status, got $actual");
        }
        auth()->logout();
    }

    public function test_booking_pages_follow_current_assignment(): void
    {
        $this->assertMatrix('show X', 'GET', route('bookings.show', $this->x), ['admin' => 200, 'A' => 200, 'B' => 403, 'T' => 200, 'U' => 403]);
        $this->assertMatrix('show Y', 'GET', route('bookings.show', $this->y), ['admin' => 200, 'A' => 403, 'B' => 200, 'T' => 403, 'U' => 200]);
        $this->assertMatrix('show Z (Admin-owned)', 'GET', route('bookings.show', $this->z), ['admin' => 200, 'A' => 403, 'B' => 403, 'T' => 200, 'U' => 403]);
        $this->assertMatrix('show W (unassigned)', 'GET', route('bookings.show', $this->w), ['admin' => 200, 'A' => 403, 'B' => 403, 'T' => 200, 'U' => 403]);
        $this->assertMatrix('edit X', 'GET', route('bookings.edit', $this->x), ['admin' => 200, 'A' => 200, 'B' => 403, 'T' => 403, 'U' => 403]);
        $this->assertMatrix('edit Z', 'GET', route('bookings.edit', $this->z), ['admin' => 200, 'A' => 403, 'B' => 403]);
    }

    public function test_conversation_and_attachment_reads_follow_current_assignment(): void
    {
        $this->assertMatrix('messages X', 'GET', route('messages.index', ['booking_id' => $this->x->id]), ['admin' => 200, 'A' => 200, 'B' => 403, 'T' => 200, 'U' => 403]);
        $this->assertMatrix('messages Z', 'GET', route('messages.index', ['booking_id' => $this->z->id]), ['admin' => 200, 'A' => 403, 'B' => 403, 'T' => 200, 'U' => 403]);
        $this->assertMatrix('attachment X', 'GET', route('messages.attachment', $this->attachmentMessage), ['admin' => 200, 'A' => 200, 'B' => 403, 'T' => 200, 'U' => 403]);
        $this->assertMatrix('manager chat X', 'GET', route('cabinet.manager.chat', ['bookingId' => $this->x->id]), ['A' => 200, 'B' => 404, 'T' => 403]);
        $this->assertMatrix('manager chat Z', 'GET', route('cabinet.manager.chat', ['bookingId' => $this->z->id]), ['A' => 404, 'B' => 404]);
        $this->assertMatrix('admin chat X', 'GET', route('cabinet.admin.chats', ['bookingId' => $this->x->id]), ['admin' => 200, 'A' => 403, 'T' => 403]);
        $this->assertMatrix('tourist chat X', 'GET', route('cabinet.chat', ['bookingId' => $this->x->id]), ['T' => 200, 'U' => 404, 'A' => 302]);
    }

    public function test_booking_document_download_follows_current_assignment(): void
    {
        $this->assertMatrix('staff document X', 'GET', route('bookings.documents.download', [$this->x, $this->doc]), ['admin' => 200, 'A' => 200, 'B' => 403, 'T' => 403, 'U' => 403]);
        $this->assertMatrix('owner document X', 'GET', route('cabinet.documents.bookings.download', [$this->x, $this->doc]), ['T' => 200, 'U' => 403, 'A' => 302, 'B' => 302, 'admin' => 302]);
    }

    public function test_agency_inquiry_pages_follow_current_assignment(): void
    {
        $own = Inquiry::factory()->managerCreated($this->a)->create();
        $foreign = Inquiry::factory()->managerCreated($this->b)->create();
        $adminOwned = Inquiry::factory()->adminCreated($this->admin)->create();

        $this->assertMatrix('agency inquiry A', 'GET', route('cabinet.manager.agency-inquiries.show', $own), ['admin' => 200, 'A' => 200, 'B' => 404, 'T' => 403, 'U' => 403]);
        $this->assertMatrix('agency inquiry B', 'GET', route('cabinet.manager.agency-inquiries.show', $foreign), ['admin' => 200, 'A' => 404, 'B' => 200, 'T' => 403]);
        $this->assertMatrix('agency inquiry Admin-owned', 'GET', route('cabinet.manager.agency-inquiries.show', $adminOwned), ['admin' => 200, 'A' => 404, 'B' => 404, 'T' => 403]);
        $this->assertMatrix('agency draft A', 'PUT', route('cabinet.manager.agency-inquiries.draft.update', $own), ['A' => 302, 'B' => 404, 'T' => 403, 'U' => 403], ['revision' => 99]);
        $this->assertMatrix('agency draft Admin-owned', 'PUT', route('cabinet.manager.agency-inquiries.draft.update', $adminOwned), ['A' => 404, 'B' => 404, 'T' => 403], ['revision' => 99]);

        // AOA-04-I2: assignment is Admin-only. A Manager who can see the record gets 403, anyone else 404 (no
        // foreign record is confirmed); Tourists are stopped by the role middleware. Denied calls change nothing.
        $assign = fn (Inquiry $i, User $to, ?User $from): array => [
            'assignee_id' => $to->id, 'expected_assignee_id' => $from?->id, 'reason' => 'Синтетическая проверка матрицы',
        ];
        $this->assertMatrix('agency assign own', 'POST', route('cabinet.manager.agency-inquiries.assign', $own), ['A' => 403, 'B' => 404, 'T' => 403, 'U' => 403], $assign($own, $this->b, $this->a));
        $this->assertMatrix('agency assign foreign', 'POST', route('cabinet.manager.agency-inquiries.assign', $foreign), ['A' => 404, 'B' => 403, 'T' => 403], $assign($foreign, $this->a, $this->b));
        $this->assertMatrix('agency assign Admin-owned', 'POST', route('cabinet.manager.agency-inquiries.assign', $adminOwned), ['A' => 404, 'B' => 404, 'T' => 403], $assign($adminOwned, $this->a, $this->admin));
        $this->assertSame($this->a->id, $own->refresh()->assigned_to);
        $this->assertSame($this->b->id, $foreign->refresh()->assigned_to);
        $this->assertSame(0, \App\Models\AuditEvent::query()->count());

        $this->assertMatrix('agency assign (Admin)', 'POST', route('cabinet.manager.agency-inquiries.assign', $own), ['admin' => 302], $assign($own, $this->b, $this->a));
        $this->assertSame($this->b->id, $own->refresh()->assigned_to);
        $this->assertMatrix('agency show after transfer', 'GET', route('cabinet.manager.agency-inquiries.show', $own), ['admin' => 200, 'A' => 404, 'B' => 200]);
        $this->assertMatrix('agency draft after transfer', 'PUT', route('cabinet.manager.agency-inquiries.draft.update', $own), ['A' => 404, 'B' => 302], ['revision' => 99]);
    }

    public function test_inquiry_pages_follow_current_assignment(): void
    {
        $this->assertMatrix('inquiry A', 'GET', route('cabinet.manager.inquiries.show', $this->inquiryA), ['admin' => 200, 'A' => 200, 'B' => 404, 'T' => 403]);
        $this->assertMatrix('inquiry B', 'GET', route('cabinet.manager.inquiries.show', $this->inquiryB), ['admin' => 200, 'A' => 404, 'B' => 200]);
        $this->assertMatrix('inquiry unassigned', 'GET', route('cabinet.manager.inquiries.show', $this->inquiryUnassigned), ['admin' => 200, 'A' => 404, 'B' => 404]);
        $this->assertMatrix('inquiry Admin-owned', 'GET', route('cabinet.manager.inquiries.show', $this->inquiryAdmin), ['admin' => 200, 'A' => 404, 'B' => 404]);
    }

    public function test_inquiry_list_contains_only_the_managers_own_inquiries(): void
    {
        $html = $this->actingAs($this->a)->get(route('cabinet.manager.inquiries'))->assertOk()->getContent();
        foreach ([$this->inquiryA] as $own) {
            $this->assertStringContainsString((string) $own->external_id, $html);
        }
        foreach ([$this->inquiryB, $this->inquiryUnassigned, $this->inquiryAdmin] as $foreign) {
            $this->assertStringNotContainsString((string) $foreign->external_id, $html);
        }

        $adminHtml = $this->actingAs($this->admin)->get(route('cabinet.manager.inquiries'))->assertOk()->getContent();
        foreach ([$this->inquiryA, $this->inquiryB, $this->inquiryUnassigned, $this->inquiryAdmin] as $each) {
            $this->assertStringContainsString((string) $each->external_id, $adminHtml);
        }
    }

    public function test_foreign_booking_writes_are_refused_and_change_nothing(): void
    {
        $before = $this->x->fresh()->only(['status', 'manager_id', 'manager_notes', 'total_price']);

        $cases = [
            ['POST', route('bookings.confirm', $this->x)],
            ['POST', route('bookings.cancel', $this->x)],
            ['POST', route('bookings.complete', $this->x)],
            ['PUT', route('bookings.update', $this->x), ['status' => Booking::STATUS_CONFIRMED, 'manager_notes' => 'чужая заметка']],
            ['DELETE', route('bookings.destroy', $this->x)],
            ['POST', route('bookings.assign-manager', $this->x), ['manager_id' => $this->b->id, 'expected_manager_id' => $this->a->id, 'reason_code' => 'workload']],
            ['POST', route('bookings.client.password-setup', $this->x)],
        ];

        foreach ([$this->b, $this->u] as $intruder) {
            foreach ($cases as $case) {
                [$method, $url] = $case;
                $data = $case[2] ?? [];
                $status = $this->actingAs($intruder)->call($method, $url, $data)->getStatusCode();
                $this->assertContains($status, [403, 404], "$method $url as {$intruder->id}: $status");
            }
        }

        // The responsible Manager may not reassign their own booking either.
        $this->actingAs($this->a)->post(route('bookings.assign-manager', $this->x), [
            'manager_id' => $this->b->id, 'expected_manager_id' => $this->a->id, 'reason_code' => 'workload',
        ])->assertForbidden();

        $this->assertSame($before, $this->x->fresh()->only(['status', 'manager_id', 'manager_notes', 'total_price']));
    }

    public function test_foreign_documents_and_messages_cannot_be_written(): void
    {
        $this->actingAs($this->b)->post(route('bookings.documents.store', $this->x), [
            'document_type' => 'voucher', 'title' => 'Чужой', 'file' => UploadedFile::fake()->create('f.pdf', 10, 'application/pdf'),
        ])->assertForbidden();
        $this->actingAs($this->b)->delete(route('bookings.documents.destroy', [$this->x, $this->doc]))->assertForbidden();
        $this->actingAs($this->b)->postJson(route('messages.store'), [
            'booking_id' => $this->x->id, 'receiver_id' => $this->t->id, 'message' => 'вторжение',
        ])->assertForbidden();
        $this->actingAs($this->b)->postJson(route('messages.read', $this->message))->assertForbidden();
        $this->actingAs($this->b)->deleteJson(route('messages.destroy', $this->message))->assertForbidden();

        $this->assertDatabaseHas('booking_documents', ['id' => $this->doc->id]);
        $this->assertDatabaseHas('messages', ['id' => $this->message->id, 'is_read' => false]);
        $this->assertDatabaseMissing('messages', ['message' => 'вторжение']);
        $this->assertSame(1, BookingDocument::query()->where('booking_id', $this->x->id)->count());
    }

    public function test_admin_chat_contract_is_read_everything_write_only_when_assigned(): void
    {
        // Reads any Booking conversation…
        $this->actingAs($this->admin)->getJson(route('messages.index', ['booking_id' => $this->x->id]))->assertOk();
        // …but writes only where personally assigned.
        $this->actingAs($this->admin)->postJson(route('messages.store'), [
            'booking_id' => $this->x->id, 'receiver_id' => $this->t->id, 'message' => 'надзор не пишет',
        ])->assertForbidden();
        $this->assertDatabaseMissing('messages', ['message' => 'надзор не пишет']);

        $this->actingAs($this->admin)->postJson(route('messages.store'), [
            'booking_id' => $this->z->id, 'receiver_id' => $this->t->id, 'message' => 'личная заявка',
        ])->assertSuccessful();
        $this->assertDatabaseHas('messages', ['booking_id' => $this->z->id, 'message' => 'личная заявка']);
    }

    public function test_inquiry_writes_follow_the_assignment_policy_and_change_nothing(): void
    {
        $snapshot = fn (): array => IncomingInquiry::query()->orderBy('id')->get(['id', 'assigned_to', 'workflow_state', 'booking_id', 'client_user_id'])->toArray();
        $before = $snapshot();

        // Manager A: cannot claim/assign unassigned intake, cannot touch colleagues' or Admin-owned inquiries.
        // Unassigned intake is invisible to a Manager (404, nothing disclosed); their own inquiry is visible but not theirs to reassign (403).
        $this->actingAs($this->a)->post(route('cabinet.manager.inquiries.claim', $this->inquiryUnassigned))->assertNotFound();
        $this->actingAs($this->a)->post(route('cabinet.manager.inquiries.assign', $this->inquiryUnassigned), ['assigned_to' => $this->a->id])->assertNotFound();
        $this->actingAs($this->a)->post(route('cabinet.manager.inquiries.close', $this->inquiryUnassigned))->assertNotFound();
        $this->actingAs($this->a)->post(route('cabinet.manager.inquiries.reassign', $this->inquiryA), ['assigned_to' => $this->b->id, 'expected_assignee_id' => $this->a->id, 'reason' => 'сам себе'])->assertForbidden();
        $this->actingAs($this->a)->post(route('cabinet.manager.inquiries.claim', $this->inquiryA))->assertForbidden();

        foreach ([$this->inquiryB, $this->inquiryAdmin] as $foreign) {
            foreach (['close', 'convert', 'client.select', 'client.create', 'client.password-setup', 'claim', 'reassign'] as $action) {
                $status = $this->actingAs($this->a)->post(route("cabinet.manager.inquiries.$action", $foreign), [
                    'assigned_to' => $this->a->id, 'expected_assignee_id' => $this->b->id, 'reason' => 'вторжение', 'client_id' => $this->t->id,
                ])->getStatusCode();
                $this->assertContains($status, [403, 404], "$action on foreign inquiry: $status");
            }
        }

        $this->assertSame($before, $snapshot());
    }

    public function test_admin_alone_allocates_and_reassigns_inquiries(): void
    {
        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.assign', $this->inquiryUnassigned), ['assigned_to' => $this->b->id])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->b->id, $this->inquiryUnassigned->fresh()->assigned_to);

        // Reassignment needs a reason, and the former assignee immediately loses access.
        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.reassign', $this->inquiryUnassigned), ['assigned_to' => $this->a->id, 'expected_assignee_id' => $this->b->id, 'reason' => ''])
            ->assertSessionHasErrors();
        $this->assertSame($this->b->id, $this->inquiryUnassigned->fresh()->assigned_to);

        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.reassign', $this->inquiryUnassigned), ['assigned_to' => $this->a->id, 'expected_assignee_id' => $this->b->id, 'reason' => 'нагрузка по обращениям'])
            ->assertSessionHasNoErrors();
        $this->assertSame($this->a->id, $this->inquiryUnassigned->fresh()->assigned_to);

        $this->actingAs($this->b)->get(route('cabinet.manager.inquiries.show', $this->inquiryUnassigned))->assertNotFound();
        $this->actingAs($this->a)->get(route('cabinet.manager.inquiries.show', $this->inquiryUnassigned))->assertOk();
    }

    public function test_assignment_targets_must_be_eligible_employees(): void
    {
        $inactiveManager = $this->userWith('manager', ['is_active' => false]);

        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.assign', $this->inquiryUnassigned), ['assigned_to' => $this->t->id])
            ->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('cabinet.manager.inquiries.assign', $this->inquiryUnassigned), ['assigned_to' => $inactiveManager->id])
            ->assertSessionHasErrors();
        $this->assertNull($this->inquiryUnassigned->fresh()->assigned_to);

        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->w), ['manager_id' => $this->t->id, 'expected_manager_id' => null, 'reason_code' => 'workload'])
            ->assertSessionHasErrors();
        $this->actingAs($this->admin)->post(route('bookings.assign-manager', $this->w), ['manager_id' => $inactiveManager->id, 'expected_manager_id' => null, 'reason_code' => 'workload'])
            ->assertSessionHasErrors();
        $this->assertNull($this->w->fresh()->manager_id);
    }

    public function test_manager_client_directory_is_limited_to_current_work(): void
    {
        $shared = $this->userWith('tourist', ['name' => 'Общий Клиент']);
        $stranger = $this->userWith('tourist', ['name' => 'Посторонний Клиент']);
        $this->bookingOf($shared, $this->b);

        $html = $this->actingAs($this->a)->get(route('cabinet.manager.clients'))->assertOk()->getContent();
        $this->assertStringNotContainsString('Общий Клиент', $html);
        $this->assertStringNotContainsString('Посторонний Клиент', $html);

        $this->bookingOf($shared, $this->a);
        $html = $this->actingAs($this->a)->get(route('cabinet.manager.clients'))->assertOk()->getContent();
        $this->assertStringContainsString('Общий Клиент', $html);
        $this->assertStringNotContainsString('Посторонний Клиент', $html);

        $adminHtml = $this->actingAs($this->admin)->get(route('cabinet.manager.clients'))->assertOk()->getContent();
        $this->assertStringContainsString('Посторонний Клиент', $adminHtml);
        $this->assertNotNull($stranger->id);
    }

    public function test_tourist_has_no_access_to_any_staff_surface(): void
    {
        foreach ([
            route('cabinet.manager.inquiries'),
            route('cabinet.manager.inquiries.show', $this->inquiryA),
            route('cabinet.manager.clients'),
            route('cabinet.manager.bookings'),
            route('cabinet.admin.bookings'),
            route('cabinet.admin.client-requests'),
        ] as $url) {
            $this->actingAs($this->t)->get($url)->assertForbidden();
        }
    }

    public function test_every_ownership_route_is_classified_by_this_matrix(): void
    {
        $unclassified = [];
        $adminOnlyWithoutMiddleware = [];
        $inspected = 0;

        foreach (Route::getRoutes() as $route) {
            if (! preg_match('/\{(booking|inquiry|message|clientRequest|notification|document|user|role)\}|\{bookingId\??\}/', $route->uri())) {
                continue;
            }

            $name = $route->getName();

            // Closure-only redirects carry no data (legacy URL aliases).
            if ($route->getActionName() === 'Closure') {
                continue;
            }

            $inspected++;

            if ($name !== null && in_array($name, self::MATRIX_ROUTES, true)) {
                continue;
            }

            if ($name !== null && in_array($name, self::OWN_RECORD_ROUTES, true)) {
                continue;
            }

            if ($name !== null && in_array($name, self::ADMIN_ONLY_ROUTES, true)) {
                if (! in_array('role:admin', $route->gatherMiddleware(), true)) {
                    $adminOnlyWithoutMiddleware[] = $name;
                }
                continue;
            }

            $unclassified[] = ($name ?? $route->uri()) . ' [' . implode('|', $route->methods()) . ']';
        }

        $this->assertGreaterThan(40, $inspected, 'The guard no longer finds the ownership-bearing routes');
        $this->assertSame([], $adminOnlyWithoutMiddleware, 'Admin-only routes lost their role:admin middleware');
        $this->assertSame([], $unclassified, 'New ownership-bearing routes must be added to the access matrix (StaffAccessPolicyMatrixTest) and the policy layer');
    }
}
