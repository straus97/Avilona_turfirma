<?php

namespace App\Support\Audit;

/**
 * Реестр типов бизнес-событий (AOA-01-I1).
 *
 * Типы объявлены заранее для будущих срезов AOA; I1 НЕ подключает запись этих
 * событий ни в одном существующем процессе. Колонка БД — обычная строка, поэтому
 * новый тип добавляется без миграции.
 */
enum AuditEventType: string
{
    case InquiryCreated = 'inquiry.created';
    case InquiryAssigned = 'inquiry.assigned';
    case InquiryReassigned = 'inquiry.reassigned';
    case InquiryClientLinked = 'inquiry.client_linked';
    case InquiryConverted = 'inquiry.converted';
    case BookingPriceRevised = 'booking.price_revised';
    case PaymentRecorded = 'payment.recorded';
    case DocumentPublished = 'document.published';
    case AdminAction = 'admin.action';

    public function label(): string
    {
        return match ($this) {
            self::InquiryCreated => 'Обращение создано',
            self::InquiryAssigned => 'Обращение назначено',
            self::InquiryReassigned => 'Обращение переназначено',
            self::InquiryClientLinked => 'Клиент привязан к обращению',
            self::InquiryConverted => 'Обращение оформлено как заявка',
            self::BookingPriceRevised => 'Цена заявки пересмотрена',
            self::PaymentRecorded => 'Платёж зарегистрирован',
            self::DocumentPublished => 'Документ опубликован клиенту',
            self::AdminAction => 'Действие администратора',
        };
    }

    /** Подпись для типа из БД, в том числе неизвестного этой версии кода. */
    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }
}
