<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Inquiry;
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
 * и назначение — следующий срез AOA-04. Привязанные к IncomingInquiry обращения здесь не показываются.
 */
class AgencyInquiryController extends Controller
{
    public function __construct(
        private readonly ManualInquiryCreator $creator,
        private readonly InquiryDraftStore $drafts,
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

        return view('manager.agency-inquiries.show', [
            'inquiry' => $inquiry,
            'draft' => $inquiry->draftSnapshot(),
            'canEdit' => $inquiry->isOpen() && Gate::allows('updateDraft', $inquiry),
            'draftConflict' => session('draft_conflict'),
        ]);
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
