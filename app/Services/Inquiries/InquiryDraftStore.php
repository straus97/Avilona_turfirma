<?php

namespace App\Services\Inquiries;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/**
 * Надёжное сохранение черновика обращения (AOA-03-I1, остаточный риск R-D).
 *
 * Черновик живёт в самом обращении, а не в Booking: заявка из него не создаётся и
 * клиент не определяется. Сервис НЕ создаёт обращений и не назначает ответственных
 * (AOA-04): он принимает уже существующее обращение и актора из доверенного контекста.
 *
 * Всё внутри одной транзакции: блокировка строки, авторизация по актуальной строке
 * (ответственный мог смениться между показом формы и отправкой), проверка, что
 * обращение ещё открыто, и оптимистичная версия — устаревшая форма отклоняется, а не
 * перезаписывает чужое сохранение. Поля владельца/статуса/клиента/заявки в патче
 * недопустимы (InquiryDraftData).
 *
 * AOA-03-I4: принятое сохранение фиксируется событием аудита `inquiry.draft_updated` в той же
 * транзакции (сбой записи аудита откатывает и черновик). Событие несёт только идентификаторы:
 * обращение, актор, версии «до/после» и ИМЕНА изменённых полей — значения черновика
 * (заметки, пожелания, даты) в журнал не попадают. Отклонённые попытки (права, валидация,
 * устаревшая версия, закрытое обращение) и пустой патч событий не создают. Это единственное
 * поддерживаемое сегодня изменение Inquiry со стороны Inquiry: назначение, статус, клиент и
 * заявка на Inquiry пока не меняются ни одним сервисом (AOA-04/05), и событий для них нет.
 */
class InquiryDraftStore
{
    public const AUDIT_SUBJECT = 'inquiry';

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $patch  см. InquiryDraftData
     * @param  int  $expectedRevision  версия черновика, которую сотрудник видел на форме
     *
     * @throws \Illuminate\Auth\Access\AuthorizationException
     * @throws \Illuminate\Validation\ValidationException
     * @throws InquiryDraftException
     */
    public function save(Inquiry $inquiry, User $actor, array $patch, int $expectedRevision): Inquiry
    {
        return DB::transaction(function () use ($inquiry, $actor, $patch, $expectedRevision): Inquiry {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

            // Привязанное обращение (AOA-03-I3): полномочия зависят от назначения источника, и оно
            // читается под блокировкой — переназначение E5 не проскочит между проверкой и записью.
            // Порядок блокировок (Inquiry → IncomingInquiry) обратного пути не имеет: E5 блокирует
            // только IncomingInquiry.
            if ($locked->incoming_inquiry_id !== null) {
                IncomingInquiry::query()->lockForUpdate()->find($locked->incoming_inquiry_id);
            }

            Gate::forUser($actor)->authorize('updateDraft', $locked);

            if (! $locked->isOpen()) {
                throw new InquiryDraftException('Обращение уже оформлено или закрыто — черновик изменить нельзя.');
            }

            if ((int) $locked->draft_revision !== $expectedRevision) {
                throw new InquiryDraftException('Черновик уже изменён. Обновите страницу и повторите.', 'revision');
            }

            $changes = InquiryDraftData::validatePatch($patch, $locked);

            if ($changes === []) {
                return $locked;
            }

            $previousRevision = (int) $locked->draft_revision;

            $locked->forceFill($changes);
            // Фактически изменённые поля (повторная отправка тех же значений версию поднимает,
            // как и раньше, но список изменённых полей в этом случае пуст).
            $changedFields = array_values(array_intersect(Inquiry::DRAFT_FIELDS, array_keys($locked->getDirty())));

            $locked->forceFill([
                'draft_revision' => $previousRevision + 1,
                'draft_updated_by' => $actor->id,
                'draft_updated_at' => now(),
            ])->save();

            $this->audit->record(AuditEventType::InquiryDraftUpdated, $actor, self::AUDIT_SUBJECT, (int) $locked->id, [
                'previous_revision' => $previousRevision,
                'new_revision' => $previousRevision + 1,
                'changed_field_count' => count($changedFields),
                'changed_fields' => $changedFields === [] ? null : implode(',', $changedFields),
            ]);

            return $locked;
        });
    }
}
