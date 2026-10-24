<?php

namespace App\Services\Inquiries\Backfill;

/**
 * Итог классификации одного IncomingInquiry (AOA-03-I2). Содержит только числовые
 * идентификаторы, коды причин и допустимые к копированию поля — никаких контактов.
 */
final class InquiryBackfillDecision
{
    /** Inquiry существует и полностью совпадает с ожидаемым отображением. */
    public const MATCHED = 'MATCHED';
    /** Источник нельзя перенести: нарушает инвариант Inquiry или ссылка не разрешается. */
    public const BLOCKED = 'BLOCKED';
    /** Расхождение с уже существующими данными (Inquiry, Booking-связь). Ничего не перезаписывается. */
    public const CONFLICT = 'CONFLICT';
    /** Технически не подлежит переносу (не загружено / неподдерживаемый тип / неизвестное состояние). */
    public const UNSUPPORTED = 'UNSUPPORTED';
    /** Можно перенести, Inquiry ещё нет. */
    public const NOT_PROCESSED = 'NOT_PROCESSED';

    /**
     * @param  list<string>  $reasons
     * @param  array<string, mixed>|null  $expected  поля для создания (только у NOT_PROCESSED/MATCHED/CONFLICT при корректном источнике)
     */
    public function __construct(
        public readonly int $sourceId,
        public readonly string $verdict,
        public readonly array $reasons,
        public readonly bool $eligible,
        public readonly ?string $workflowState,
        public readonly string $technicalState,
        public readonly ?array $expected = null,
        public readonly ?int $inquiryId = null,
    ) {
    }
}
