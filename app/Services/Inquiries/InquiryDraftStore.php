<?php

namespace App\Services\Inquiries;

use App\Models\Inquiry;
use App\Models\User;
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
 * недопустимы (InquiryDraftData). События аудита нет: черновик — рабочая заметка,
 * а не изменение ответственности.
 */
class InquiryDraftStore
{
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

            $locked->forceFill($changes + [
                'draft_revision' => $locked->draft_revision + 1,
                'draft_updated_by' => $actor->id,
                'draft_updated_at' => now(),
            ])->save();

            return $locked;
        });
    }
}
