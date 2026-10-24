<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Database\QueryException;
use Illuminate\Database\UniqueConstraintViolationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

/**
 * AOA-03-I1 — независимое от провайдера обращение агентства: схема, модель, инварианты,
 * происхождение, политика и сохранность IncomingInquiry. Синтетические данные, SQLite :memory:.
 */
class InquiryFoundationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
    }

    private function userWithRoles(array $roleNames): User
    {
        $user = User::factory()->create();

        foreach ($roleNames as $roleName) {
            $role = Role::query()->firstOrCreate(
                ['name' => $roleName],
                ['description' => Role::availableRoles()[$roleName] ?? $roleName]
            );
            $user->roles()->attach($role->id);
        }

        return $user;
    }

    private function incoming(array $overrides = []): IncomingInquiry
    {
        $incoming = new IncomingInquiry();
        $incoming->forceFill(array_merge([
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => '000123456',
            'state' => IncomingInquiry::STATE_IMPORTED,
            'webhook_count' => 1,
            'client_name' => 'Синтетический Клиент',
            'client_phone' => '+7 (900) 123-45-67',
            'client_email' => 'synthetic.client@example.test',
        ], $overrides))->save();

        return $incoming->refresh();
    }

    // ---------------------------------------------------------------- schema

    public function test_inquiries_table_has_expected_columns_and_no_contact_or_secret_columns(): void
    {
        $this->assertTrue(Schema::hasTable('inquiries'));
        $this->assertTrue(Schema::hasColumns('inquiries', [
            'origin', 'status', 'incoming_inquiry_id', 'created_by', 'assigned_to', 'assigned_at',
            'client_user_id', 'client_linked_by', 'client_linked_at',
            'booking_id', 'converted_at', 'converted_by', 'closed_at', 'closed_by', 'close_reason',
            'draft_revision', 'draft_updated_by', 'draft_updated_at',
        ]));

        foreach (Inquiry::DRAFT_FIELDS as $field) {
            $this->assertTrue(Schema::hasColumn('inquiries', $field), $field);
        }

        $columns = Schema::getColumnListing('inquiries');
        foreach (['client_name', 'client_phone', 'client_email', 'password', 'token', 'passport', 'vendor_payload', 'provider', 'external_id'] as $forbidden) {
            $this->assertNotContains($forbidden, $columns, $forbidden);
        }
    }

    public function test_migration_is_additive_and_reversible(): void
    {
        $legacyColumns = Schema::getColumnListing('incoming_inquiries');
        $bookingColumns = Schema::getColumnListing('bookings');

        $migration = require base_path('database/migrations/2026_10_24_000000_create_inquiries_table.php');

        $migration->down();
        $this->assertFalse(Schema::hasTable('inquiries'));
        $this->assertSame($legacyColumns, Schema::getColumnListing('incoming_inquiries'));
        $this->assertSame($bookingColumns, Schema::getColumnListing('bookings'));

        $migration->up();
        $this->assertTrue(Schema::hasTable('inquiries'));
        $this->assertSame($legacyColumns, Schema::getColumnListing('incoming_inquiries'));
    }

    public function test_migration_does_not_backfill_existing_records(): void
    {
        $this->incoming(['external_id' => '1']);
        $this->incoming(['external_id' => '2']);

        $migration = require base_path('database/migrations/2026_10_24_000000_create_inquiries_table.php');
        $migration->down();
        $migration->up();

        $this->assertSame(0, DB::table('inquiries')->count());
        $this->assertSame(2, IncomingInquiry::query()->count());
    }

    // ----------------------------------------------------- provenance / unique

    public function test_external_inquiry_links_to_its_source_without_copying_identifiers(): void
    {
        $incoming = $this->incoming(['external_id' => '000123456']);

        $inquiry = Inquiry::factory()->externalIntake($incoming)->create();

        $this->assertSame(Inquiry::ORIGIN_EXTERNAL_INTAKE, $inquiry->origin);
        $this->assertSame(Inquiry::STATUS_NEW, $inquiry->status);
        $this->assertNull($inquiry->assigned_to);
        $this->assertNull($inquiry->created_by);
        // Ведущие нули остаются в источнике, а не копируются и не теряются.
        $this->assertSame('000123456', $inquiry->incomingInquiry->external_id);
        $this->assertSame('tourvisor', $inquiry->incomingInquiry->provider);
    }

    public function test_second_inquiry_for_the_same_incoming_inquiry_is_rejected_by_the_database(): void
    {
        $incoming = $this->incoming();
        Inquiry::factory()->externalIntake($incoming)->create();

        $this->expectException(UniqueConstraintViolationException::class);

        Inquiry::factory()->externalIntake($incoming)->create();
    }

    public function test_source_cannot_be_deleted_while_a_business_inquiry_references_it(): void
    {
        $incoming = $this->incoming();
        Inquiry::factory()->externalIntake($incoming)->create();

        try {
            $incoming->delete();
            $this->fail('Provenance source was deleted.');
        } catch (QueryException) {
            $this->assertSame(1, IncomingInquiry::query()->whereKey($incoming->id)->count());
            $this->assertSame(1, Inquiry::query()->count());
        }
    }

    public function test_one_booking_cannot_be_linked_to_two_inquiries(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $booking = $this->booking($tourist, $manager);

        DB::table('inquiries')->insert($this->rawRow(['booking_id' => $booking->id, 'status' => 'converted', 'converted_at' => now()]));

        $this->expectException(UniqueConstraintViolationException::class);

        DB::table('inquiries')->insert($this->rawRow(['booking_id' => $booking->id, 'status' => 'converted', 'converted_at' => now()]));
    }

    private function booking(User $client, User $manager): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create([
            'user_id' => $client->id,
            'manager_id' => $manager->id,
            'status' => Booking::STATUS_PROGRESS,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => 150000,
        ]));
    }

    private function rawRow(array $overrides): array
    {
        return array_merge([
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE,
            'status' => 'new',
            'draft_revision' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ], $overrides);
    }

    // ---------------------------------------------------- ownership / creation

    public function test_manager_created_inquiry_belongs_to_its_creator(): void
    {
        $manager = $this->userWithRoles(['manager']);

        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $inquiry->status);
        $this->assertSame($manager->id, $inquiry->created_by);
        $this->assertSame($manager->id, $inquiry->assigned_to);
        $this->assertTrue($inquiry->creator->is($manager));
        $this->assertTrue($inquiry->assignee->is($manager));
    }

    public function test_manager_created_inquiry_cannot_be_created_for_another_employee(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $other = $this->userWithRoles(['manager']);

        $this->expectException(InquiryInvariantViolation::class);

        Inquiry::factory()->managerCreated($manager)->assignedTo($other)->create();
    }

    public function test_admin_created_inquiry_defaults_to_admin_or_an_explicit_employee(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $manager = $this->userWithRoles(['manager']);

        $own = Inquiry::factory()->adminCreated($admin)->create();
        $delegated = Inquiry::factory()->adminCreated($admin, $manager)->create();

        $this->assertSame($admin->id, $own->assigned_to);
        $this->assertSame($manager->id, $delegated->assigned_to);
        $this->assertSame($admin->id, $delegated->created_by);
    }

    public function test_status_and_origin_are_closed_vocabularies(): void
    {
        $manager = $this->userWithRoles(['manager']);

        foreach ([['status' => 'draft'], ['origin' => 'tourvisor']] as $bad) {
            try {
                Inquiry::factory()->managerCreated($manager)->state($bad)->create();
                $this->fail('Accepted ' . json_encode($bad));
            } catch (InquiryInvariantViolation) {
                $this->assertSame(0, Inquiry::query()->count());
            }
        }
    }

    public function test_invariants_reject_incoherent_combinations(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $incoming = $this->incoming();

        $cases = [
            'external without source' => fn () => Inquiry::factory()->state(['origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE])->create(),
            'external with creator' => fn () => Inquiry::factory()->externalIntake($incoming)->state(['created_by' => $manager->id])->create(),
            'manual without creator' => fn () => Inquiry::factory()->managerCreated($manager)->state(['created_by' => null])->create(),
            'manual with source' => fn () => Inquiry::factory()->managerCreated($manager)->state(['incoming_inquiry_id' => $incoming->id])->create(),
            'new with assignee' => fn () => Inquiry::factory()->externalIntake($incoming)->state(['assigned_to' => $manager->id])->create(),
            'in progress without assignee' => fn () => Inquiry::factory()->externalIntake($incoming)->state(['status' => 'in_progress'])->create(),
            'converted without booking' => fn () => Inquiry::factory()->externalIntake($incoming)->state(['status' => 'converted', 'converted_at' => now()])->create(),
            'closed without closed_at' => fn () => Inquiry::factory()->externalIntake($incoming)->state(['status' => 'closed'])->create(),
            'manual created as closed' => fn () => Inquiry::factory()->managerCreated($manager)->state(['status' => 'closed', 'closed_at' => now()])->create(),
        ];

        foreach ($cases as $name => $attempt) {
            try {
                $attempt();
                $this->fail("Accepted: {$name}");
            } catch (InquiryInvariantViolation) {
                $this->assertSame(0, Inquiry::query()->count(), $name);
            }
        }
    }

    public function test_booking_link_requires_converted_status(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $booking = $this->booking($tourist, $manager);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        $inquiry->forceFill(['booking_id' => $booking->id]);

        $this->expectException(InquiryInvariantViolation::class);

        $inquiry->save();
    }

    public function test_client_association_is_nullable_and_never_inferred(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $tourist->forceFill(['email' => 'synthetic.client@example.test'])->save();
        $incoming = $this->incoming(['client_email' => 'synthetic.client@example.test']);

        $inquiry = Inquiry::factory()->externalIntake($incoming)->assignedTo($manager)->create();

        $this->assertNull($inquiry->client_user_id);
        $this->assertNull($inquiry->clientUser);
    }

    // ------------------------------------------------------------ serialization

    public function test_serialization_never_exposes_related_users_or_notes(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create([
            'client_user_id' => $tourist->id,
            'internal_notes' => 'Секретная служебная заметка',
        ]);

        $inquiry->load(['creator', 'assignee', 'clientUser', 'incomingInquiry', 'booking']);

        $json = json_encode($inquiry->toArray(), JSON_UNESCAPED_UNICODE);

        $this->assertStringNotContainsString($manager->email, $json);
        $this->assertStringNotContainsString($tourist->email, $json);
        $this->assertStringNotContainsString('Секретная', $json);
        foreach (['creator', 'assignee', 'client_user', 'clientUser', 'password'] as $key) {
            $this->assertArrayNotHasKey($key, $inquiry->toArray());
        }
        $this->assertSame($tourist->id, $inquiry->toArray()['client_user_id']);
    }

    public function test_mass_assignment_cannot_set_ownership(): void
    {
        $this->expectException(\Illuminate\Database\Eloquent\MassAssignmentException::class);

        (new Inquiry())->fill(['assigned_to' => 1, 'origin' => Inquiry::ORIGIN_ADMIN_CREATED]);
    }

    // ------------------------------------------------------------------- policy

    public function test_policy_matrix(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $owner = $this->userWithRoles(['manager']);
        $foreign = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $noRole = User::factory()->create();

        $assigned = Inquiry::factory()->managerCreated($owner)->create();
        $unassigned = Inquiry::factory()->externalIntake($this->incoming())->create();

        foreach (['view', 'updateDraft'] as $ability) {
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $assigned), "admin {$ability} assigned");
            $this->assertTrue(Gate::forUser($admin)->allows($ability, $unassigned), "admin {$ability} unassigned");
            $this->assertTrue(Gate::forUser($owner)->allows($ability, $assigned), "owner {$ability}");
            $this->assertFalse(Gate::forUser($foreign)->allows($ability, $assigned), "foreign {$ability}");
            $this->assertFalse(Gate::forUser($owner)->allows($ability, $unassigned), "owner {$ability} unassigned");
            $this->assertFalse(Gate::forUser($foreign)->allows($ability, $unassigned), "manager {$ability} unassigned");
            $this->assertFalse(Gate::forUser($tourist)->allows($ability, $assigned), "tourist {$ability}");
            $this->assertFalse(Gate::forUser($noRole)->allows($ability, $assigned), "no role {$ability}");
        }
    }

    public function test_visible_to_scope_matches_policy_and_hides_foreign_and_unassigned(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $owner = $this->userWithRoles(['manager']);
        $foreign = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);

        $mine = Inquiry::factory()->managerCreated($owner)->create();
        $theirs = Inquiry::factory()->managerCreated($foreign)->create();
        $unassigned = Inquiry::factory()->externalIntake($this->incoming())->create();

        $this->assertEqualsCanonicalizing(
            [$mine->id, $theirs->id, $unassigned->id],
            Inquiry::query()->visibleTo($admin)->pluck('id')->all()
        );
        $this->assertSame([$mine->id], Inquiry::query()->visibleTo($owner)->pluck('id')->all());
        $this->assertSame(0, Inquiry::query()->visibleTo($tourist)->count());
    }

    public function test_reassigned_away_manager_loses_visibility(): void
    {
        $former = $this->userWithRoles(['manager']);
        $current = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($former)->create();

        $inquiry->forceFill(['assigned_to' => $current->id])->save();

        $this->assertFalse(Gate::forUser($former)->allows('view', $inquiry->fresh()));
        $this->assertTrue(Gate::forUser($current)->allows('view', $inquiry->fresh()));
        $this->assertSame(0, Inquiry::query()->visibleTo($former)->count());
    }

    // ----------------------------------------------- legacy / no side effects

    public function test_creating_an_inquiry_does_not_touch_legacy_records_or_create_side_effects(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $incoming = $this->incoming();
        $before = $incoming->getAttributes();

        Inquiry::factory()->externalIntake($incoming)->assignedTo($manager)->create();

        $this->assertSame($before, $incoming->fresh()->getAttributes());
        $this->assertSame(IncomingInquiry::WORKFLOW_NEW, $incoming->fresh()->workflow_state);
        $this->assertNull($incoming->fresh()->assigned_to);
        $this->assertSame(0, Booking::withTrashed()->count());
        $this->assertSame(0, AuditEvent::query()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_incoming_inquiry_creation_does_not_create_a_business_inquiry(): void
    {
        $this->incoming(['external_id' => '777']);

        $this->assertSame(0, Inquiry::query()->count());
    }

    public function test_draft_never_creates_a_booking(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create([
            'destination_country' => 'Турция',
            'adults' => 2,
        ]);

        $this->assertSame(0, Booking::withTrashed()->count());
        $this->assertNull($inquiry->booking_id);
        $this->assertNull($inquiry->booking);
    }
}
