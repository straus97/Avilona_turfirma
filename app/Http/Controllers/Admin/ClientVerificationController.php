<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\AuditEvent;
use App\Models\ClientVerificationRequest;
use App\Models\User;
use App\Services\Accounts\ClientVerificationException;
use App\Services\Accounts\ClientVerificationService;
use App\Services\IncomingInquiries\IncomingInquiryClientMatcher;
use App\Support\Audit\AuditEventType;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Проверка клиентов администратором (AOA-01-I7). Доступ ограничен middleware
 * role:admin на маршруте и политикой ClientVerificationRequestPolicy в сервисе.
 */
class ClientVerificationController extends Controller
{
    private const PER_PAGE = 20;

    public function __construct(private readonly ClientVerificationService $service)
    {
    }

    public function index(Request $request): View
    {
        $status = $request->query('status', ClientVerificationRequest::STATUS_PENDING);
        $status = in_array($status, [ClientVerificationRequest::STATUS_PENDING, ClientVerificationRequest::STATUS_APPROVED, ClientVerificationRequest::STATUS_REJECTED, 'all'], true)
            ? $status
            : ClientVerificationRequest::STATUS_PENDING;

        $requests = ClientVerificationRequest::query()
            ->with('requester:id,name')
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            ->orderByDesc('id')
            ->paginate(self::PER_PAGE)
            ->withQueryString();

        return view('admin.client-requests.index', compact('requests', 'status'));
    }

    public function show(Request $request, ClientVerificationRequest $clientRequest, IncomingInquiryClientMatcher $matcher): View
    {
        $this->authorize('resolve', $clientRequest);

        $clientRequest->load(['requester:id,name', 'resolver:id,name', 'resolvedUser:id,name', 'inquiry:id,workflow_state,assigned_to', 'agencyInquiry:id,status,assigned_to,client_user_id']);

        $searchTerm = trim((string) $request->query('client_q', ''));

        return view('admin.client-requests.show', [
            'clientRequest' => $clientRequest,
            'candidates' => $clientRequest->isPending() ? $this->service->candidatesFor($clientRequest, $request->user()) : collect(),
            'searchTerm' => $searchTerm,
            'searchResults' => $clientRequest->isPending() ? $matcher->search($searchTerm, $request->user()) : collect(),
            'history' => $this->history($clientRequest),
            // AOA-04-I4: контекст запроса мог измениться после подачи; решение всё равно проверяется под блокировкой.
            'staleReason' => $this->service->staleReason($clientRequest),
        ]);
    }

    public function link(Request $request, ClientVerificationRequest $clientRequest): RedirectResponse
    {
        $this->authorize('resolve', $clientRequest);

        $validated = $request->validate(['user_id' => ['required', 'integer']], [], ['user_id' => 'Аккаунт']);

        return $this->perform($clientRequest, function () use ($request, $clientRequest, $validated): string {
            $this->service->linkExisting($clientRequest, $request->user(), (int) $validated['user_id']);

            return 'Существующий клиент привязан к менеджеру.';
        });
    }

    public function create(Request $request, ClientVerificationRequest $clientRequest): RedirectResponse
    {
        $this->authorize('resolve', $clientRequest);

        return $this->perform($clientRequest, function () use ($request, $clientRequest): string {
            $this->service->approveNew($clientRequest, $request->user());

            return 'Новый клиент создан и привязан к менеджеру. Пароль не задан и никому не отправлен.';
        });
    }

    public function reject(Request $request, ClientVerificationRequest $clientRequest): RedirectResponse
    {
        $this->authorize('resolve', $clientRequest);

        $validated = $request->validate([
            'note' => ['required', 'string', 'max:' . (ClientVerificationService::NOTE_MAX_LENGTH * 4)],
        ], [], ['note' => 'Причина отклонения']);

        return $this->perform($clientRequest, function () use ($request, $clientRequest, $validated): string {
            $this->service->reject($clientRequest, $request->user(), $validated['note']);

            return 'Запрос отклонён.';
        });
    }

    /** @param  \Closure(): string  $action  возвращает текст успеха */
    private function perform(ClientVerificationRequest $clientRequest, \Closure $action): RedirectResponse
    {
        try {
            $message = $action();
        } catch (ClientVerificationException $e) {
            return redirect()->route('cabinet.admin.client-requests.show', $clientRequest)
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('cabinet.admin.client-requests.show', $clientRequest)->with('success', $message);
    }

    /**
     * История запроса из журнала аудита. Метаданные содержат только числовые id и
     * флаги; имена подставляются по id одним запросом.
     *
     * @return \Illuminate\Support\Collection<int, array<string, mixed>>
     */
    private function history(ClientVerificationRequest $clientRequest)
    {
        $events = AuditEvent::query()
            ->where('subject_type', ClientVerificationService::AUDIT_SUBJECT)
            ->where('subject_id', $clientRequest->id)
            ->whereIn('event_type', [
                AuditEventType::ClientVerificationRequested->value,
                AuditEventType::ClientVerificationLinked->value,
                AuditEventType::ClientVerificationCreated->value,
                AuditEventType::ClientVerificationRejected->value,
            ])
            ->orderBy('occurred_at')
            ->orderBy('id')
            ->limit(50)
            ->get();

        $names = User::query()->whereIn('id', $events->pluck('actor_id')->filter()->unique()->values())->pluck('name', 'id');

        return $events->map(fn (AuditEvent $e): array => [
            'type' => $e->typeLabel(),
            'at' => $e->occurred_at,
            'actor' => $names[$e->actor_id] ?? null,
        ]);
    }
}
