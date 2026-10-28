<?php

namespace App\Services\Inquiries;

use App\Models\ClientVerificationRequest;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use App\Support\Audit\AuditMetadata;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use InvalidArgumentException;

/**
 * Закрытие САМОСТОЯТЕЛЬНОГО обращения без заявки (AOA-04-I5): клиент отказался или запрос неактуален.
 *
 * Единственный переход среза: in_progress → closed. closed терминален (повторного открытия и
 * оформления нет), Booking не создаётся. Одна транзакция: блокировка строки обращения, повторная
 * проверка прав по актуальной учётной записи и текущей ответственности (InquiryPolicy::close),
 * самостоятельное происхождение, допустимый статус, ожидаемое состояние формы, причина, переход и
 * событие аудита `inquiry.closed`. Сбой аудита откатывает закрытие; отказ не оставляет события.
 *
 * Запрос проверки клиента (AOA-04-I4): пока по обращению есть ожидающий запрос, закрыть его нельзя —
 * сначала администратор решает или отклоняет запрос. Так «висящий» запрос не остаётся
 * действующим против закрытого обращения, а семантика проверки не меняется. Порядок блокировок тот же,
 * что у решения запроса и передачи: сначала строка обращения; поэтому закрытие и решение
 * администратора сериализуются, а проигравший видит новое состояние.
 *
 * Меняются только status, closed_at, closed_by. Создатель, ответственный, origin, канал, контакт,
 * клиент, черновик и IncomingInquiry не затрагиваются; ничего не удаляется. Привязанное к
 * IncomingInquiry обращение остаётся во власти E5 и здесь отклоняется до любой записи.
 */
class InquiryClosure
{
    public const REASON_MIN_LENGTH = 5;
    public const REASON_MAX_LENGTH = 200;

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  int|null  $expectedAssigneeId  ответственный, которого сотрудник видел на форме (null — «не назначен»)
     *
     * @throws AuthorizationException
     * @throws InquiryClosureException
     */
    public function close(Inquiry $inquiry, User $actor, string $expectedStatus, ?int $expectedAssigneeId, string $reason): void
    {
        DB::transaction(function () use ($inquiry, $actor, $expectedStatus, $expectedAssigneeId, $reason): void {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

            // Актуальная учётная запись: роль и активность могли измениться после входа.
            $staff = User::query()->active()->find($actor->id);

            if ($staff === null) {
                throw new AuthorizationException('Эта учётная запись не может закрывать обращения.');
            }

            Gate::forUser($staff)->authorize('close', $locked);

            if (InquiryAuthority::provenance($locked) !== InquiryAuthority::PROVENANCE_INDEPENDENT) {
                throw new InquiryClosureException('Обращение, пришедшее из внешнего источника, закрывают в разделе «Входящие обращения».');
            }

            if ($locked->status === Inquiry::STATUS_CLOSED) {
                throw new InquiryClosureException('Обращение уже закрыто. Повторное закрытие не выполнялось.', InquiryClosureException::FIELD_STALE);
            }

            if ($locked->status !== Inquiry::STATUS_IN_PROGRESS) {
                throw new InquiryClosureException('Обращение не находится в работе — закрыть его нельзя.');
            }

            $assigneeId = $locked->assigned_to !== null ? (int) $locked->assigned_to : null;

            if ($expectedStatus !== $locked->status || $expectedAssigneeId !== $assigneeId) {
                throw new InquiryClosureException('Обращение уже изменено (статус или ответственный). Обновите страницу и повторите. Закрытие НЕ выполнено.', InquiryClosureException::FIELD_STALE);
            }

            $reason = $this->cleanReason($reason);

            if (ClientVerificationRequest::query()
                ->where('agency_inquiry_id', $locked->id)
                ->where('status', ClientVerificationRequest::STATUS_PENDING)
                ->exists()) {
                throw new InquiryClosureException('По обращению есть запрос на проверку клиента, ожидающий решения администратора. Пока он не решён или не отклонён, обращение закрыть нельзя.', 'verification');
            }

            try {
                $locked->forceFill([
                    'status' => Inquiry::STATUS_CLOSED,
                    'closed_at' => now(),
                    'closed_by' => $staff->id,
                    'close_reason' => $reason,
                ])->save();
            } catch (InquiryInvariantViolation) {
                throw new InquiryClosureException('Данные обращения нарушают правила согласованности — закрыть его нельзя. Обратитесь к разработчикам.');
            }

            $this->audit->record(AuditEventType::InquiryClosed, $staff, InquiryDraftStore::AUDIT_SUBJECT, (int) $locked->id, [
                'previous_status' => Inquiry::STATUS_IN_PROGRESS,
                'new_status' => Inquiry::STATUS_CLOSED,
                'assignee_id' => $assigneeId,
                'reason' => $reason,
            ]);
        });
    }

    /**
     * Фактическое свидетельство закрытия для карточки — только то, что записано в самой строке
     * (closed_at, closed_by, close_reason); для старых записей без этих данных ничего не достраивается.
     * Имя берётся по id; если учётной записи нет, не выдумывается (null).
     *
     * @return array{at: \Illuminate\Support\Carbon|null, actor: string|null, reason: string|null}
     */
    public function evidence(Inquiry $inquiry): array
    {
        return [
            'at' => $inquiry->closed_at,
            'actor' => $inquiry->closed_by === null ? null : User::query()->whereKey($inquiry->closed_by)->value('name'),
            'reason' => $inquiry->close_reason,
        ];
    }

    /** Причина обязательна, сжимается по пробелам, ограничена и проходит валидатор метаданных аудита. */
    private function cleanReason(string $reason): string
    {
        $reason = trim((string) preg_replace('/\s+/u', ' ', $reason));
        $length = mb_strlen($reason);

        if ($length < self::REASON_MIN_LENGTH || $length > self::REASON_MAX_LENGTH) {
            throw new InquiryClosureException('Причина должна содержать от ' . self::REASON_MIN_LENGTH . ' до ' . self::REASON_MAX_LENGTH . ' символов.');
        }

        try {
            AuditMetadata::validate(['reason' => $reason]);
        } catch (InvalidArgumentException) {
            throw new InquiryClosureException('Причина не должна содержать email, телефоны, пути к файлам и длинные ключеподобные строки.');
        }

        return $reason;
    }
}
