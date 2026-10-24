<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Inquiries\InquiryDraftData;
use App\Services\Inquiries\InquiryDraftException;
use App\Services\Inquiries\InquiryDraftStore;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Illuminate\Validation\ValidationException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AOA-03-I1 — надёжное сохранение черновика обращения (R-D): права, границы данных,
 * оптимистичная версия, отсутствие побочных эффектов. Синтетические данные, SQLite :memory:.
 */
class InquiryDraftStoreTest extends TestCase
{
    use RefreshDatabase;

    private InquiryDraftStore $store;

    protected function setUp(): void
    {
        parent::setUp();

        Mail::fake();
        Notification::fake();
        $this->store = app(InquiryDraftStore::class);
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

    private function incoming(): IncomingInquiry
    {
        $incoming = new IncomingInquiry();
        $incoming->forceFill([
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => '000987',
            'state' => IncomingInquiry::STATE_IMPORTED,
            'webhook_count' => 1,
        ])->save();

        return $incoming;
    }

    private function validPatch(array $overrides = []): array
    {
        return array_merge([
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'departure_city' => 'Москва',
            'date_from' => '2027-06-01',
            'date_to' => '2027-06-15',
            'nights_min' => 7,
            'nights_max' => 10,
            'adults' => 2,
            'children' => 2,
            'children_ages' => [4, 9],
            'preferences' => 'Первая линия, все включено',
            'internal_notes' => 'Клиент ещё сверяет даты',
        ], $overrides);
    }

    public function test_owner_manager_saves_a_draft_before_any_client_is_known(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        $saved = $this->store->save($inquiry, $manager, $this->validPatch(), 0);

        $fresh = $saved->fresh();
        $this->assertSame('Турция', $fresh->destination_country);
        $this->assertSame('2027-06-01', $fresh->date_from->toDateString());
        $this->assertSame([4, 9], $fresh->children_ages);
        $this->assertSame(1, $fresh->draft_revision);
        $this->assertSame($manager->id, $fresh->draft_updated_by);
        $this->assertNotNull($fresh->draft_updated_at);
        // Клиент не определяется автоматически, заявка не создаётся, владелец не меняется.
        $this->assertNull($fresh->client_user_id);
        $this->assertNull($fresh->booking_id);
        $this->assertSame($manager->id, $fresh->assigned_to);
        $this->assertSame(0, Booking::withTrashed()->count());
        $this->assertSame(0, AuditEvent::query()->count());
        Mail::assertNothingSent();
        Notification::assertNothingSent();
    }

    public function test_admin_can_save_a_draft_on_any_open_inquiry_including_unassigned(): void
    {
        $admin = $this->userWithRoles(['admin']);
        $unassigned = Inquiry::factory()->externalIntake($this->incoming())->create();

        $this->store->save($unassigned, $admin, ['adults' => 3], 0);

        $this->assertSame(3, $unassigned->fresh()->adults);
        $this->assertNull($unassigned->fresh()->assigned_to);
    }

    public function test_foreign_manager_unassigned_manager_tourist_and_no_role_are_denied_without_changes(): void
    {
        $owner = $this->userWithRoles(['manager']);
        $foreign = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $noRole = User::factory()->create();
        $mine = Inquiry::factory()->managerCreated($owner)->create();
        $unassigned = Inquiry::factory()->externalIntake($this->incoming())->create();

        foreach ([[$foreign, $mine], [$tourist, $mine], [$noRole, $mine], [$owner, $unassigned], [$foreign, $unassigned]] as [$actor, $target]) {
            try {
                $this->store->save($target, $actor, ['adults' => 5], 0);
                $this->fail('Draft saved by an unauthorized actor.');
            } catch (AuthorizationException) {
                $this->assertNull($target->fresh()->adults);
                $this->assertSame(0, $target->fresh()->draft_revision);
            }
        }
    }

    public function test_former_manager_cannot_save_after_reassignment(): void
    {
        $former = $this->userWithRoles(['manager']);
        $current = $this->userWithRoles(['manager']);
        $stale = Inquiry::factory()->managerCreated($former)->create();
        $copy = Inquiry::find($stale->id);

        $stale->forceFill(['assigned_to' => $current->id])->save();

        $this->expectException(AuthorizationException::class);

        // Устаревшая копия модели: решение принимает перечитанная под блокировкой строка.
        $this->store->save($copy, $former, ['adults' => 2], 0);
    }

    public function test_ownership_and_state_fields_are_not_writable_through_the_draft(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $other = $this->userWithRoles(['manager']);
        $tourist = $this->userWithRoles(['tourist']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        foreach (['assigned_to' => $other->id, 'created_by' => $other->id, 'status' => 'closed', 'origin' => 'admin_created',
            'booking_id' => 1, 'client_user_id' => $tourist->id, 'incoming_inquiry_id' => 1, 'draft_revision' => 99, 'id' => 5] as $field => $value) {
            try {
                $this->store->save($inquiry, $manager, ['adults' => 2, $field => $value], 0);
                $this->fail("Accepted {$field}");
            } catch (ValidationException $e) {
                $this->assertArrayHasKey('draft', $e->errors(), $field);
            }
        }

        $fresh = $inquiry->fresh();
        $this->assertSame($manager->id, $fresh->assigned_to);
        $this->assertSame($manager->id, $fresh->created_by);
        $this->assertSame(Inquiry::STATUS_IN_PROGRESS, $fresh->status);
        $this->assertNull($fresh->adults);
        $this->assertSame(0, $fresh->draft_revision);
    }

    public function test_closed_inquiry_draft_cannot_be_changed(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();
        $inquiry->forceFill(['status' => 'closed', 'closed_at' => now()])->save();

        $this->expectException(InquiryDraftException::class);

        $this->store->save($inquiry, $manager, ['adults' => 2], 0);
    }

    public function test_stale_revision_is_rejected_and_does_not_overwrite(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $admin = $this->userWithRoles(['admin']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        $this->store->save($inquiry, $manager, ['destination_country' => 'Турция'], 0);

        try {
            $this->store->save($inquiry, $admin, ['destination_country' => 'Египет'], 0);
            $this->fail('Stale revision accepted.');
        } catch (InquiryDraftException $e) {
            $this->assertSame('revision', $e->field);
        }

        $this->assertSame('Турция', $inquiry->fresh()->destination_country);

        $this->store->save($inquiry, $admin, ['destination_country' => 'Египет'], 1);
        $this->assertSame('Египет', $inquiry->fresh()->destination_country);
        $this->assertSame(2, $inquiry->fresh()->draft_revision);
        $this->assertSame($admin->id, $inquiry->fresh()->draft_updated_by);
    }

    public function test_patch_semantics_and_noop_does_not_bump_revision(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();
        $this->store->save($inquiry, $manager, $this->validPatch(), 0);

        // Частичный патч меняет только переданное; пустая строка и null очищают поле.
        $this->store->save($inquiry, $manager, ['adults' => 3, 'preferences' => '   ', 'internal_notes' => null], 1);

        $fresh = $inquiry->fresh();
        $this->assertSame(3, $fresh->adults);
        $this->assertNull($fresh->preferences);
        $this->assertNull($fresh->internal_notes);
        $this->assertSame('Анталья', $fresh->destination_city);
        $this->assertSame(2, $fresh->draft_revision);

        // Пустой патч — не изменение: версия прежняя.
        $this->store->save($inquiry, $manager, [], 2);
        $this->assertSame(2, $inquiry->fresh()->draft_revision);
    }

    /** @return array<string, array{0: array<string, mixed>, 1: string}> */
    public static function invalidPatches(): array
    {
        return [
            'adults zero' => [['adults' => 0], 'adults'],
            'adults too many' => [['adults' => 11], 'adults'],
            'adults bool' => [['adults' => true], 'adults'],
            'adults float' => [['adults' => 2.5], 'adults'],
            'adults array' => [['adults' => [2]], 'adults'],
            'adults text' => [['adults' => 'много'], 'adults'],
            'children negative' => [['children' => -1, 'children_ages' => []], 'children'],
            'nights too long' => [['nights_min' => 31], 'nights_min'],
            'nights range reversed' => [['nights_min' => 10, 'nights_max' => 7], 'nights_max'],
            'date format' => [['date_from' => '01.06.2027'], 'date_from'],
            'date impossible' => [['date_from' => '2027-02-31'], 'date_from'],
            'dates reversed' => [['date_from' => '2027-06-10', 'date_to' => '2027-06-01'], 'date_to'],
            'ages count mismatch' => [['children' => 2, 'children_ages' => [3]], 'children_ages'],
            'ages without children' => [['children' => 0, 'children_ages' => [3]], 'children_ages'],
            'age too high' => [['children' => 1, 'children_ages' => [18]], 'children_ages.0'],
            'age not a list' => [['children' => 1, 'children_ages' => ['a' => 3]], 'children_ages'],
            'country too long' => [['destination_country' => str_repeat('я', 129)], 'destination_country'],
            'country array' => [['destination_country' => ['Турция']], 'destination_country'],
            'preferences too long' => [['preferences' => str_repeat('a', InquiryDraftData::PREFERENCES_MAX_LENGTH + 1)], 'preferences'],
            'notes too long' => [['internal_notes' => str_repeat('a', InquiryDraftData::NOTES_MAX_LENGTH + 1)], 'internal_notes'],
            'notes NUL byte' => [['internal_notes' => "ab\0cd"], 'internal_notes'],
        ];
    }

    #[DataProvider('invalidPatches')]
    public function test_invalid_data_is_rejected_without_persisting(array $patch, string $errorKey): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        try {
            $this->store->save($inquiry, $manager, $patch, 0);
            $this->fail('Invalid draft accepted.');
        } catch (ValidationException $e) {
            $this->assertArrayHasKey($errorKey, $e->errors());
        }

        $fresh = $inquiry->fresh();
        $this->assertSame(0, $fresh->draft_revision);
        $this->assertSame([], array_filter($fresh->draftSnapshot(), fn ($v, $k) => $k !== 'revision' && $v !== null, ARRAY_FILTER_USE_BOTH));
    }

    public function test_boundary_values_are_accepted(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();

        $this->store->save($inquiry, $manager, [
            'adults' => 10, 'children' => 10, 'children_ages' => [0, 1, 2, 3, 4, 5, 6, 7, 8, 17],
            'nights_min' => 1, 'nights_max' => 30,
            'preferences' => str_repeat('я', InquiryDraftData::PREFERENCES_MAX_LENGTH),
            'internal_notes' => str_repeat('я', InquiryDraftData::NOTES_MAX_LENGTH),
        ], 0);

        $this->assertSame(10, $inquiry->fresh()->children);
        $this->assertCount(10, $inquiry->fresh()->children_ages);
    }

    public function test_draft_snapshot_is_an_explicit_projection_without_relations(): void
    {
        $manager = $this->userWithRoles(['manager']);
        $inquiry = Inquiry::factory()->managerCreated($manager)->create();
        $this->store->save($inquiry, $manager, $this->validPatch(), 0);

        $snapshot = $inquiry->fresh()->load('assignee', 'creator')->draftSnapshot();

        $this->assertEqualsCanonicalizing(
            array_merge(Inquiry::DRAFT_FIELDS, ['revision']),
            array_keys($snapshot)
        );
        $this->assertStringNotContainsString($manager->email, json_encode($snapshot));
    }
}
