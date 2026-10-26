<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\ClientVerificationRequest;
use App\Models\Inquiry;
use App\Models\User;
use App\Services\Accounts\ClientVerificationException;
use App\Services\Accounts\ClientVerificationService;
use App\Services\Inquiries\InquiryAuthority;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Запросы менеджера на проверку клиента (AOA-01-I7).
 *
 * Менеджер передаёт данные клиента администратору и видит только собственные
 * запросы с нейтральным состоянием. Ответ на отправку один и тот же для любого
 * email: контроллер не обращается к учётным записям пользователей.
 */
class ClientVerificationController extends Controller
{
    public function __construct(private readonly ClientVerificationService $service)
    {
    }

    public function index(Request $request): View
    {
        $this->assertManager($request->user());

        $requests = ClientVerificationRequest::query()
            ->where('requested_by', $request->user()->id)
            ->orderByDesc('id')
            ->paginate(20);

        // AOA-04-I4: запрос из обращения показывает данные клиента только пока менеджер отвечает за это
        // обращение; после передачи строка остаётся в истории, но данные скрыты (одним запросом на страницу).
        $agencyIds = $requests->getCollection()->pluck('agency_inquiry_id')->filter()->unique()->values();
        $accessibleInquiries = $agencyIds->isEmpty()
            ? []
            : InquiryAuthority::scopeIndependent(Inquiry::query())
                ->visibleTo($request->user())
                ->whereIn('inquiries.id', $agencyIds)
                ->pluck('inquiries.id')
                ->map(fn ($id): int => (int) $id)
                ->all();

        return view('manager.client-requests.index', compact('requests', 'accessibleInquiries'));
    }

    public function store(Request $request): RedirectResponse
    {
        $actor = $request->user();
        $this->assertManager($actor);

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
            $this->service->submit($actor, $validated['client_name'], $validated['client_email'] ?? null, $validated['client_phone'] ?? null);
        } catch (ClientVerificationException $e) {
            return redirect()->route('cabinet.manager.client-requests')
                ->withErrors([$e->field => $e->getMessage()])
                ->withInput();
        }

        return redirect()->route('cabinet.manager.client-requests')->with('success', ClientVerificationService::NEUTRAL_MESSAGE);
    }

    /** Запросы отправляет менеджер; администратор создаёт клиентов напрямую. */
    private function assertManager(User $user): void
    {
        abort_unless($user->hasRole(User::ROLE_MANAGER) && ! $user->hasRole(User::ROLE_ADMIN), 403);
    }
}
