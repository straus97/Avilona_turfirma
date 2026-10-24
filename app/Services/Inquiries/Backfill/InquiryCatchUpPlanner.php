<?php

namespace App\Services\Inquiries\Backfill;

use App\Models\Inquiry;

/**
 * Чистая (без БД и записи) классификация для будущего catch-up (AOA-03-I4).
 *
 * Берёт готовое решение классификатора переноса (InquiryBackfillDecision — единственное место,
 * где решается, во что превращается IncomingInquiry) и текущий статус связанного Inquiry и
 * называет категорию «что произошло с источником после переноса» и ПРЕДЛАГАЕМОЕ будущее
 * действие. Ничего не выполняет, не сканирует данные и не предполагает отметки «до какого
 * момента всё учтено»: такой отметки нет, и честный способ её получить описан в документе I4
 * (журнал изменений E5). Поэтому любое действие, кроме NONE, здесь — «кандидат», а не разрешение.
 *
 * Ни одна категория не выбирает победителя в споре об ответственном и не перезаписывает
 * Inquiry: расхождение по ответственному, обратное опережение (Inquiry впереди источника),
 * дубли заявок и неразрешённые ссылки всегда уходят на решение владельца/Admin.
 */
final class InquiryCatchUpPlanner
{
    public const NEW_ELIGIBLE_SOURCE = 'NEW_ELIGIBLE_SOURCE';
    public const UNCHANGED_LINKED = 'UNCHANGED_LINKED';
    public const POST_BACKFILL_CONVERSION = 'POST_BACKFILL_CONVERSION';
    public const CLOSURE = 'CLOSURE';
    public const CLIENT_LINK_CHANGE = 'CLIENT_LINK_CHANGE';
    public const REASSIGNMENT = 'REASSIGNMENT';
    public const INQUIRY_AHEAD = 'INQUIRY_AHEAD';
    public const UNRESOLVED_USER_REFERENCE = 'UNRESOLVED_USER_REFERENCE';
    public const UNRESOLVED_CLIENT_REFERENCE = 'UNRESOLVED_CLIENT_REFERENCE';
    public const DUPLICATE_BOOKING = 'DUPLICATE_BOOKING';
    public const UNSUPPORTED_TECHNICAL_SOURCE = 'UNSUPPORTED_TECHNICAL_SOURCE';
    public const REQUIRES_OWNER_DECISION = 'REQUIRES_OWNER_DECISION';

    /** Ничего не делать. */
    public const ACTION_NONE = 'NONE';
    /** Кандидат на создание Inquiry из источника (только когда перечень новых источников полон). */
    public const ACTION_CREATE_FROM_SOURCE = 'CREATE_FROM_SOURCE';
    /** Кандидат на перенос полей источника в Inquiry (только когда доказано, что Inquiry сам их не менял). */
    public const ACTION_MIRROR_FROM_SOURCE = 'MIRROR_FROM_SOURCE';
    /** Ничего не менять до решения владельца/Admin. */
    public const ACTION_HOLD_FOR_DECISION = 'HOLD_FOR_DECISION';

    private const STAGE_FIELDS = ['status', 'booking_id', 'converted_at', 'converted_by', 'closed_at', 'closed_by', 'close_reason'];
    private const CLIENT_FIELDS = ['client_user_id', 'client_linked_by', 'client_linked_at'];

    /**
     * @param  string|null  $inquiryStatus  текущий статус связанного Inquiry; null — Inquiry нет
     * @return array{category: string, action: string, reasons: list<string>}
     */
    public function plan(InquiryBackfillDecision $decision, ?string $inquiryStatus): array
    {
        return match ($decision->verdict) {
            InquiryBackfillDecision::NOT_PROCESSED => $this->result(self::NEW_ELIGIBLE_SOURCE, self::ACTION_CREATE_FROM_SOURCE, []),
            InquiryBackfillDecision::MATCHED => $this->result(self::UNCHANGED_LINKED, self::ACTION_NONE, []),
            InquiryBackfillDecision::UNSUPPORTED => $this->result(self::UNSUPPORTED_TECHNICAL_SOURCE, self::ACTION_NONE, $decision->reasons),
            InquiryBackfillDecision::BLOCKED => $this->result($this->blockedCategory($decision->reasons), self::ACTION_HOLD_FOR_DECISION, $decision->reasons),
            default => $this->conflict($decision, $inquiryStatus),
        };
    }

    /** @return array{category: string, action: string, reasons: list<string>} */
    private function conflict(InquiryBackfillDecision $decision, ?string $inquiryStatus): array
    {
        $reasons = $decision->reasons;
        $diverged = array_map(
            static fn (string $reason): string => substr($reason, strlen('diverges:')),
            array_values(array_filter($reasons, static fn (string $reason): bool => str_starts_with($reason, 'diverges:')))
        );

        // Ссылки источника не разрешаются или Inquiry существует у неподдерживаемого источника.
        $blocked = $this->blockedCategory($reasons);
        if ($blocked !== self::REQUIRES_OWNER_DECISION || in_array('inquiry_exists_for_unmappable_source', $reasons, true)) {
            return $this->result($blocked, self::ACTION_HOLD_FOR_DECISION, $reasons);
        }

        if (array_intersect($reasons, ['booking_owned_by_other_inquiry', 'duplicate_booking_reference_in_sources'])) {
            return $this->result(self::DUPLICATE_BOOKING, self::ACTION_HOLD_FOR_DECISION, $reasons);
        }

        if (in_array('duplicate_inquiries_for_source', $reasons, true)) {
            return $this->result(self::REQUIRES_OWNER_DECISION, self::ACTION_HOLD_FOR_DECISION, $reasons);
        }

        // Победитель в споре об ответственном не выбирается: ни по времени, ни по стадии.
        if (in_array('assigned_to', $diverged, true) || in_array('assigned_at', $diverged, true)) {
            return $this->result(self::REASSIGNMENT, self::ACTION_HOLD_FOR_DECISION, $reasons);
        }

        $sourceStage = $this->stage($decision->workflowState);
        $inquiryStage = $this->stage($inquiryStatus);

        if ($inquiryStage > $sourceStage) {
            return $this->result(self::INQUIRY_AHEAD, self::ACTION_HOLD_FOR_DECISION, $reasons);
        }

        if (array_intersect($diverged, self::STAGE_FIELDS)) {
            if ($sourceStage === $inquiryStage) {
                // Одна стадия, но разные итоги (другая заявка, другая причина закрытия): догадываться нельзя.
                return $this->result(self::REQUIRES_OWNER_DECISION, self::ACTION_HOLD_FOR_DECISION, $reasons);
            }

            $category = $decision->workflowState === Inquiry::STATUS_CONVERTED ? self::POST_BACKFILL_CONVERSION : self::CLOSURE;

            return $this->result($category, self::ACTION_MIRROR_FROM_SOURCE, $reasons);
        }

        if (array_intersect($diverged, self::CLIENT_FIELDS)) {
            return $this->result(self::CLIENT_LINK_CHANGE, self::ACTION_MIRROR_FROM_SOURCE, $reasons);
        }

        // Поле, которое планировщик не знает: безопаснее остановиться, чем угадывать.
        return $this->result(self::REQUIRES_OWNER_DECISION, self::ACTION_HOLD_FOR_DECISION, $reasons);
    }

    /** @param list<string> $reasons */
    private function blockedCategory(array $reasons): string
    {
        if (array_intersect($reasons, ['unresolved_client', 'client_not_tourist'])) {
            return self::UNRESOLVED_CLIENT_REFERENCE;
        }

        if (array_intersect($reasons, ['unresolved_assignee', 'unresolved_actor_reference'])) {
            return self::UNRESOLVED_USER_REFERENCE;
        }

        return self::REQUIRES_OWNER_DECISION;
    }

    private function stage(?string $status): int
    {
        return match ($status) {
            Inquiry::STATUS_NEW => 0,
            Inquiry::STATUS_IN_PROGRESS => 1,
            default => 2,
        };
    }

    /**
     * @param  list<string>  $reasons
     * @return array{category: string, action: string, reasons: list<string>}
     */
    private function result(string $category, string $action, array $reasons): array
    {
        return ['category' => $category, 'action' => $action, 'reasons' => array_values($reasons)];
    }
}
