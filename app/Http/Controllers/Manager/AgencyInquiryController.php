<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Inquiries\InquiryAssignment;
use App\Services\Inquiries\InquiryAssignmentException;
use App\Services\Inquiries\InquiryAuthority;
use App\Services\Inquiries\InquiryDraftException;
use App\Services\Inquiries\InquiryDraftStore;
use App\Services\Inquiries\ManualInquiryCreator;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;

/**
 * Бизнес-обращения агентства, заведённые вручную (AOA-04-I1): звонок или сообщение в мессенджере.
 *
 * Это сущность Inquiry, а НЕ техническое поступление IncomingInquiry (Tourvisor, раздел «Входящие
 * обращения»): маршруты и имена разделены. Роль проверяет middleware role:manager,admin; кто именно
 * вправе действовать над записью — InquiryPolicy / InquiryAuthority (на сервере, не в Blade);
 * владельца и статус задаёт ManualInquiryCreator, черновик пишет только InquiryDraftStore.
 *
 * Чужое, ничьё или бывшее обращение для менеджера «не существует» (404 без данных) — как для
 * IncomingInquiry. Администратор видит все самостоятельные обращения; создание администратором
 * (AOA-04-I3) выбирает первого ответственного; правило задаётся по маршруту. Назначение и передача ответственного (AOA-04-I2) — только администратор
 * (InquiryAssignment); менеджер блок назначения и историю не видит. Привязанные к IncomingInquiry
 * обращения здесь не показываются и не передаются.
 */
class AgencyInquiryController extends Controller
{
    public function __construct(
        private readonly ManualInquiryCreator $creator,
        private readonly InquiryDraftStore $drafts,
        private readonly InquiryAssignment $assignment,
    ) {
    }

    /** Фильтры, которые переносятся в ссылки (список → карточка → назад). */
    private const FILTER_KEYS = ['status', 'channel', 'assignee'];

    /**
     * Список — только дополнительное сужение уже авторизованной выборки (самостоятельные обращения,
     * visibleTo): фильтр никогда не расширяет доступ. Менеджер не может отфильтровать по
     * ответственному — параметр для него не читается, видны лишь его обращения.
     */
    public function index(Request $request): View
    {
        $user = Auth::user();
        $isAdmin = $user->hasRole(User::ROLE_ADMIN);
        $eligibleStaff = $isAdmin ? $this->assignment->eligibleStaff() : collect();

        [$filters, $rejected] = $this->filters($request, $isAdmin, $eligibleStaff->pluck('id')->all());

        $query = InquiryAuthority::scopeIndependent(Inquiry::query())->visibleTo($user);

        if (isset($filters['status'])) {
            $query->where('inquiries.status', $filters['status']);
        }
        if (isset($filters['channel'])) {
            $query->where('inquiries.contact_channel', $filters['channel']);
        }
        if (isset($filters['assignee'])) {
            $filters['assignee'] === 'none'
                ? $query->whereNull('inquiries.assigned_to')
                : $query->where('inquiries.assigned_to', (int) $filters['assignee']);
        }

        $inquiries = $query
            ->with('assignee:id,name')
            ->orderByDesc('inquiries.id')
            ->paginate(20)
            ->appends($filters);

        return view('manager.agency-inquiries.index', [
            'inquiries' => $inquiries,
            'canCreate' => $user->can('create', Inquiry::class) || $user->can('createForStaff', Inquiry::class),
            'filters' => $filters,
            'filtersRejected' => $rejected,
            'eligibleStaff' => $eligibleStaff,
            'carry' => $filters + ($inquiries->currentPage() > 1 ? ['page' => $inquiries->currentPage()] : []),
        ]);
    }

    /**
     * Белый список параметров фильтра. Недопустимое значение не применяется (и помечается), а не
     * подставляется в запрос. Параметр «ответственный» читает только администратор.
     *
     * @param  list<int>  $eligibleIds
     * @return array{0: array<string, string>, 1: bool}
     */
    private function filters(Request $request, bool $isAdmin, array $eligibleIds): array
    {
        $filters = [];
        $rejected = false;

        $pick = function (string $key, array $allowed) use ($request, &$filters, &$rejected): void {
            $value = $request->query($key);
            if ($value === null || $value === '') {
                return;
            }
            if (is_string($value) && in_array($value, $allowed, true)) {
                $filters[$key] = $value;
            } else {
                $rejected = true;
            }
        };

        $pick('status', Inquiry::statuses());
        $pick('channel', Inquiry::contactChannels());

        if ($isAdmin) {
            $pick('assignee', ['none', ...array_map('strval', $eligibleIds)]);
        }

        return [$filters, $rejected];
    }

    public function create(): View
    {
        $user = Auth::user();
        $forStaff = $user->can('createForStaff', Inquiry::class);
        abort_unless($forStaff || $user->can('create', Inquiry::class), 403);

        return view('manager.agency-inquiries.create', [
            // Токен показа формы: повторная отправка той же формы не создаёт дубликат.
            'creationToken' => (string) Str::uuid(),
            'chooseAssignee' => $forStaff,
            'eligibleStaff' => $forStaff ? $this->assignment->eligibleStaff() : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $user = Auth::user();
        // Правило по маршруту: эффективная роль admin > manager (аккаунт с обеими ролями идёт по
        // правилам администратора и обязан выбрать ответственного); иначе — менеджер для себя.
        $forStaff = $user->can('createForStaff', Inquiry::class);
        if (! $forStaff) {
            Gate::authorize('create', Inquiry::class);
        }

        // Создатель, статус, источник, заявка и клиент задаются только сервером: любое лишнее поле
        // (подмена) отклоняется целиком, а не молча игнорируется. Ответственного принимает только
        // администратор.
        $allowed = ['_token', 'contact_channel', 'creation_token'];
        if ($forStaff) {
            $allowed[] = 'assignee_id';
        }
        $unexpected = array_diff(array_map('strval', array_keys($request->all())), $allowed);
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => [$forStaff
                    ? 'Форма содержит недопустимые поля. Создатель и статус обращения задаются автоматически.'
                    : 'Форма содержит недопустимые поля. Ответственный и статус обращения задаются автоматически.'],
            ]);
        }

        $rules = [
            'contact_channel' => ['required', 'string', Rule::in(Inquiry::contactChannels())],
            'creation_token' => ['required', 'string', 'uuid'],
        ];
        $messages = [
            'contact_channel.required' => 'Выберите, как клиент связался с агентством: по телефону или в мессенджере.',
            'contact_channel.in' => 'Выберите, как клиент связался с агентством: по телефону или в мессенджере.',
            'creation_token.required' => 'Форма устарела. Обновите страницу и повторите.',
            'creation_token.uuid' => 'Форма устарела. Обновите страницу и повторите.',
        ];
        if ($forStaff) {
            $rules['assignee_id'] = ['required', 'integer', 'min:1'];
            $messages['assignee_id.required'] = 'Выберите ответственного сотрудника.';
            $messages['assignee_id.integer'] = 'Выберите ответственного сотрудника из списка.';
            $messages['assignee_id.min'] = 'Выберите ответственного сотрудника из списка.';
        }

        $data = $request->validate($rules, $messages);

        $inquiry = $forStaff
            ? $this->creator->createForAdmin($user, (int) $data['assignee_id'], $data['contact_channel'], $data['creation_token'])
            : $this->creator->createForManager($user, $data['contact_channel'], $data['creation_token']);

        $created = $forStaff
            ? 'Обращение создано, ответственный назначен. Заполните данные поездки и сохраните черновик.'
            : 'Обращение создано и закреплено за вами. Заполните данные поездки и сохраните черновик.';

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', $inquiry->wasRecentlyCreated
                ? $created
                : 'Это обращение уже было создано — открыта его карточка, дубликат не создан. Повторно отправленные значения не применялись.');
    }

    public function show(Request $request, Inquiry $inquiry): View
    {
        $this->visible($inquiry);

        // Параметры списка (если пришли по ссылке из него) — только для ссылки «назад».
        $backQuery = array_filter(
            Arr::only($request->query(), [...self::FILTER_KEYS, 'page']),
            fn ($v): bool => is_string($v) && preg_match('/^[a-z_0-9]{1,32}$/', $v) === 1
        );

        $inquiry->load(['assignee:id,name', 'creator:id,name']);

        // Блок назначения и история — только администратору: данные для них даже не загружаются
        // для менеджера (не полагаемся на скрытие в шаблоне).
        $canAssign = Gate::allows('assign', $inquiry);

        return view('manager.agency-inquiries.show', [
            'inquiry' => $inquiry,
            'backUrl' => route('cabinet.manager.agency-inquiries', $backQuery),
            'draft' => $inquiry->draftSnapshot(),
            'canEdit' => $inquiry->isOpen() && Gate::allows('updateDraft', $inquiry),
            'draftConflict' => session('draft_conflict'),
            'canAssign' => $canAssign,
            'assignmentOpen' => $canAssign && $inquiry->status === Inquiry::STATUS_IN_PROGRESS,
            'eligibleStaff' => $canAssign ? $this->assignment->eligibleStaff() : collect(),
            'assignmentHistory' => $canAssign ? $this->assignment->history($inquiry) : collect(),
        ]);
    }

    /**
     * Назначить или передать обращение другому сотруднику (AOA-04-I2). Менеджер, которому обращение
     * видно, получает 403 (он его уже видит; права на назначение у него нет), чужое/ничьё/отсутствующее —
     * 404 как и везде. Выбор сотрудника, причину и версию формы валидирует и проверяет сервис под
     * блокировкой; запрос не может подменить ничего, кроме этих полей.
     */
    public function assign(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);
        Gate::authorize('assign', $inquiry);

        $data = $request->validate([
            'assignee_id' => ['required', 'integer', 'min:1'],
            'expected_assignee_id' => ['present', 'nullable', 'integer', 'min:1'],
            'reason' => ['required', 'string', 'max:' . (InquiryAssignment::REASON_MAX_LENGTH * 4)],
        ], [
            'assignee_id.required' => 'Выберите сотрудника, которому передаёте обращение.',
            'assignee_id.integer' => 'Выберите сотрудника из списка.',
            'expected_assignee_id.present' => 'Форма устарела. Обновите страницу и повторите.',
            'expected_assignee_id.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'reason.required' => 'Укажите причину: от ' . InquiryAssignment::REASON_MIN_LENGTH . ' до ' . InquiryAssignment::REASON_MAX_LENGTH . ' символов.',
            'reason.max' => 'Причина слишком длинная: не более ' . InquiryAssignment::REASON_MAX_LENGTH . ' символов.',
        ]);

        try {
            $result = $this->assignment->assign(
                $inquiry,
                Auth::user(),
                (int) $data['assignee_id'],
                $data['expected_assignee_id'] === null ? null : (int) $data['expected_assignee_id'],
                (string) $data['reason'],
            );
        } catch (InquiryAssignmentException $e) {
            if ($e->isStale()) {
                return redirect()
                    ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                    ->with('error', $e->getMessage() . ' Ваша передача НЕ выполнена.');
            }

            return redirect()
                ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', match ($result) {
                InquiryAssignment::RESULT_UNCHANGED => 'Этот сотрудник уже ответственный за обращение — изменений нет.',
                InquiryAssignment::RESULT_ASSIGNED => 'Ответственный назначен. Запись добавлена в историю назначений.',
                default => 'Обращение передано. Запись добавлена в историю назначений.',
            });
    }

    public function updateDraft(Request $request, Inquiry $inquiry): RedirectResponse
    {
        $this->visible($inquiry);

        $request->validate(['revision' => ['required', 'integer', 'min:0']], [
            'revision.required' => 'Форма устарела. Обновите страницу и повторите.',
            'revision.integer' => 'Форма устарела. Обновите страницу и повторите.',
            'revision.min' => 'Форма устарела. Обновите страницу и повторите.',
        ]);

        // Всё, кроме служебных полей, уходит в хранилище как есть: оно само отклоняет любые ключи вне
        // Inquiry::DRAFT_FIELDS (owner, assigned_to, status, booking_id, client_user_id …).
        $patch = $request->except(['_token', '_method', 'revision']);

        if (array_key_exists('children_ages', $patch) && is_string($patch['children_ages'])) {
            $patch['children_ages'] = $this->parseAges($patch['children_ages']);
        }

        try {
            $this->drafts->save($inquiry, Auth::user(), $patch, (int) $request->input('revision'));
        } catch (AuthorizationException) {
            // Ответственный сменился между показом формы и отправкой.
            abort(404);
        } catch (InquiryDraftException $e) {
            if ($e->field === 'revision') {
                return redirect()
                    ->route('cabinet.manager.agency-inquiries.show', $inquiry)
                    ->with('error', 'Черновик уже изменён в другой вкладке или другим сотрудником. Ваши правки НЕ сохранены: ниже загружена актуальная версия, а ваши значения показаны для сверки.')
                    ->with('draft_conflict', $this->conflictValues($patch));
            }

            return back()->withInput()->withErrors([$e->field => $e->getMessage()]);
        }

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', 'Черновик сохранён.');
    }

    /** Чужое/ничьё/бывшее — 404; также только самостоятельные (не привязанные к Tourvisor) обращения. */
    private function visible(Inquiry $inquiry): void
    {
        abort_unless(
            InquiryAuthority::provenance($inquiry) === InquiryAuthority::PROVENANCE_INDEPENDENT
                && Gate::allows('view', $inquiry),
            404
        );
    }

    /**
     * Возраст детей вводится одной строкой («5, 8»). Значения передаются как есть: целочисленность и
     * границы проверяет InquiryDraftData, поэтому «abc» даёт понятную ошибку, а не молчаливую потерю.
     *
     * @return list<string>|null
     */
    private function parseAges(string $raw): ?array
    {
        $parts = preg_split('/[\s,;]+/u', trim($raw), -1, PREG_SPLIT_NO_EMPTY) ?: [];

        return $parts === [] ? null : array_values($parts);
    }

    /**
     * Несохранённые значения для сверки при конфликте версий: только поля черновика, только
     * непустые скаляры; возраст детей — строкой.
     *
     * @param  array<string, mixed>  $patch
     * @return array<string, string>
     */
    private function conflictValues(array $patch): array
    {
        $values = [];

        foreach (Arr::only($patch, Inquiry::DRAFT_FIELDS) as $field => $value) {
            if (is_array($value)) {
                $value = implode(', ', array_filter($value, 'is_scalar'));
            }

            if (is_scalar($value) && trim((string) $value) !== '') {
                $values[$field] = (string) $value;
            }
        }

        return $values;
    }
}
