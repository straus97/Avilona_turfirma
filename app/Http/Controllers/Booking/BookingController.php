<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Events\BookingCreated;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Role;
use App\Models\Tour;
use App\Models\User;
use App\Models\DestinationCity;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Symfony\Component\HttpFoundation\StreamedResponse;

class BookingController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Список заявок (для туриста - свои, для менеджера - назначенные, для админа - все)
     */
    public function index()
    {
        $user = Auth::user();
        
        if ($user->isAdmin()) {
            $bookings = Booking::with(['user', 'tour', 'manager'])
                ->latest()
                ->paginate(20);
        } elseif ($user->isManager()) {
            $bookings = Booking::with(['user', 'tour'])
                ->byManager($user->id)
                ->latest()
                ->paginate(20);
        } else {
            $bookings = Booking::with(['tour', 'manager'])
                ->byUser($user->id)
                ->latest()
                ->paginate(20);
        }
        
        return view('bookings.index', compact('bookings'));
    }

    /**
     * Форма создания заявки
     */
    public function create(Request $request)
    {
        $tourId = $request->query('tour_id');
        $tour = $tourId ? Tour::findOrFail($tourId) : null;
        
        // Получаем уникальные города вылета
        $departureCities = Tour::select('departure_city')
            ->distinct()
            ->orderBy('departure_city')
            ->pluck('departure_city');
        
        // Получаем страны из таблицы countries_images
        $destinationCountries = \App\Models\Countries_image::orderBy('title')->pluck('title');
        
        // Получаем список клиентов (только для менеджеров и админов)
        $clients = collect();
        if (Auth::user()->hasAnyRole(['manager', 'admin'])) {
            $clients = $this->touristClientQuery()
                ->select('id', 'name', 'email')
                ->orderBy('name')
                ->get();
        }
        
        return view('bookings.create', compact('tour', 'departureCities', 'destinationCountries', 'clients'));
    }

    /**
     * Сохранение заявки
     *
     * Владелец заявки, назначенный менеджер и начальный статус выводятся только
     * из аутентифицированного пользователя, а не из данных запроса.
     */
    public function store(Request $request)
    {
        $actor = Auth::user();

        // Роль определяется на сервере. Маршрут защищён только auth,
        // поэтому пользователь без известной роли не может создать заявку.
        $isAdmin   = $actor->isAdmin();
        $isManager = !$isAdmin && $actor->isManager();
        $isTourist = !$isAdmin && !$isManager && $actor->isTourist();

        if (!$isAdmin && !$isManager && !$isTourist) {
            abort(403, 'У вас нет прав на создание заявки');
        }

        $isStaff = $isAdmin || $isManager;

        $rules = [
            'tour_id' => 'nullable|exists:tours,id',
            'departure_city' => 'required|string|max:255',
            'destination_country' => 'required|string|max:255',
            'destination_city' => 'nullable|string|max:255',
            'start_date' => 'required|date|after:today',
            'start_date_end' => 'nullable|date|after_or_equal:start_date',
            'nights' => 'required|integer|min:1|max:30',
            'nights_max' => 'nullable|integer|min:1|max:30|gte:nights',
            'adults' => 'required|integer|min:1|max:10',
            'children' => 'integer|min:0|max:10',
            'children_ages' => 'nullable|array',
            'children_ages.*' => 'nullable|integer|min:0|max:17',
            'tourists_data' => 'nullable|array',
            'notes' => 'nullable|string',
        ];

        if ($isStaff) {
            $rules['is_new_client'] = 'nullable|boolean';
        }

        $validated = $request->validate($rules);

        // Данные клиента валидируются только для менеджера/админа и только
        // в том режиме, который подтверждён валидированным is_new_client.
        $isNewClient = $isStaff && (bool) ($validated['is_new_client'] ?? false);
        $clientData  = [];

        if ($isStaff && $isNewClient) {
            $clientData = $request->validate([
                'client_name'  => 'required|string|max:255',
                'client_email' => 'nullable|email|max:255|unique:users,email',
            ], [
                'client_email.unique' => 'Пользователь с таким email уже существует. Пожалуйста, выберите его из списка клиентов или используйте другой email.',
            ]);
        } elseif ($isStaff) {
            $clientData = $request->validate([
                'client_id' => [
                    'required',
                    'integer',
                    function (string $attribute, $value, \Closure $fail): void {
                        if (!$this->touristClientQuery()->whereKey($value)->exists()) {
                            $fail('Выберите клиента из списка активных туристов.');
                        }
                    },
                ],
            ]);
        }

        // Поля клиента никогда не попадают в заявку напрямую.
        unset($validated['is_new_client']);

        $validated['manager_id'] = $isManager ? $actor->id : null;
        $validated['status']     = $isManager ? Booking::STATUS_PROGRESS : Booking::STATUS_NEW;

        // Если указан тур, берем цену из него
        if (!empty($validated['tour_id'])) {
            $tour = Tour::find($validated['tour_id']);
            if ($tour) {
                $validated['total_price'] = $tour->price * ($validated['adults'] + ($validated['children'] ?? 0));
            }
        }

        try {
            $booking = DB::transaction(function () use ($actor, $validated, $clientData, $isTourist, $isNewClient): Booking {
                $data = $validated;

                if ($isTourist) {
                    $data['user_id'] = $actor->id;
                } elseif ($isNewClient) {
                    $clientEmail = $clientData['client_email'] ?? null;

                    $newTourist = $this->createTouristClient($clientData['client_name'], $clientEmail);

                    $data['user_id'] = $newTourist->id;
                    $data['notes']   = $this->appendNewClientNote($data['notes'] ?? null, $clientEmail);
                } else {
                    // Владелец перечитывается по тому же ограничению активного туриста.
                    $owner = $this->touristClientQuery()
                        ->lockForUpdate()
                        ->findOrFail($clientData['client_id']);

                    $data['user_id'] = $owner->id;
                }

                // Событие BookingCreated отправляется только после коммита.
                $booking = Booking::withoutEvents(fn (): Booking => Booking::create($data));

                if (!empty($data['destination_country']) && !empty($data['destination_city'])) {
                    DestinationCity::addCityIfNotExists(
                        $data['destination_country'],
                        $data['destination_city']
                    );
                }

                return $booking;
            });
        } catch (\Throwable $e) {
            Log::error('Booking creation failed', [
                'actor_id'  => $actor->id,
                'exception' => get_class($e),
            ]);

            return back()
                ->withInput()
                ->withErrors(['booking' => 'Не удалось создать заявку. Попробуйте ещё раз.']);
        }

        // Заявка уже зафиксирована в БД: сбой уведомления не должен её откатывать
        // или возвращать пользователю ошибку создания.
        try {
            event(new BookingCreated($booking));
        } catch (\Throwable $e) {
            Log::error('BookingCreated dispatch failed', [
                'booking_id' => $booking->id,
                'exception'  => get_class($e),
            ]);
        }

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка успешно создана! Наш менеджер свяжется с вами в ближайшее время.');
    }

    /**
     * Пользователи, которых менеджер/админ вправе выбрать владельцем заявки:
     * только активные туристы. Используется и в форме, и в валидации.
     */
    private function touristClientQuery(): Builder
    {
        return User::query()
            ->where('is_active', true)
            ->whereHas('roles', fn (Builder $query) => $query->where('name', Role::TOURIST));
    }

    /**
     * Создание нового клиента-туриста внутри транзакции.
     * Отсутствие роли "tourist" приводит к исключению и откату всей транзакции.
     */
    private function createTouristClient(string $name, ?string $email): User
    {
        $tempPassword = Str::password(12, true, true, false, false);

        $newTourist = User::create([
            'name'                     => $name,
            'email'                    => $email ?: $this->generateTechnicalEmail(),
            'password'                 => bcrypt($tempPassword),
            'password_change_required' => true,
            'temp_password'            => $tempPassword,
            'email_verified_at'        => null,
        ]);

        // firstOrFail: роль обязательна, молча пропустить назначение нельзя.
        $newTourist->assignRole(Role::TOURIST);

        return $newTourist;
    }

    /**
     * Технический адрес для клиента без email: недоставляемый домен + UUID.
     */
    private function generateTechnicalEmail(): string
    {
        do {
            $email = 'temp_' . Str::uuid() . '@' . User::TECHNICAL_EMAIL_DOMAIN;
        } while (User::where('email', $email)->exists());

        return $email;
    }

    /**
     * Пометка о созданном клиенте. Факт доставки письма не утверждается:
     * почта только ставится в очередь и может не дойти.
     */
    private function appendNewClientNote(?string $notes, ?string $clientEmail): string
    {
        $note = $clientEmail
            ? "Клиент создан автоматически. Email для отправки данных для входа: {$clientEmail}."
            : 'Клиент создан автоматически. Email не указан. Данные для входа автоматически не отправлялись.';

        $notes = trim((string) $notes);

        return $notes === '' ? $note : $notes . "\n\n" . $note;
    }

    /**
     * Просмотр заявки
     */
    public function show(Booking $booking)
    {
        $this->authorize('view', $booking);

        $booking->load(['user', 'tour', 'manager', 'messages.sender', 'messages.receiver', 'bookingDocuments.uploadedBy']);

        // Список кандидатов на роль ответственного нужен только админу (форма назначения
        // доступна только ему). Для остальных ролей отдаём пустую коллекцию, чтобы не
        // выполнять лишний запрос.
        $assignableEmployees = Auth::user()->isAdmin()
            ? User::assignableToBookings()->orderBy('name')->get()
            : collect();

        return view('bookings.show', compact('booking', 'assignableEmployees'));
    }

    /**
     * Форма редактирования заявки
     */
    public function edit(Booking $booking)
    {
        $this->authorize('update', $booking);
        
        return view('bookings.edit', compact('booking'));
    }

    /**
     * Обновление заявки
     */
    public function update(Request $request, Booking $booking)
    {
        $this->authorize('update', $booking);

        $user = Auth::user();

        $allowedStatuses = $booking->allowedStatusesForUpdate();

        // Валидация зависит от роли
        $rules = [
            'status' => ['required', Rule::in($allowedStatuses)],
        ];

        if ($user->isManager() || $user->isAdmin()) {
            $rules['manager_notes'] = 'nullable|string';
            $rules['total_price'] = 'nullable|numeric|min:0';
        }

        if ($user->isTourist() && $booking->status === Booking::STATUS_NEW) {
            $rules['notes'] = 'nullable|string';
            $rules['tourists_data'] = 'nullable|array';
        }

        $validated = $request->validate($rules);

        $newStatus = $validated['status'];
        unset($validated['status']);

        if ($newStatus !== $booking->status) {
            // Real transition: validate and fire event via the model method.
            $booking->transitionTo($newStatus);
        }

        if (!empty($validated)) {
            $booking->update($validated);
        }

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка обновлена');
    }

    /**
     * Назначение менеджера на заявку (только для админа)
     */
    public function assignManager(Request $request, Booking $booking)
    {
        $this->authorize('assignManager', $booking);
        
        $request->validate([
            'manager_id' => 'required|exists:users,id',
        ]);

        // Ответственным может быть только активный менеджер или администратор.
        // Единое правило берётся из User::assignableToBookings(), поэтому UI и сервер
        // применяют одну и ту же бизнес-логику.
        if (! User::assignableToBookings()->whereKey($request->manager_id)->exists()) {
            return back()->withErrors([
                'manager_id' => 'Выбранного сотрудника нельзя назначить ответственным по заявке.',
            ])->withInput();
        }

        if ($booking->status === Booking::STATUS_NEW) {
            // Первичное назначение: модель переводит заявку в PROGRESS. Событие
            // отправляется тем же защищённым путём, что и при переназначении.
            $booking->assignManager($request->manager_id);
            $this->dispatchManagerAssigned($booking);
        } elseif ($booking->reassignManager((int) $request->manager_id)) {
            // Переназначение зафиксировано и ответственный действительно сменился.
            $this->dispatchManagerAssigned($booking);
        }
        // Тот же ответственный на не-NEW заявке → reassignManager() вернул false → no-op.

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Ответственный назначен');
    }

    /**
     * Отправить ManagerAssigned для уже сохранённой заявки.
     *
     * Заявка уже зафиксирована в БД (первичное назначение или переназначение):
     * сбой уведомления не должен превращать успешное (пере)назначение в HTTP-ошибку
     * или откатывать manager_id. Поэтому исключение логируется и проглатывается.
     */
    private function dispatchManagerAssigned(Booking $booking): void
    {
        try {
            event(new \App\Events\ManagerAssigned($booking));
        } catch (\Throwable $e) {
            Log::error('ManagerAssigned dispatch failed', [
                'booking_id' => $booking->id,
                'exception'  => get_class($e),
            ]);
        }
    }

    /**
     * Отмена заявки
     */
    public function cancel(Booking $booking)
    {
        $this->authorize('cancel', $booking);

        if (!$booking->canTransitionTo(Booking::STATUS_CANCELLED)) {
            return back()->withErrors(['status' => 'Невозможно отменить заявку в текущем статусе.']);
        }

        $booking->transitionTo(Booking::STATUS_CANCELLED);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка отменена');
    }

    /**
     * Подтверждение заявки (только для менеджера/админа)
     */
    public function confirm(Booking $booking)
    {
        $this->authorize('confirm', $booking);

        if (!$booking->canTransitionTo(Booking::STATUS_CONFIRMED)) {
            return back()->withErrors(['status' => 'Нельзя подтвердить заявку в текущем статусе.']);
        }

        $booking->transitionTo(Booking::STATUS_CONFIRMED);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка подтверждена');
    }

    /**
     * Завершение заявки (только для менеджера/админа)
     */
    public function complete(Booking $booking)
    {
        $this->authorize('complete', $booking);

        if (!$booking->canTransitionTo(Booking::STATUS_COMPLETED)) {
            return back()->withErrors(['status' => 'Нельзя завершить заявку в текущем статусе.']);
        }

        $booking->transitionTo(Booking::STATUS_COMPLETED);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка завершена');
    }

    /**
     * Удаление заявки (только для админа)
     */
    public function destroy(Booking $booking)
    {
        $this->authorize('delete', $booking);
        
        $booking->delete();
        
        return redirect()->route('cabinet.admin.bookings')
            ->with('success', 'Заявка удалена');
    }

    /**
     * Загрузка документа к заявке (только менеджер/админ)
     */
    public function storeDocument(Request $request, Booking $booking): RedirectResponse
    {
        $this->authorizeBooking($booking);

        $validated = $request->validate([
            'title'         => 'required|string|max:255',
            'document_type' => 'required|in:contract,voucher,tickets,insurance,instructions,other',
            'file'          => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $path = null;

        try {
            $file = $request->file('file');

            if (!$file->isValid()) {
                throw new \RuntimeException('Uploaded file is not valid');
            }

            $path = $file->store("documents/bookings/{$booking->id}", 'local');

            if (!$path) {
                throw new \RuntimeException('File could not be stored on disk');
            }

            BookingDocument::create([
                'booking_id'    => $booking->id,
                'document_type' => $validated['document_type'],
                'title'         => $validated['title'],
                'file_path'     => $path,
                'file_size'     => $file->getSize(),
                'uploaded_by'   => Auth::id(),
                'uploaded_at'   => now(),
            ]);
        } catch (\Throwable $e) {
            if (is_string($path) && $path !== '') {
                Storage::disk('local')->delete($path);
            }

            Log::error('BookingDocument store failed', [
                'booking_id'  => $booking->id,
                'uploader_id' => Auth::id(),
                'exception'   => get_class($e),
            ]);

            return redirect()->route('bookings.show', $booking)
                ->with('error', 'Не удалось сохранить документ. Попробуйте ещё раз.');
        }

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Документ успешно загружен.');
    }

    /**
     * Скачивание документа по заявке (только менеджер/админ)
     */
    public function downloadDocument(Booking $booking, BookingDocument $document): StreamedResponse
    {
        $this->authorizeBooking($booking);

        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $this->buildDocumentDownloadName($document)
        );
    }

    /**
     * Удаление документа по заявке (только менеджер/админ)
     */
    public function destroyDocument(Booking $booking, BookingDocument $document): RedirectResponse
    {
        $this->authorizeBooking($booking);

        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        $filePath = $document->file_path;

        // Soft-delete the row first; only then remove the physical file.
        // This ensures a DB failure cannot leave an active record pointing to a deleted file.
        $document->delete();

        Storage::disk('local')->delete($filePath);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Документ удалён.');
    }

    /**
     * Сформировать безопасное имя файла для скачивания.
     *
     * Extension is derived from the stored file_path, never from user-supplied input.
     */
    private function buildDocumentDownloadName(BookingDocument $document): string
    {
        // Real extension comes from the stored path, not the title
        $ext = strtolower(pathinfo((string) $document->file_path, PATHINFO_EXTENSION));

        // Base name from the document title
        $name = (string) $document->title;

        // Strip ASCII control characters (CR, LF, tab, null, etc.)
        $name = (string) preg_replace('/[\x00-\x1F\x7F]+/', '', $name);

        // Replace path separators and Windows-invalid filename characters
        $name = (string) preg_replace('/[\/\\\\:*?"<>|]+/', '-', $name);

        // Collapse repeated whitespace to a single space
        $name = (string) preg_replace('/\s+/', ' ', $name);

        // Trim leading/trailing spaces and dots
        $name = trim($name, " \t\n\r\0\x0B.");

        // Fallback when sanitized title is empty
        if ($name === '') {
            $name = 'booking-document';
        }

        // Limit the base name to ~180 characters
        $name = Str::limit($name, 180, '');
        $name = rtrim($name, " .");

        if ($name === '') {
            $name = 'booking-document';
        }

        // Append actual extension from file_path when not already present
        if ($ext !== '' && !str_ends_with(strtolower($name), '.' . $ext)) {
            $name .= '.' . $ext;
        }

        // Final safety: remove any CR, LF, null byte, slash, or backslash that may remain
        $name = (string) preg_replace('/[\x00\x0A\x0D\/\\\\]+/', '', $name);

        if ($name === '' || $name === '.' . $ext) {
            $name = 'booking-document' . ($ext !== '' ? '.' . $ext : '');
        }

        return $name;
    }

    /**
     * Проверка прав доступа к заявке
     */
    private function authorizeBooking(Booking $booking)
    {
        $user = Auth::user();
        
        if ($user->isAdmin()) {
            return;
        }
        
        if ($user->isManager() && $booking->manager_id === $user->id) {
            return;
        }
        
        if ($user->isTourist() && $booking->user_id === $user->id) {
            return;
        }
        
        abort(403, 'У вас нет доступа к этой заявке');
    }
}
