<?php

namespace App\Services\Inquiries;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Builder;

/**
 * Единственное место, где решается, чья ответственность за Inquiry считается действующей
 * в переходный период, когда одно и то же внешнее обращение представлено двумя записями
 * (AOA-03-I3).
 *
 * Модель полномочий (до утверждённого переключения — «legacy главнее»):
 *
 *  - ПРИВЯЗАННЫЙ Inquiry (origin = external_intake, есть источник IncomingInquiry): рабочий
 *    процесс E5 по-прежнему идёт на IncomingInquiry и остаётся источником истины. Inquiry-сторона
 *    даёт менеджеру доступ только когда её ответственный СОВПАДАЕТ с ответственным источника.
 *    При расхождении побеждающий не выбирается (ни по времени, ни иначе): Inquiry-сторона
 *    закрыта для всех менеджеров, а существующий доступ по legacy-записи не меняется.
 *  - САМОСТОЯТЕЛЬНЫЙ Inquiry (ручное происхождение, источника нет): собственный assigned_to
 *    — единственный источник ответственности.
 *  - Некорректное происхождение (внешнее без источника, ручное с источником, источник
 *    удалён): доступ менеджерам закрыт; администратор по-прежнему видит всё (надзор).
 *
 * Права создателя, прежних ответственных, авторов конверсии/закрытия и исполнителя переноса
 * доступа НЕ дают — только текущий assigned_to (и совпадающий с ним источник).
 *
 * Будущее переключение источника истины меняет только ветку «привязанный» в этом классе.
 */
final class InquiryAuthority
{
    public const PROVENANCE_INDEPENDENT = 'independent';
    public const PROVENANCE_LINKED = 'linked';
    public const PROVENANCE_MALFORMED = 'malformed';

    /** @return list<string> */
    private static function manualOrigins(): array
    {
        return [Inquiry::ORIGIN_MANAGER_CREATED, Inquiry::ORIGIN_ADMIN_CREATED];
    }

    /** Происхождение одной записи (без обращения к базе; наличие источника проверяют отдельно). */
    public static function provenance(Inquiry $inquiry): string
    {
        if ($inquiry->incoming_inquiry_id === null && in_array($inquiry->origin, self::manualOrigins(), true)) {
            return self::PROVENANCE_INDEPENDENT;
        }

        if ($inquiry->incoming_inquiry_id !== null && $inquiry->origin === Inquiry::ORIGIN_EXTERNAL_INTAKE) {
            return self::PROVENANCE_LINKED;
        }

        return self::PROVENANCE_MALFORMED;
    }

    /**
     * Ответственный менеджер ли это СЕЙЧАС (одна запись). Совпадает с
     * scopeResponsibleManager(): эквивалентность закреплена тестом.
     */
    public static function isResponsibleManager(Inquiry $inquiry, User $user): bool
    {
        if ($inquiry->assigned_to === null
            || (int) $inquiry->assigned_to !== (int) $user->id
            || ! $user->hasRole(User::ROLE_MANAGER)) {
            return false;
        }

        return match (self::provenance($inquiry)) {
            self::PROVENANCE_INDEPENDENT => true,
            self::PROVENANCE_LINKED => self::sourceAssignee($inquiry) === (int) $inquiry->assigned_to,
            default => false,
        };
    }

    /** Текущий ответственный legacy-источника (null — не назначен или источника нет). */
    private static function sourceAssignee(Inquiry $inquiry): ?int
    {
        $value = IncomingInquiry::query()->whereKey($inquiry->incoming_inquiry_id)->value('assigned_to');

        return $value === null ? null : (int) $value;
    }

    /** Самостоятельные Inquiry: ручное происхождение и нет источника. */
    public static function scopeIndependent(Builder $query): Builder
    {
        return $query->whereNull('inquiries.incoming_inquiry_id')
            ->whereIn('inquiries.origin', self::manualOrigins());
    }

    /** Привязанные Inquiry, источник которых существует (сироты в выборку не попадают). */
    public static function scopeLinked(Builder $query): Builder
    {
        return $query->where('inquiries.origin', Inquiry::ORIGIN_EXTERNAL_INTAKE)
            ->whereExists(fn ($source) => $source->selectRaw('1')->from('incoming_inquiries')
                ->whereColumn('incoming_inquiries.id', 'inquiries.incoming_inquiry_id'));
    }

    /**
     * Привязанные пары, где ответственные различаются (в том числе «назначен» ↔ «не назначен»).
     * Явные ветки вместо NOT(a = b): с NULL последнее даёт UNKNOWN и конфликт терялся бы.
     */
    public static function scopeOwnershipConflict(Builder $query): Builder
    {
        return $query->where('inquiries.origin', Inquiry::ORIGIN_EXTERNAL_INTAKE)
            ->whereExists(fn ($source) => $source->selectRaw('1')->from('incoming_inquiries')
                ->whereColumn('incoming_inquiries.id', 'inquiries.incoming_inquiry_id')
                ->where(function ($w): void {
                    $w->where(fn ($x) => $x->whereNull('incoming_inquiries.assigned_to')->whereNotNull('inquiries.assigned_to'))
                        ->orWhere(fn ($x) => $x->whereNotNull('incoming_inquiries.assigned_to')->whereNull('inquiries.assigned_to'))
                        ->orWhere(fn ($x) => $x->whereNotNull('incoming_inquiries.assigned_to')
                            ->whereNotNull('inquiries.assigned_to')
                            ->whereColumn('incoming_inquiries.assigned_to', '!=', 'inquiries.assigned_to'));
                }));
    }

    /** Записи, за которые менеджер отвечает сейчас (то же правило, что isResponsibleManager). */
    public static function scopeResponsibleManager(Builder $query, User $manager): Builder
    {
        return $query->where('inquiries.assigned_to', $manager->id)
            ->where(function (Builder $w): void {
                $w->where(fn (Builder $own) => self::scopeIndependent($own))
                    ->orWhere(function (Builder $linked): void {
                        $linked->where('inquiries.origin', Inquiry::ORIGIN_EXTERNAL_INTAKE)
                            ->whereExists(fn ($source) => $source->selectRaw('1')->from('incoming_inquiries')
                                ->whereColumn('incoming_inquiries.id', 'inquiries.incoming_inquiry_id')
                                ->whereColumn('incoming_inquiries.assigned_to', 'inquiries.assigned_to'));
                    });
            });
    }

    /**
     * Подзапрос id Inquiry, которые менеджер вправе видеть сейчас. Предназначен для будущих
     * серверных фильтров (например, уведомлений, ссылающихся на Inquiry): права вычисляются
     * при показе, а не по факту адресации.
     */
    public static function responsibleInquiryIds(User $manager): Builder
    {
        return self::scopeResponsibleManager(Inquiry::query()->select('inquiries.id'), $manager);
    }
}
