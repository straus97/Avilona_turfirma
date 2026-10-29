<?php

namespace App\Services\Inquiries;

use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\ClientVerificationRequest;
use App\Models\DestinationCity;
use App\Models\Inquiry;
use App\Models\Role;
use App\Models\User;
use App\Services\Audit\AuditRecorder;
use App\Support\Audit\AuditEventType;
use App\Support\Inquiries\InquiryInvariantViolation;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\Log;

/**
 * Явное оформление САМОСТОЯТЕЛЬНОГО обращения в ОДНУ заявку Avilona (AOA-05-I1).
 *
 * Единственный переход: in_progress → converted (терминальный). Заявка создаётся только этим действием
 * сотрудника: ни сохранение черновика, ни привязка клиента, ни импорт оформления не запускают. Клиент
 * НИКОГДА не создаётся и не выводится из контакта — нужен аккаунт, явно привязанный к обращению.
 *
 * Одна транзакция (всё откатывается при любом сбое, в том числе аудита):
 *  1. блокировка строки обращения (первой, как в InquiryAssignment / InquiryClosure / InquiryClientAssociation /
 *     ClientVerificationService — поэтому все эти операции с одним обращением сериализуются);
 *  2. блокировка строк пользователей (актор, клиент, ответственный) ОДНИМ запросом по возрастанию id —
 *     две встречные оформления (A оформляет за B и наоборот) не блокируют друг друга; взаимная блокировка с
 *     чужими операциями, если случится, разрешается повтором транзакции (DB::transaction, 3 попытки);
 *  3. повторная проверка по заблокированным строкам: актор активен и вправе (InquiryPolicy::convert, текущий
 *     ответственный), обращение самостоятельное и «в работе», форма не устарела (статус, ответственный и
 *     клиент те же, что на форме), нет запроса проверки клиента, клиент — активный турист, ответственный —
 *     допустимый сотрудник (User::assignableToBookings());
 *  4. заявка (владелец — привязанный клиент, manager_id — ТЕКУЩИЙ ответственный, статус progress, цены нет);
 *  5. условный UPDATE обращения (второй рубеж; плюс UNIQUE inquiries.booking_id) + запись аудита
 *     `inquiry.converted`.
 *
 * Идемпотентность: права проверяются ДО возврата уже оформленного обращения; повтор возвращает существующую
 * заявку (created = false) без второй заявки и без второго события. Какую именно заявку видно сотруднику,
 * решает вызывающий код по BookingPolicy — сервис её содержимое наружу не отдаёт.
 *
 * Сервис НЕ создаёт OperatorBooking, платежей и пересчётов, не меняет клиента, ответственного, создателя,
 * контакт и черновик, не вызывает внешних сервисов внутри транзакции. Письмо о новой заявке (BookingCreated) —
 * тот же контракт, что у ручного создания и E5: отправляется один раз ПОСЛЕ коммита; сбой письма заявку не откатывает.
 */
class InquiryConversion
{
    private const DEADLOCK_ATTEMPTS = 3;

    public function __construct(private readonly AuditRecorder $audit)
    {
    }

    /**
     * @param  array<string, mixed>  $details  уже провалидированные поля заявки (белый список ниже)
     * @param  string  $expectedStatus  статус, который сотрудник видел на форме
     * @param  int|null  $expectedAssigneeId  ответственный, которого он видел
     * @param  int|null  $expectedClientId  клиент, которого он видел
     * @return array{booking: Booking, created: bool}  created = false — обращение уже было оформлено ранее
     *
     * @throws AuthorizationException
     * @throws InquiryConversionException
     */
    public function convert(Inquiry $inquiry, User $actor, array $details, string $expectedStatus, ?int $expectedAssigneeId, ?int $expectedClientId): array
    {
        $result = DB::transaction(function () use ($inquiry, $actor, $details, $expectedStatus, $expectedAssigneeId, $expectedClientId): array {
            $locked = Inquiry::query()->lockForUpdate()->findOrFail($inquiry->id);

            $users = $this->lockUsers($locked, $actor);
            $staff = $users[(int) $actor->id] ?? null;

            // Актуальная учётная запись: роль и активность могли измениться после входа.
            if ($staff === null || ! $staff->is_active) {
                throw new AuthorizationException('Эта учётная запись не может оформлять обращения в заявки.');
            }

            // Права проверяются ДО идемпотентного возврата: потерявший доступ сотрудник заявку повтором не получает.
            Gate::forUser($staff)->authorize('convert', $locked);

            if (InquiryAuthority::provenance($locked) !== InquiryAuthority::PROVENANCE_INDEPENDENT) {
                throw new InquiryConversionException('Обращение, пришедшее из внешнего источника, оформляют в разделе «Входящие обращения».');
            }

            if ($locked->status === Inquiry::STATUS_CONVERTED && $locked->booking_id !== null) {
                return ['booking' => Booking::withTrashed()->findOrFail($locked->booking_id), 'created' => false];
            }

            if ($locked->status !== Inquiry::STATUS_IN_PROGRESS) {
                // «converted» без booking_id: заявку удалили вместе с аккаунтом клиента (внешние ключи БД). Терминальное
                // состояние не отменяется и вторая заявка не создаётся.
                throw new InquiryConversionException(match ($locked->status) {
                    Inquiry::STATUS_CLOSED => 'Обращение закрыто — оформить его в заявку нельзя.',
                    Inquiry::STATUS_CONVERTED => 'Обращение уже оформлено ранее, созданная заявка удалена. Повторное оформление не выполняется.',
                    default => 'Обращение не находится в работе — оформить его в заявку нельзя.',
                });
            }

            $assigneeId = $locked->assigned_to !== null ? (int) $locked->assigned_to : null;
            $clientId = $locked->client_user_id !== null ? (int) $locked->client_user_id : null;

            if ($expectedStatus !== $locked->status || $expectedAssigneeId !== $assigneeId || $expectedClientId !== $clientId) {
                throw new InquiryConversionException(
                    'Обращение уже изменено (статус, ответственный или клиент). Обновите страницу и повторите. Заявка НЕ создана.',
                    InquiryConversionException::FIELD_STALE
                );
            }

            if ($clientId === null) {
                throw new InquiryConversionException(
                    'К обращению не привязан клиент. Сначала явно привяжите существующий аккаунт клиента: контакт, имя, телефон или email аккаунт не выбирают.',
                    InquiryConversionException::FIELD_CLIENT
                );
            }

            if (ClientVerificationRequest::query()
                ->where('agency_inquiry_id', $locked->id)
                ->where('status', ClientVerificationRequest::STATUS_PENDING)
                ->exists()) {
                throw new InquiryConversionException(
                    'По обращению есть запрос на проверку клиента, ожидающий решения администратора. Пока он не решён или не отклонён, обращение оформить нельзя.',
                    InquiryConversionException::FIELD_CLIENT
                );
            }

            $client = $users[$clientId] ?? null;

            if ($client === null || ! $client->is_active || ! $client->hasRole(Role::TOURIST)) {
                throw new InquiryConversionException(
                    'Привязанный клиент больше недоступен (неактивен или не турист). Администратор должен снять привязку и привязать клиента заново.',
                    InquiryConversionException::FIELD_CLIENT
                );
            }

            $assignee = $assigneeId === null ? null : User::query()->assignableToBookings()->find($assigneeId);

            if ($assignee === null) {
                throw new InquiryConversionException('Ответственный сотрудник больше не может вести заявки. Администратор должен передать обращение другому сотруднику.');
            }

            // Явный белый список: владелец, ответственный, статус и tour_id никогда не берутся из запроса;
            // цены нет (total_price остаётся пустым), paid_amount — значение БД по умолчанию.
            $booking = Booking::withoutEvents(fn (): Booking => Booking::create([
                'user_id' => $client->id,
                'manager_id' => $assignee->id,
                'tour_id' => null,
                'status' => Booking::STATUS_PROGRESS,
                'departure_city' => $details['departure_city'],
                'destination_country' => $details['destination_country'],
                'destination_city' => $details['destination_city'] ?? null,
                'start_date' => $details['start_date'],
                'start_date_end' => $details['start_date_end'] ?? null,
                'nights' => $details['nights'],
                'nights_max' => $details['nights_max'] ?? null,
                'adults' => $details['adults'],
                'children' => $details['children'] ?? 0,
                'children_ages' => $details['children_ages'] ?? null,
                'notes' => $details['notes'] ?? null,
                'manager_notes' => $details['manager_notes'] ?? null,
            ]));

            $now = now();

            // Инварианты модели проверяются на будущем состоянии до записи.
            $next = clone $locked;
            $next->forceFill(['status' => Inquiry::STATUS_CONVERTED, 'booking_id' => $booking->id, 'converted_at' => $now]);
            try {
                $next->assertInvariants();
            } catch (InquiryInvariantViolation) {
                throw new InquiryConversionException('Данные обращения нарушают правила согласованности — оформить его нельзя. Обратитесь к разработчикам.');
            }

            // Условный UPDATE — второй рубеж: даже при сбое блокировки строка не перейдёт в converted дважды.
            $marked = Inquiry::query()
                ->whereKey($locked->id)
                ->where('status', Inquiry::STATUS_IN_PROGRESS)
                ->whereNull('booking_id')
                ->where('assigned_to', $assignee->id)
                ->where('client_user_id', $client->id)
                ->update([
                    'status' => Inquiry::STATUS_CONVERTED,
                    'booking_id' => $booking->id,
                    'converted_at' => $now,
                    'converted_by' => $staff->id,
                    'updated_at' => $now,
                ]);

            if ($marked !== 1) {
                throw new InquiryConversionException('Обращение уже обработано другим сотрудником. Обновите страницу. Заявка НЕ создана.', InquiryConversionException::FIELD_STALE);
            }

            if (! empty($details['destination_country']) && ! empty($details['destination_city'])) {
                DestinationCity::addCityIfNotExists($details['destination_country'], $details['destination_city']);
            }

            // Только идентификаторы и статусы: ни имён, ни контактов, ни заметок, ни сумм.
            $this->audit->record(AuditEventType::InquiryConverted, $staff, InquiryDraftStore::AUDIT_SUBJECT, (int) $locked->id, [
                'previous_status' => Inquiry::STATUS_IN_PROGRESS,
                'new_status' => Inquiry::STATUS_CONVERTED,
                'booking_id' => (int) $booking->id,
                'assignee_id' => (int) $assignee->id,
                'client_id' => (int) $client->id,
            ]);

            return ['booking' => $booking, 'created' => true];
        }, self::DEADLOCK_ATTEMPTS);

        if ($result['created']) {
            // Заявка уже зафиксирована: сбой уведомления не откатывает её (как в BookingController::store и E5).
            try {
                event(new BookingCreated($result['booking']));
            } catch (\Throwable $e) {
                Log::error('BookingCreated dispatch failed', [
                    'booking_id' => $result['booking']->id,
                    'exception' => get_class($e),
                ]);
            }
        }

        return $result;
    }

    /**
     * Фактическое свидетельство оформления для карточки — только то, что записано в самой строке
     * (converted_at, converted_by); для старых записей без этих данных ничего не достраивается.
     *
     * @return array{at: \Illuminate\Support\Carbon|null, actor: string|null}
     */
    public function evidence(Inquiry $inquiry): array
    {
        return [
            'at' => $inquiry->converted_at,
            'actor' => $inquiry->converted_by === null ? null : User::query()->whereKey($inquiry->converted_by)->value('name'),
        ];
    }

    /**
     * Блокировка строк актора, клиента и ответственного одним запросом в порядке возрастания id.
     *
     * @return array<int, User>
     */
    private function lockUsers(Inquiry $locked, User $actor): array
    {
        $ids = array_values(array_unique(array_filter([
            (int) $actor->id,
            $locked->client_user_id !== null ? (int) $locked->client_user_id : null,
            $locked->assigned_to !== null ? (int) $locked->assigned_to : null,
        ])));

        return User::query()->whereIn('id', $ids)->orderBy('id')->lockForUpdate()->get()->keyBy('id')->all();
    }
}
