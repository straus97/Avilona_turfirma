<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\IncomingInquiry;
use App\Models\User;
use App\Services\IncomingInquiries\IncomingInquiryClientMatcher;
use App\Services\IncomingInquiries\IncomingInquiryWorkflow;
use App\Services\IncomingInquiries\IncomingInquiryWorkflowException;
use App\Support\Audit\AuditEventType;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Служебная обработка входящих обращений (E5-A3).
 *
 * Доступ к маршрутам ограничен middleware role:manager,admin; кто именно вправе
 * действовать над конкретным обращением, решает IncomingInquiryPolicy, а
 * допустимость действия в текущем состоянии — IncomingInquiryWorkflow
 * (внутри транзакции). Ничего здесь не обращается к Tourvisor и туроператорам.
 *
 * Видимость (AOA-01-I2A): менеджер видит только назначенные ему обращения.
 * Чужое или ничьё обращение для менеджера «не существует» (404 без данных) —
 * для ЛЮБОГО действия, до проверки права на само действие; право на действие,
 * которого у видящего менеджера нет (назначение, переназначение), даёт 403.
 */
class IncomingInquiryController extends Controller
{
    public function __construct(private readonly IncomingInquiryWorkflow $workflow)
    {
    }

    public function index(Request $request): View
    {
        $filter = $request->query('status');
        $filter = (is_string($filter) && array_key_exists($filter, IncomingInquiry::workflowLabels())) ? $filter : null;

        $inquiries = IncomingInquiry::query()
            ->visibleTo(Auth::user())
            ->with('assignee:id,name')
            ->when($filter !== null, function ($query) use ($filter): void {
                $query->where('workflow_state', $filter)->actionable();
            })
            ->orderByDesc('first_notified_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('manager.inquiries.index', compact('inquiries', 'filter'));
    }

    public function show(Request $request, IncomingInquiry $inquiry, IncomingInquiryClientMatcher $matcher): View
    {
        $this->visible($inquiry);

        $inquiry->load(['assignee:id,name', 'clientUser', 'booking', 'convertedBy:id,name', 'closedBy:id,name']);

        $actor = Auth::user();
        $inquiryOpen = $inquiry->isActionable() && $inquiry->workflow_state === IncomingInquiry::WORKFLOW_IN_PROGRESS;

        $canProcess = $inquiryOpen && $actor->can('process', $inquiry);
        $searchTerm = trim((string) $request->query('client_q', ''));

        $canAssign = $inquiry->isActionable()
            && $inquiry->workflow_state === IncomingInquiry::WORKFLOW_NEW
            && $inquiry->assigned_to === null
            && $actor->can('assign', $inquiry);
        $canReassign = $inquiryOpen && $actor->can('reassign', $inquiry);

        return view('manager.inquiries.show', [
            'inquiry' => $inquiry,
            'canClaim' => $inquiry->isActionable()
                && $inquiry->workflow_state === IncomingInquiry::WORKFLOW_NEW
                && $actor->can('claim', $inquiry),
            'canProcess' => $canProcess,
            'canClose' => $inquiry->isActionable()
                && in_array($inquiry->workflow_state, [IncomingInquiry::WORKFLOW_NEW, IncomingInquiry::WORKFLOW_IN_PROGRESS], true)
                && $actor->can('close', $inquiry),
            'canAssign' => $canAssign,
            'canReassign' => $canReassign,
            'assignableStaff' => ($canAssign || $canReassign)
                ? User::query()->assignableToBookings()->orderBy('name')->get(['id', 'name'])
                : collect(),
            'assignmentHistory' => $actor->can('assign', $inquiry) ? $this->assignmentHistory($inquiry) : collect(),
            'candidates' => $canProcess ? $matcher->candidatesFor($inquiry) : collect(),
            'searchTerm' => $searchTerm,
            'searchResults' => $canProcess ? $matcher->search($searchTerm) : collect(),
        ]);
    }

    /** Самоназначение администратора. Менеджер обращение себе не берёт. */
    public function claim(IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('claim', $inquiry);

        return $this->perform($inquiry, function () use ($inquiry): string {
            return $this->workflow->claim($inquiry, Auth::user())
                ? 'Обращение взято в работу.'
                : 'Обращение уже в работе у вас.';
        });
    }

    /** Первичное назначение нового обращения сотруднику (только администратор). */
    public function assign(Request $request, IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('assign', $inquiry);

        $validated = $request->validate(['assigned_to' => ['required', 'integer']], [], ['assigned_to' => 'Ответственный']);

        return $this->perform($inquiry, function () use ($inquiry, $validated): string {
            return $this->workflow->assign($inquiry, Auth::user(), (int) $validated['assigned_to'])
                ? 'Обращение назначено сотруднику.'
                : 'Обращение уже назначено этому сотруднику.';
        });
    }

    /** Смена ответственного у назначенного обращения: только администратор, с обязательной причиной. */
    public function reassign(Request $request, IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('reassign', $inquiry);

        $validated = $request->validate([
            'assigned_to' => ['required', 'integer'],
            'expected_assignee_id' => ['required', 'integer'],
            'reason' => ['required', 'string', 'min:' . IncomingInquiryWorkflow::REASON_MIN_LENGTH, 'max:' . IncomingInquiryWorkflow::REASON_MAX_LENGTH],
        ], [], [
            'assigned_to' => 'Ответственный',
            'expected_assignee_id' => 'Текущий ответственный',
            'reason' => 'Причина переназначения',
        ]);

        return $this->perform($inquiry, function () use ($inquiry, $validated): string {
            return $this->workflow->reassign(
                $inquiry,
                Auth::user(),
                (int) $validated['assigned_to'],
                $validated['reason'],
                (int) $validated['expected_assignee_id'],
            )
                ? 'Ответственный изменён.'
                : 'Этот сотрудник уже ответственный за обращение.';
        });
    }

    public function selectClient(Request $request, IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('process', $inquiry);

        $validated = $request->validate(['client_id' => ['required', 'integer']], [], ['client_id' => 'Клиент']);

        return $this->perform($inquiry, function () use ($inquiry, $validated): string {
            $client = $this->workflow->selectClient($inquiry, Auth::user(), (int) $validated['client_id']);

            return 'Клиент выбран: ' . $client->name . '.';
        });
    }

    public function createClient(Request $request, IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('process', $inquiry);

        $validated = $request->validate([
            'client_name' => ['required', 'string', 'max:255'],
            'client_email' => ['nullable', 'email', 'max:255'],
            'client_phone' => ['nullable', 'string', 'max:20'],
        ], [], [
            'client_name' => 'Имя клиента',
            'client_email' => 'Email клиента',
            'client_phone' => 'Телефон клиента',
        ]);

        return $this->perform($inquiry, function () use ($inquiry, $validated): string {
            $client = $this->workflow->createClient(
                $inquiry,
                Auth::user(),
                $validated['client_name'],
                $validated['client_email'] ?? null,
                $validated['client_phone'] ?? null,
            );

            return 'Создан новый клиент: ' . $client->name . '. Пароль не задан и никому не отправлен; клиент может установить его по ссылке для установки пароля.';
        });
    }

    public function sendPasswordSetup(IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('process', $inquiry);

        return $this->perform($inquiry, function () use ($inquiry): string {
            $this->workflow->sendPasswordSetup($inquiry, Auth::user());

            return 'Ссылка для установки пароля отправлена клиенту.';
        });
    }

    public function convert(Request $request, IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('process', $inquiry);

        // Возраст детей вводится строкой «5, 8» и приводится к массиву до валидации.
        if (is_string($request->input('children_ages'))) {
            $ages = array_values(array_filter(
                array_map('trim', explode(',', (string) $request->input('children_ages'))),
                fn (string $age): bool => $age !== ''
            ));
            $request->merge(['children_ages' => $ages]);
        }

        $validated = $request->validate([
            'departure_city' => ['required', 'string', 'max:255'],
            'destination_country' => ['required', 'string', 'max:255'],
            'destination_city' => ['nullable', 'string', 'max:255'],
            'start_date' => ['required', 'date', 'after:today'],
            'start_date_end' => ['nullable', 'date', 'after_or_equal:start_date'],
            'nights' => ['required', 'integer', 'min:1', 'max:30'],
            'nights_max' => ['nullable', 'integer', 'min:1', 'max:30', 'gte:nights'],
            'adults' => ['required', 'integer', 'min:1', 'max:10'],
            'children' => ['nullable', 'integer', 'min:0', 'max:10'],
            'children_ages' => ['nullable', 'array'],
            'children_ages.*' => ['integer', 'min:0', 'max:17'],
            // Итоговая стоимость ВСЕЙ заявки, подтверждённая менеджером (не за человека, не за ночь).
            'total_price' => ['required', 'numeric', 'min:0.01', 'max:99999999.99'],
            'price_verified' => ['accepted'],
            'notes' => ['nullable', 'string', 'max:5000'],
            'manager_notes' => ['nullable', 'string', 'max:5000'],
        ], [
            'price_verified.accepted' => 'Подтвердите, что цена и наличие мест проверены.',
        ], [
            'departure_city' => 'Город вылета',
            'destination_country' => 'Страна',
            'destination_city' => 'Курорт / город',
            'start_date' => 'Дата вылета (с)',
            'start_date_end' => 'Дата вылета (по)',
            'nights' => 'Ночей (от)',
            'nights_max' => 'Ночей (до)',
            'adults' => 'Взрослых',
            'children' => 'Детей',
            'children_ages.*' => 'Возраст ребёнка',
            'total_price' => 'Итоговая стоимость',
            'price_verified' => 'Подтверждение цены',
            'notes' => 'Информация для клиента',
            'manager_notes' => 'Заметки менеджера',
        ]);

        unset($validated['price_verified']);

        try {
            $result = $this->workflow->convert($inquiry, Auth::user(), $validated);
        } catch (AuthorizationException $e) {
            $this->hideOrRethrow($inquiry, $e);
        } catch (IncomingInquiryWorkflowException $e) {
            return redirect()->route('cabinet.manager.inquiries.show', $inquiry)
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        $message = $result['created']
            ? 'Заявка Avilona создана. Бронирование у туроператора не оформлялось и оплата не проводилась.'
            : 'Обращение уже оформлено — повторная заявка не создавалась.';

        return redirect()->route('cabinet.manager.inquiries.show', $inquiry)->with('success', $message);
    }

    public function close(Request $request, IncomingInquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        $this->authorize('close', $inquiry);

        $validated = $request->validate([
            'close_reason' => ['nullable', 'string', 'max:500'],
            'close_confirmed' => ['accepted'],
        ], [
            'close_confirmed.accepted' => 'Подтвердите закрытие обращения без бронирования.',
        ], [
            'close_reason' => 'Причина',
        ]);

        return $this->perform($inquiry, function () use ($inquiry, $validated): string {
            return $this->workflow->close($inquiry, Auth::user(), $validated['close_reason'] ?? null)
                ? 'Обращение закрыто без бронирования.'
                : 'Обращение уже закрыто.';
        });
    }

    /**
     * Выполнить действие сервиса и вернуться на карточку обращения: ожидаемые
     * отказы бизнес-процесса показываются как ошибки формы.
     *
     * @param  \Closure(): string  $action  возвращает текст успеха
     */
    private function perform(IncomingInquiry $inquiry, \Closure $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (AuthorizationException $e) {
            $this->hideOrRethrow($inquiry, $e);
        } catch (IncomingInquiryWorkflowException $e) {
            return redirect()->route('cabinet.manager.inquiries.show', $inquiry)
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('cabinet.manager.inquiries.show', $inquiry)->with('success', $message);
    }

    /**
     * Обращение вне видимости пользователя не раскрывается: 404 без данных.
     * Вызывается до проверки права на конкретное действие.
     */
    private function visible(IncomingInquiry $inquiry): void
    {
        abort_unless(Auth::user()->can('view', $inquiry), 404);
    }

    /**
     * Отказ сервиса по актуальной (заблокированной) строке: если пользователь
     * успел потерять видимость (обращение переназначено), ответ — тот же 404,
     * иначе — обычный 403.
     */
    private function hideOrRethrow(IncomingInquiry $inquiry, AuthorizationException $e): never
    {
        $current = IncomingInquiry::query()->find($inquiry->id);

        abort_unless($current !== null && Auth::user()->can('view', $current), 404);

        throw $e;
    }

    /**
     * История назначений для администратора: события аудита этого обращения.
     * Имена сотрудников подставляются по id; причина выводится только экранированной.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function assignmentHistory(IncomingInquiry $inquiry)
    {
        $events = AuditEvent::query()
            ->where('subject_type', IncomingInquiryWorkflow::AUDIT_SUBJECT)
            ->where('subject_id', $inquiry->id)
            ->whereIn('event_type', [AuditEventType::InquiryAssigned->value, AuditEventType::InquiryReassigned->value])
            ->orderByDesc('occurred_at')
            ->orderByDesc('id')
            ->limit(50)
            ->get();

        $ids = $events->flatMap(fn (AuditEvent $e): array => [
            $e->actor_id,
            $e->metadata['previous_assignee_id'] ?? null,
            $e->metadata['new_assignee_id'] ?? null,
        ])->filter()->unique()->values();

        $names = User::query()->whereIn('id', $ids)->pluck('name', 'id');

        return $events->map(fn (AuditEvent $e): array => [
            'type' => $e->typeLabel(),
            'at' => $e->occurred_at,
            'actor' => $names[$e->actor_id] ?? null,
            'previous' => $names[$e->metadata['previous_assignee_id'] ?? 0] ?? null,
            'new' => $names[$e->metadata['new_assignee_id'] ?? 0] ?? null,
            'reason' => $e->metadata['reason'] ?? null,
        ]);
    }
}
