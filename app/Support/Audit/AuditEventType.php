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
    case BookingAssigned = 'booking.assigned';
    case BookingReassigned = 'booking.reassigned';
    case BookingPriceRevised = 'booking.price_revised';
    case PaymentRecorded = 'payment.recorded';
    case DocumentPublished = 'document.published';
    case AdminAction = 'admin.action';
    case ClientVerificationRequested = 'client_verification.requested';
    case ClientVerificationLinked = 'client_verification.linked';
    case ClientVerificationCreated = 'client_verification.created';
    case ClientVerificationRejected = 'client_verification.rejected';

    public function label(): string
    {
        return match ($this) {
            self::InquiryCreated => 'Обращение создано',
            self::InquiryAssigned => 'Обращение назначено',
            self::InquiryReassigned => 'Обращение переназначено',
            self::InquiryClientLinked => 'Клиент привязан к обращению',
            self::InquiryConverted => 'Обращение оформлено как заявка',
            self::BookingAssigned => 'Ответственный по заявке назначен',
            self::BookingReassigned => 'Ответственный по заявке изменён',
            self::BookingPriceRevised => 'Цена заявки пересмотрена',
            self::PaymentRecorded => 'Платёж зарегистрирован',
            self::DocumentPublished => 'Документ опубликован клиенту',
            self::AdminAction => 'Действие администратора',
            self::ClientVerificationRequested => 'Запрос на проверку клиента передан',
            self::ClientVerificationLinked => 'Клиент проверен: привязан существующий аккаунт',
            self::ClientVerificationCreated => 'Клиент проверен: создан новый аккаунт',
            self::ClientVerificationRejected => 'Запрос на проверку клиента отклонён',
        };
    }

    /** Подпись для типа из БД, в том числе неизвестного этой версии кода. */
    public static function labelFor(string $value): string
    {
        return self::tryFrom($value)?->label() ?? $value;
    }
}
