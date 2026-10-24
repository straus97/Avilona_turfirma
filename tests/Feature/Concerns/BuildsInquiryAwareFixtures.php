<?php

namespace Tests\Feature\Concerns;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Support\Facades\DB;

/**
 * Синтетические пары IncomingInquiry ↔ Inquiry для тестов AOA-03-I3.
 * Строки с «нарушенным» состоянием пишутся сырым запросом: модель Inquiry такие
 * состояния не допускает, а в реальной базе они возможны (ручная правка, удаление
 * пользователя, незавершённый перенос).
 */
trait BuildsInquiryAwareFixtures
{
    use BuildsAoaI2bFixtures;

    /**
     * Внешнее обращение (legacy) + привязанный к нему Inquiry. Назначения и клиента
     * можно задать независимо, чтобы воспроизвести согласованные и конфликтующие пары.
     *
     * @return array{0: IncomingInquiry, 1: Inquiry}
     */
    protected function linkedPair(?User $legacyAssignee, ?User $inquiryAssignee, ?User $legacyClient = null, ?User $inquiryClient = null, array $legacy = [], array $inquiry = []): array
    {
        $source = $this->inquiryFor($legacyAssignee, $legacyClient, $legacy);

        $factory = Inquiry::factory()->externalIntake($source);
        if ($inquiryAssignee !== null) {
            $factory = $factory->assignedTo($inquiryAssignee);
        }

        $row = $factory->create();

        $extra = array_merge(['client_user_id' => $inquiryClient?->id], $inquiry);
        DB::table('inquiries')->where('id', $row->id)->update($extra);

        return [$source->refresh(), $row->refresh()];
    }

    /** Самостоятельный Inquiry (без IncomingInquiry), назначенный сотруднику. */
    protected function independentInquiry(User $creator, ?User $assignee = null, ?User $client = null, array $overrides = []): Inquiry
    {
        $isAdmin = $creator->hasRole(User::ROLE_ADMIN);
        $factory = $isAdmin
            ? Inquiry::factory()->adminCreated($creator, $assignee)
            : Inquiry::factory()->managerCreated($creator);

        $row = $factory->create();

        $update = array_merge(['client_user_id' => $client?->id], $overrides);
        DB::table('inquiries')->where('id', $row->id)->update($update);

        return $row->refresh();
    }

    /** Смена ответственного у legacy-обращения так же, как это делает E5 (без аудита — только состояние). */
    protected function legacyReassign(IncomingInquiry $source, ?User $to): void
    {
        DB::table('incoming_inquiries')->where('id', $source->id)->update([
            'assigned_to' => $to?->id,
            'workflow_state' => $to === null ? IncomingInquiry::WORKFLOW_NEW : IncomingInquiry::WORKFLOW_IN_PROGRESS,
        ]);
    }
}
