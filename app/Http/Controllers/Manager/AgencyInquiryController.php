<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
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
 * — следующий срез AOA-04. Назначение и передача ответственного (AOA-04-I2) — только администратор
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

    public function index(): View
    {
        $user = Auth::user();

        $inquiries = InquiryAuthority::scopeIndependent(Inquiry::query())
            ->visibleTo($user)
            ->with('assignee:id,name')
            ->orderByDesc('id')
            ->paginate(20);

        return view('manager.agency-inquiries.index', [
            'inquiries' => $inquiries,
            'canCreate' => $user->can('create', Inquiry::class),
        ]);
    }

    public function create(): View
    {
        Gate::authorize('create', Inquiry::class);

        return view('manager.agency-inquiries.create', [
            // Токен показа формы: повторная отправка той же формы не создаёт дубликат.
            'creationToken' => (string) Str::uuid(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        Gate::authorize('create', Inquiry::class);

        // Владелец, создатель, статус, источник, заявка и клиент задаются только сервером: любое
        // лишнее поле (подмена) отклоняется целиком, а не молча игнорируется.
        $unexpected = array_diff(array_map('strval', array_keys($request->all())), ['_token', 'contact_channel', 'creation_token']);
        if ($unexpected !== []) {
            throw ValidationException::withMessages([
                'request' => ['Форма содержит недопустимые поля. Ответственный и статус обращения задаются автоматически.'],
            ]);
        }

        $data = $request->validate([
            'contact_channel' => ['required', 'string', Rule::in(Inquiry::contactChannels())],
            'creation_token' => ['required', 'string', 'uuid'],
        ], [
            'contact_channel.required' => 'Выберите, как клиент связался с агентством: по телефону или в мессенджере.',
            'contact_channel.in' => 'Выберите, как клиент связался с агентством: по телефону или в мессенджере.',
            'creation_token.required' => 'Форма устарела. Обновите страницу и повторите.',
            'creation_token.uuid' => 'Форма устарела. Обновите страницу и повторите.',
        ]);

        $inquiry = $this->creator->createForManager(Auth::user(), $data['contact_channel'], $data['creation_token']);

        return redirect()
            ->route('cabinet.manager.agency-inquiries.show', $inquiry)
            ->with('success', $inquiry->wasRecentlyCreated
                ? 'Обращение создано и закреплено за вами. Заполните данные поездки и сохраните черновик.'
                : 'Это обращение уже было создано — открыта его карточка, дубликат не создан.');
    }

    public function show(Inquiry $inquiry): View
    {
        $this->visible($inquiry);

        $inquiry->load(['assignee:id,name', 'creator:id,name']);

        // Блок назначения и история — только администратору: данные для них даже не загружаются
        // для менеджера (не полагаемся на скрытие в шаблоне).
        $canAssign = Gate::allows('assign', $inquiry);

        return view('manager.agency-inquiries.show', [
            'inquiry' => $inquiry,
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
