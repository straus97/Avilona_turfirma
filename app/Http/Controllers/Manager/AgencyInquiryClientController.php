<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Manager\Concerns\ResolvesAgencyInquiry;
use App\Models\Inquiry;
use App\Services\Accounts\ClientVerificationException;
use App\Services\Accounts\ClientVerificationService;
use App\Services\IncomingInquiries\IncomingInquiryClientMatcher;
use App\Services\Inquiries\InquiryClientAssociation;
use App\Services\Inquiries\InquiryClientAssociationException;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\ValidationException;

/**
 * Клиент самостоятельного обращения агентства (AOA-04-I4): запрос проверки (вариант C), явный выбор
 * видимого клиента, создание и отвязка администратором.
 *
 * Порядок защиты каждого действия: middleware роли → visible() (чужое/ничьё/бывшее/привязанное к E5 —
 * 404) → Gate (403) → повторная проверка сервисом под блокировкой по актуальным строкам. Идентификатор
 * обращения берётся из маршрута, идентификатор актора — из сессии; из полей формы принимаются только
 * перечисленные в белом списке.
 */
class AgencyInquiryClientController extends Controller
{
    use ResolvesAgencyInquiry;

    public function __construct(
        private readonly ClientVerificationService $verification,
        private readonly InquiryClientAssociation $association,
    ) {
    }

    /**
     * Нейтральный запрос проверки клиента администратором (только ответственный менеджер). Ответ и код
     * одинаковы для зарегистрированного и неизвестного email: контроллер и сервис не обращаются к
     * учётным записям, кроме строки самого менеджера.
     */
    public function requestVerification(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('requestClient', $inquiry);
        $this->rejectUnexpected($request, ['client_name', 'client_email', 'client_phone']);

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],
        ], [], [
            'client_name' => 'Имя клиента',
            'client_email' => 'Email клиента',
            'client_phone' => 'Телефон клиента',
        ]);

        try {
            $this->verification->submitForInquiry(
                Auth::user(),
                $inquiry,
                $validated['client_name'],
                $validated['client_email'] ?? null,
                $validated['client_phone'] ?? null,
            );
        } catch (AuthorizationException) {
            // Ответственный сменился между показом формы и отправкой.
            abort(404);
        } catch (ClientVerificationException $e) {
            return redirect()
                ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', ClientVerificationService::NEUTRAL_MESSAGE);
    }

    /**
     * Поиск среди клиентов, ВИДИМЫХ сотруднику (менеджер — только свои, администратор — справочник).
     * Строка поиска не попадает в URL: POST и одноразовая передача через сессию («old input»). Сам
     * поиск выполняет карточка обращения при показе, а не этот метод.
     */
    public function search(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('linkClient', $inquiry);
        $this->rejectUnexpected($request, ['client_q']);

        $request->validate([
            'client_q' => ['required', 'string', 'min:' . IncomingInquiryClientMatcher::SEARCH_MIN_LENGTH, 'max:100'],
        ], [
            'client_q.required' => 'Введите не менее ' . IncomingInquiryClientMatcher::SEARCH_MIN_LENGTH . ' символов для поиска.',
            'client_q.min' => 'Введите не менее ' . IncomingInquiryClientMatcher::SEARCH_MIN_LENGTH . ' символов для поиска.',
            'client_q.max' => 'Строка поиска слишком длинная: не более 100 символов.',
        ]);

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->withInput($request->only('client_q'));
    }

    /**
     * Явно выбрать клиента из ВИДИМЫХ сотруднику (повторный клиент менеджера или справочник
     * администратора). Чужой, несуществующий и нетуристский id дают один и тот же отказ.
     */
    public function select(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('linkClient', $inquiry);
        $this->rejectUnexpected($request, ['user_id', 'expected_client_id']);

        $data = $request->validate([
            'user_id' => ['required', 'integer', 'min:1'],
            'expected_client_id' => ['present', 'nullable', 'integer', 'min:1'],
        ], [
            'user_id.required' => InquiryClientAssociation::CLIENT_UNAVAILABLE_MESSAGE,
            'user_id.integer' => InquiryClientAssociation::CLIENT_UNAVAILABLE_MESSAGE,
            'user_id.min' => InquiryClientAssociation::CLIENT_UNAVAILABLE_MESSAGE,
            'expected_client_id.present' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_client_id.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_client_id.min' => 'Форма устарела. Обновите страницу и повторите.',
        ]);

        return $this->performAssociation($inquiry, function () use ($inquiry, $data): string {
            $result = $this->association->linkVisible(
                $inquiry,
                Auth::user(),
                (int) $data['user_id'],
                $data['expected_client_id'] === null ? null : (int) $data['expected_client_id'],
            );

            return $result === InquiryClientAssociation::RESULT_UNCHANGED
                ? 'Этот клиент уже привязан к обращению — изменений нет.'
                : 'Клиент привязан к обращению.';
        });
    }

    /** Создать нового клиента и привязать к обращению: только администратор. */
    public function create(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('administerClient', $inquiry);
        $this->rejectUnexpected($request, ['client_name', 'client_email', 'client_phone', 'expected_client_id']);

        $data = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],
            'expected_client_id' => ['present', 'nullable', 'integer', 'min:1'],
        ], [
            'expected_client_id.present' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_client_id.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_client_id.min' => 'Форма устарела. Обновите страницу и повторите.',
        ], [
            'client_name' => 'Имя клиента',
            'client_email' => 'Email клиента',
            'client_phone' => 'Телефон клиента',
        ]);

        return $this->performAssociation($inquiry, function () use ($inquiry, $data): string {
            $this->association->createAndLink(
                $inquiry,
                Auth::user(),
                $data['client_name'],
                $data['client_email'] ?? null,
                $data['client_phone'] ?? null,
                $data['expected_client_id'] === null ? null : (int) $data['expected_client_id'],
            );

            return 'Клиент создан и привязан к обращению. Пароль не задан и никому не отправлен.';
        });
    }

    /** Снять привязку клиента (исправление ошибки): только администратор, причина обязательна. */
    public function unlink(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('administerClient', $inquiry);
        $this->rejectUnexpected($request, ['expected_client_id', 'reason']);

        $data = $request->validate([
            'expected_client_id' => ['required', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:' . (InquiryClientAssociation::REASON_MAX_LENGTH * 4)],
        ], [
            'expected_client_id.required' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_client_id.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_client_id.min' => 'Форма устарела. Обновите страницу и повторите.',
            'reason.required' => 'Укажите причину: от ' . InquiryClientAssociation::REASON_MIN_LENGTH . ' до ' . InquiryClientAssociation::REASON_MAX_LENGTH . ' символов.',
            'reason.max' => 'Причина слишком длинная: не более ' . InquiryClientAssociation::REASON_MAX_LENGTH . ' символов.',
        ]);

        return $this->performAssociation($inquiry, function () use ($inquiry, $data): string {
            $this->association->unlink($inquiry, Auth::user(), (int) $data['expected_client_id'], (string) $data['reason']);

            return 'Привязка клиента снята. Запись добавлена в журнал.';
        });
    }

    /** @param  \Closure(): string  $action  возвращает текст успеха */
    private function performAssociation(Inquiry $inquiry, \Closure $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (AuthorizationException) {
            // Ответственный или роль изменились между показом формы и отправкой.
            abort(404);
        } catch (InquiryClientAssociationException $e) {
            if ($e->isStale()) {
                return redirect()
                    ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                    ->with('error', $e->getMessage() . ' Ваше действие НЕ выполнено.');
            }

            return redirect()
                ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', $message);
    }

    /**
     * Лишнее поле (подмена владельца, обращения, клиента, статуса …) отклоняет запрос целиком.
     *
     * @param  list<string>  $allowed
     */
    private function rejectUnexpected(Request $request, array $allowed): void
    {
        $unexpected = array_diff(array_map('strval', array_keys($request->all())), ['_token', '_method', ...$allowed]);

        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'client' => ['Форма содержит недопустимые поля. Обращение, ответственный и клиент определяются автоматически.'],
            ]);
        }
    }
}
