<?php

namespace App\Services\Inquiries;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;

/**
 * Сводка обращений по статусам для сотрудника (AOA-03-I3). Заготовка серверной стороны:
 * ни один экран её пока не использует (счётчиков обращений в интерфейсе сегодня нет — боковое
 * меню и панель считают заявки и сообщения), поэтому существующие счётчики не затрагиваются.
 *
 * Единица учёта — бизнес-обращение. Оно учитывается РОВНО ОДИН раз:
 *  - внешнее обращение — по записи IncomingInquiry (legacy остаётся источником истины для E5),
 *    даже если к нему уже привязан Inquiry: привязанный Inquiry — его теневой снимок и отдельно
 *    не считается (поэтому пара не удваивается, а при расхождении ответственных учитывается по
 *    legacy-записи, как и в списке E5);
 *  - самостоятельный Inquiry (будущее ручное создание) — по собственной записи.
 * В счёт legacy входят только обрабатываемые обращения (`actionable()`): технические состояния
 * загрузки бизнес-обращениями не являются. Некорректное происхождение не считается ни у кого,
 * кроме отдельной строки для администратора.
 *
 * Права те же, что у списков: администратор — все; менеджер — только свои сейчас; остальные —
 * нули. Запросов фиксированное число (по одному агрегату на источник), без проверок по записи.
 */
final class InquiryWorkloadSummary
{
    /**
     * @return array{
     *     by_status: array<string, int>,
     *     total: int,
     *     external: int,
     *     independent: int,
     *     ownership_conflicts: int,
     *     unresolved_provenance: int
     * }
     */
    public static function for(User $actor): array
    {
        $byStatus = array_fill_keys(Inquiry::statuses(), 0);
        $external = 0;
        $independent = 0;

        $legacy = IncomingInquiry::query()
            ->actionable()
            ->visibleTo($actor)
            ->groupBy('workflow_state')
            ->selectRaw('workflow_state, count(*) as aggregate')
            ->pluck('aggregate', 'workflow_state');

        foreach ($legacy as $state => $count) {
            if (array_key_exists((string) $state, $byStatus)) {
                $byStatus[(string) $state] += (int) $count;
                $external += (int) $count;
            }
        }

        $own = InquiryAuthority::scopeIndependent(Inquiry::query())
            ->visibleTo($actor)
            ->groupBy('inquiries.status')
            ->selectRaw('inquiries.status, count(*) as aggregate')
            ->pluck('aggregate', 'status');

        foreach ($own as $status => $count) {
            if (array_key_exists((string) $status, $byStatus)) {
                $byStatus[(string) $status] += (int) $count;
                $independent += (int) $count;
            }
        }

        $conflicts = 0;
        $unresolved = 0;

        if ($actor->hasRole(User::ROLE_ADMIN)) {
            $conflicts = InquiryAuthority::scopeOwnershipConflict(Inquiry::query())->count();
            $unresolved = Inquiry::query()
                ->whereNot(fn ($q) => InquiryAuthority::scopeIndependent($q))
                ->whereNot(fn ($q) => InquiryAuthority::scopeLinked($q))
                ->count();
        }

        return [
            'by_status' => $byStatus,
            'total' => array_sum($byStatus),
            'external' => $external,
            'independent' => $independent,
            'ownership_conflicts' => $conflicts,
            'unresolved_provenance' => $unresolved,
        ];
    }
}
