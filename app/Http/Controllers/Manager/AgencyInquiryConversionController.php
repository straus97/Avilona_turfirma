<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesAgencyInquiry;
use App\Models\Inquiry;
use App\Services\Inquiries\InquiryConversion;
use App\Services\Inquiries\InquiryConversionException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Явное оформление самостоятельного обращения в заявку (AOA-05-I1).
 *
 * Порядок защиты: middleware роли (турист — 403) → visible() (чужое/ничьё/бывшее/привязанное к E5 — 404) →
 * Gate convert → белый список полей формы → повторная проверка сервисом под блокировкой по актуальным
 * строкам. Идентификаторы обращения и актора берутся из маршрута и сессии; владелец заявки, ответственный,
 * статус, тур, цена и клиент из формы НЕ принимаются (лишнее поле отклоняется целиком).
 */
class AgencyInquiryConversionController extends Controller
{
    use ResolvesAgencyInquiry;

    /**
     * Поля формы оформления; всё остальное — подмена. Данные заявки вложены в `booking[...]`, чтобы их
     * повторный показ (old input) не смешивался с одноимёнными полями черновика на той же странице.
     */
    private const ALLOWED_FIELDS = ['_token', 'expected_status', 'expected_owner_id', 'expected_client_id', 'confirm_conversion', 'booking'];

    private const BOOKING_FIELDS = [
        'departure_city', 'destination_country', 'destination_city', 'start_date', 'start_date_end',
        'nights', 'nights_max', 'adults', 'children', 'children_ages', 'notes', 'manager_notes',
    ];

    public function __construct(private readonly InquiryConversion $conversion)
    {
    }

    public function store(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('convert', $inquiry);

        $booking = $request->input('booking');
        $unexpected = array_diff(array_map('strval', array_keys($request->all())), self::ALLOWED_FIELDS);
        $unexpectedBooking = is_array($booking) ? array_diff(array_map('strval', array_keys($booking)), self::BOOKING_FIELDS) : [];
        if ($unexpected !== [] || $unexpectedBooking !== []) {
            throw ValidationException::withMessages([
                'request' => ['Форма содержит недопустимые поля. Клиент, ответственный, статус и цена заявки определяются автоматически.'],
            ]);
        }

        // Возраст детей вводится строкой «5, 8» и приводится к массиву до валидации.
        if (is_string($request->input('booking.children_ages'))) {
            $ages = array_values(array_filter(
                array_map('trim', explode(',', (string) $request->input('booking.children_ages'))),
                fn (string $age): bool => $age !== ''
            ));
            $request->merge(['booking' => array_merge((array) $request->input('booking'), ['children_ages' => $ages])]);
        }

        $stale = 'Форма устарела. Обновите страницу и повторите.';

        $data = $request->validate([
            'expected_status' => ['required', 'string', 'max:32'],
            'expected_owner_id' => ['present', 'nullable', 'integer', 'min:1'],
            'expected_client_id' => ['present', 'nullable', 'integer', 'min:1'],
            'confirm_conversion' => ['accepted'],
            'booking.departure_city' => ['required', 'string', 'max:255'],
            'booking.destination_country' => ['required', 'string', 'max:255'],
            'booking.destination_city' => ['nullable', 'string', 'max:255'],
            'booking.start_date' => ['required', 'date', 'after:today'],
            'booking.start_date_end' => ['nullable', 'date', 'after_or_equal:booking.start_date'],
            'booking.nights' => ['required', 'integer', 'min:1', 'max:30'],
            'booking.nights_max' => ['nullable', 'integer', 'min:1', 'max:30', 'gte:booking.nights'],
            'booking.adults' => ['required', 'integer', 'min:1', 'max:10'],
            'booking.children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'booking.children_ages' => ['nullable', 'array'],
            'booking.children_ages.*' => ['integer', 'min:0', 'max:17'],
            'booking.notes' => ['nullable', 'string', 'max:5000'],
            'booking.manager_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'expected_status.required' => $stale,
            'expected_owner_id.present' => $stale,
            'expected_owner_id.integer' => $stale,
            'expected_client_id.present' => $stale,
            'expected_client_id.integer' => $stale,
            'confirm_conversion.accepted' => 'Подтвердите, что клиент согласен и вы оформляете заявку по обращению.',
        ], [
            'booking.departure_city' => 'Город вылета',
            'booking.destination_country' => 'Страна',
            'booking.destination_city' => 'Курорт / город',
            'booking.start_date' => 'Дата вылета (с)',
            'booking.start_date_end' => 'Дата вылета (по)',
            'booking.nights' => 'Ночей (от)',
            'booking.nights_max' => 'Ночей (до)',
            'booking.adults' => 'Взрослых',
            'booking.children' => 'Детей',
            'booking.children_ages.*' => 'Возраст ребёнка',
            'booking.notes' => 'Информация для клиента',
            'booking.manager_notes' => 'Заметки менеджера',
        ]);

        $details = $data['booking'];

        $back = redirect()->route('cabinet.manager.agency-inquiries.show', $inquiry);

        try {
            $result = $this->conversion->convert(
                $inquiry,
                Auth::user(),
                $details,
                (string) $data['expected_status'],
                $data['expected_owner_id'] === null ? null : (int) $data['expected_owner_id'],
                $data['expected_client_id'] === null ? null : (int) $data['expected_client_id'],
            );
        } catch (AuthorizationException) {
            // Ответственный сменился или учётная запись потеряла права между показом формы и отправкой.
            abort(404);
        } catch (InquiryConversionException $e) {
            if ($e->isStale()) {
                return $back->with('error', $e->getMessage());
            }

            return $back->withErrors([$e->field => $e->getMessage()])->withInput();
        }

        // Номер и ссылку заявки сообщение не содержит: показывает их карточка, и только тем, кому заявка доступна.
        return $back->with('success', $result['created']
            ? 'Обращение оформлено в заявку. Бронирование у туроператора не оформлялось, оплата не проводилась, цена не задана.'
            : 'Это обращение уже оформлено — повторная заявка не создавалась. Повторно отправленные значения не применялись.');
    }
}
