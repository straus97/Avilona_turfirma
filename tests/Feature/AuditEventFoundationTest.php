<?php

namespace Tests\Feature;

use App\Models\AuditEvent;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Database\Eloquent\MassAssignmentException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use InvalidArgumentException;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

/**
 * AOA-01-I1 — append-only business audit foundation.
 *
 * The recorder is not wired into any existing business process in this slice;
 * these tests exercise it directly with synthetic users and subjects.
 */
class AuditEventFoundationTest extends TestCase
{
    use RefreshDatabase;

    private function recorder(): AuditRecorder
    {
        return $this->app->make(AuditRecorder::class);
    }

    public function test_table_starts_empty_and_has_expected_columns(): void
    {
        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertTrue(Schema::hasColumns('audit_events', [
            'id', 'uuid', 'event_type', 'actor_id', 'subject_type', 'subject_id', 'metadata', 'occurred_at',
        ]));
    }

    public function test_records_a_valid_event(): void
    {
        Carbon::setTestNow('2026-10-20 12:30:00');
        $actor = User::factory()->create();

        $event = $this->recorder()->record(
            AuditEventType::InquiryReassigned,
            $actor,
            'incoming_inquiry',
            42,
            ['previous_assignee_id' => 7, 'new_assignee_id' => 9, 'reason' => 'Отпуск сотрудника', 'urgent' => false, 'note' => null],
        );

        $fresh = AuditEvent::query()->findOrFail($event->id);

        $this->assertSame('inquiry.reassigned', $fresh->event_type);
        $this->assertSame($actor->id, (int) $fresh->actor_id);
        $this->assertSame('incoming_inquiry', $fresh->subject_type);
        $this->assertSame(42, (int) $fresh->subject_id);
        $this->assertSame('2026-10-20 12:30:00', $fresh->occurred_at->format('Y-m-d H:i:s'));
        $this->assertSame(
            ['previous_assignee_id' => 7, 'new_assignee_id' => 9, 'reason' => 'Отпуск сотрудника', 'urgent' => false, 'note' => null],
            $fresh->metadata
        );
        $this->assertMatchesRegularExpression('/^[0-9a-f]{8}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{4}-[0-9a-f]{12}$/', $fresh->uuid);
        $this->assertSame('Обращение переназначено', $fresh->typeLabel());
    }

    public function test_accepts_actor_id_and_system_actor_and_empty_metadata(): void
    {
        $byId = $this->recorder()->record(AuditEventType::AdminAction, 5, 'user', 5);
        $system = $this->recorder()->record(AuditEventType::AdminAction, null, 'system', null);

        $this->assertSame(5, (int) $byId->fresh()->actor_id);
        $this->assertNull($byId->fresh()->metadata);
        $this->assertNull($system->fresh()->actor_id);
        $this->assertNull($system->fresh()->subject_id);
        $this->assertNotSame($byId->uuid, $system->uuid);
    }

    public function test_audit_row_survives_deletion_of_the_actor(): void
    {
        $actor = User::factory()->create();
        $event = $this->recorder()->record(AuditEventType::AdminAction, $actor, 'user', $actor->id);

        DB::table('users')->where('id', $actor->id)->delete();

        $this->assertSame($actor->id, (int) AuditEvent::query()->findOrFail($event->id)->actor_id);
    }

    #[DataProvider('unsafeMetadataProvider')]
    public function test_rejects_unsafe_metadata(array $metadata): void
    {
        $this->expectException(InvalidArgumentException::class);

        try {
            $this->recorder()->record(AuditEventType::AdminAction, 1, 'user', 1, $metadata);
        } finally {
            $this->assertSame(0, AuditEvent::query()->count());
        }
    }

    public static function unsafeMetadataProvider(): array
    {
        return [
            'password key' => [['password' => 'x']],
            'token key' => [['api_token' => 'x']],
            'cookie key' => [['cookie' => 'x']],
            'passport key' => [['passport_number' => '1234']],
            'request body key' => [['request_body' => 'x']],
            'provider payload key' => [['vendor_payload' => 'x']],
            'message text key' => [['message' => 'full correspondence']],
            'nested array' => [['reason' => ['a' => 1]]],
            'object value' => [['reason' => new \stdClass()]],
            'bad key format' => [['Bad Key' => 1]],
            'numeric key' => [[0 => 'x']],
            'email value' => [['contact' => 'person@example.test']],
            'windows path value' => [['file' => 'C:\\wamp\\www\\app\\x.php']],
            'posix path value' => [['file' => '/var/www/app/x.php']],
            'token-like value' => [['ref' => 'abcdefghijklmnopqrstuvwxyz0123456789ABCD']],
            'bearer value' => [['ref' => 'Bearer abc.def']],
            'too long value' => [['reason' => str_repeat('я', 501)]],
            'too many keys' => [array_combine(array_map(fn ($i) => 'k' . $i, range(1, 21)), range(1, 21))],
        ];
    }

    public function test_ordinary_business_keys_are_not_mistaken_for_secrets(): void
    {
        $event = $this->recorder()->record(AuditEventType::AdminAction, 1, 'booking', 1, [
            'passenger_count' => 3,
            'previous_total' => '100000.00',
            'new_total' => 95000.5,
        ]);

        $this->assertSame(3, $event->fresh()->metadata['passenger_count']);
    }

    public function test_rejects_invalid_subject_and_actor(): void
    {
        foreach ([
            fn () => $this->recorder()->record(AuditEventType::AdminAction, 1, 'App\\Models\\User', 1),
            fn () => $this->recorder()->record(AuditEventType::AdminAction, 1, 'user', 0),
            fn () => $this->recorder()->record(AuditEventType::AdminAction, 0, 'user', 1),
        ] as $call) {
            try {
                $call();
                $this->fail('Expected InvalidArgumentException');
            } catch (InvalidArgumentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_events_cannot_be_updated_or_deleted_through_the_model(): void
    {
        $event = $this->recorder()->record(AuditEventType::AdminAction, 1, 'user', 1, ['a' => 1]);

        $loaded = AuditEvent::query()->findOrFail($event->id);
        $loaded->event_type = 'tampered';

        foreach ([
            fn () => $loaded->save(),
            fn () => $loaded->delete(),
            fn () => $loaded->update(['event_type' => 'x']),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Expected LogicException');
            } catch (LogicException|MassAssignmentException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame('admin.action', AuditEvent::query()->findOrFail($event->id)->event_type);
    }

    public function test_events_cannot_be_mass_updated_or_deleted_through_the_query_builder(): void
    {
        $this->recorder()->record(AuditEventType::AdminAction, 1, 'user', 1);

        foreach ([
            fn () => AuditEvent::query()->update(['event_type' => 'x']),
            fn () => AuditEvent::query()->delete(),
            fn () => AuditEvent::query()->where('id', '>', 0)->delete(),
            fn () => AuditEvent::query()->truncate(),
            fn () => AuditEvent::truncate(),
            fn () => AuditEvent::query()->increment('subject_id'),
        ] as $mutation) {
            try {
                $mutation();
                $this->fail('Expected LogicException');
            } catch (LogicException) {
                $this->addToAssertionCount(1);
            }
        }

        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame('admin.action', AuditEvent::query()->first()->event_type);
    }

    public function test_mass_assignment_is_closed(): void
    {
        $this->expectException(MassAssignmentException::class);

        AuditEvent::create(['event_type' => 'x', 'subject_type' => 'user', 'occurred_at' => now(), 'uuid' => 'x']);
    }

    public function test_event_is_rolled_back_with_the_business_transaction(): void
    {
        $actor = User::factory()->create();

        try {
            DB::transaction(function () use ($actor): void {
                $actor->forceFill(['name' => 'Changed In Transaction'])->save();
                $this->recorder()->record(AuditEventType::AdminAction, $actor, 'user', $actor->id, ['step' => 'one']);

                throw new \RuntimeException('business failure after audit write');
            });
            $this->fail('Expected RuntimeException');
        } catch (\RuntimeException) {
            $this->addToAssertionCount(1);
        }

        $this->assertSame(0, AuditEvent::query()->count());
        $this->assertNotSame('Changed In Transaction', $actor->fresh()->name);
    }

    public function test_event_is_committed_with_the_business_transaction(): void
    {
        $actor = User::factory()->create();

        DB::transaction(function () use ($actor): void {
            $actor->forceFill(['name' => 'Changed In Transaction'])->save();
            $this->recorder()->record(AuditEventType::AdminAction, $actor, 'user', $actor->id);
        });

        $this->assertSame(1, AuditEvent::query()->count());
        $this->assertSame('Changed In Transaction', $actor->fresh()->name);
    }

    public function test_failed_audit_write_rolls_back_the_business_change(): void
    {
        $actor = User::factory()->create();

        try {
            DB::transaction(function () use ($actor): void {
                $actor->forceFill(['name' => 'Changed In Transaction'])->save();
                $this->recorder()->record(AuditEventType::AdminAction, $actor, 'user', $actor->id, ['password' => 'nope']);
            });
            $this->fail('Expected InvalidArgumentException');
        } catch (InvalidArgumentException) {
            $this->addToAssertionCount(1);
        }

        $this->assertNotSame('Changed In Transaction', $actor->fresh()->name);
        $this->assertSame(0, AuditEvent::query()->count());
    }

    public function test_unknown_event_type_from_storage_still_has_a_label(): void
    {
        $this->assertSame('legacy.type', AuditEventType::labelFor('legacy.type'));
        $this->assertSame('Действие администратора', AuditEventType::labelFor('admin.action'));
    }
}
