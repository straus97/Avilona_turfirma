<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\BookingDocument;
use App\Models\Tour;
use App\Models\User;
use App\Models\DestinationCity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
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
            $clients = User::whereHas('roles', function($query) {
                $query->where('name', 'tourist');
            })
            ->select('id', 'name', 'email')
            ->orderBy('name')
            ->get();
        }
        
        return view('bookings.create', compact('tour', 'departureCities', 'destinationCountries', 'clients'));
    }

    /**
     * Сохранение заявки
     */
    public function store(Request $request)
    {
        $user = Auth::user();
        
        // Базовая валидация
        $validated = $request->validate([
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
            'is_new_client' => 'nullable|boolean',
            'client_id' => 'nullable|exists:users,id',
            'client_name' => 'nullable|string|max:255',
            'client_email' => 'nullable|email|max:255',
        ]);

        // Определяем user_id для заявки
        if ($user->hasAnyRole(['manager', 'admin'])) {
            // Если менеджер/админ создает заявку
            if ($request->is_new_client) {
                // Проверяем, существует ли email в базе
                $existingUser = User::where('email', $request->client_email)->first();
                if ($existingUser) {
                    return back()->withErrors([
                        'client_email' => 'Пользователь с таким email уже существует. Пожалуйста, выберите его из списка клиентов или используйте другой email.'
                    ])->withInput();
                }
                
                // Генерируем читаемый временный пароль
                $tempPassword = 'AV' . rand(1000, 9999) . strtoupper(substr(md5(time()), 0, 4));
                
                // Создаем нового пользователя-туриста
                $newTourist = User::create([
                    'name' => $request->client_name,
                    'email' => $request->client_email ?? 'temp_' . time() . '@avilona.ru',
                    'password' => bcrypt($tempPassword),
                    'password_change_required' => true,
                    'temp_password' => $tempPassword, // Сохраняем временный пароль в БД
                    'email_verified_at' => null,
                ]);
                
                // Назначаем роль "tourist"
                $touristRole = \App\Models\Role::where('name', 'tourist')->first();
                if ($touristRole) {
                    $newTourist->roles()->attach($touristRole->id);
                }
                
                // Привязываем заявку к новому туристу
                $validated['user_id'] = $newTourist->id;
                
                // Добавляем пометку в notes
                $validated['notes'] = ($validated['notes'] ?? '') . "\n\n⚠️ Клиент создан автоматически. Отправлены данные для входа на email: {$request->client_email}";
            } else {
                // Выбран существующий клиент
                $validated['user_id'] = $request->client_id;
            }
        } else {
            // Турист создает заявку для себя
            $validated['user_id'] = $user->id;
        }

        $validated['status'] = Booking::STATUS_NEW;

        // Если указан тур, берем цену из него
        if ($request->tour_id) {
            $tour = Tour::find($request->tour_id);
            if ($tour) {
                $validated['total_price'] = $tour->price * ($validated['adults'] + ($validated['children'] ?? 0));
            }
        }

        $booking = Booking::create($validated);

        // Автоматически добавляем курорт/город в БД если его нет
        if (!empty($validated['destination_country']) && !empty($validated['destination_city'])) {
            DestinationCity::addCityIfNotExists(
                $validated['destination_country'],
                $validated['destination_city']
            );
        }

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка успешно создана! Наш менеджер свяжется с вами в ближайшее время.');
    }

    /**
     * Просмотр заявки
     */
    public function show(Booking $booking)
    {
        $this->authorizeBooking($booking);
        
        $booking->load(['user', 'tour', 'manager', 'messages.sender', 'messages.receiver', 'bookingDocuments.uploadedBy']);
        
        return view('bookings.show', compact('booking'));
    }

    /**
     * Форма редактирования заявки
     */
    public function edit(Booking $booking)
    {
        $this->authorizeBooking($booking);
        
        return view('bookings.edit', compact('booking'));
    }

    /**
     * Обновление заявки
     */
    public function update(Request $request, Booking $booking)
    {
        $this->authorizeBooking($booking);
        
        $user = Auth::user();
        
        // Валидация зависит от роли
        $rules = [
            'status' => 'required|in:' . implode(',', array_keys(Booking::availableStatuses())),
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
        
        $booking->update($validated);
        
        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка обновлена');
    }

    /**
     * Назначение менеджера на заявку (только для админа)
     */
    public function assignManager(Request $request, Booking $booking)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
        
        $request->validate([
            'manager_id' => 'required|exists:users,id',
        ]);
        
        if ($booking->status === Booking::STATUS_NEW) {
            $booking->assignManager($request->manager_id);
        } else {
            $booking->update(['manager_id' => $request->manager_id]);
        }
        
        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Менеджер назначен');
    }

    /**
     * Отмена заявки
     */
    public function cancel(Booking $booking)
    {
        $this->authorizeBooking($booking);
        
        $booking->cancel();
        
        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка отменена');
    }

    /**
     * Подтверждение заявки (только для менеджера/админа)
     */
    public function confirm(Booking $booking)
    {
        $user = Auth::user();
        
        if (!$user->isManager() && !$user->isAdmin()) {
            abort(403);
        }
        
        $booking->confirm();
        
        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка подтверждена');
    }

    /**
     * Завершение заявки (только для менеджера/админа)
     */
    public function complete(Booking $booking)
    {
        $user = Auth::user();
        
        if (!$user->isManager() && !$user->isAdmin()) {
            abort(403);
        }
        
        $booking->complete();
        
        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка завершена');
    }

    /**
     * Удаление заявки (только для админа)
     */
    public function destroy(Booking $booking)
    {
        if (!Auth::user()->isAdmin()) {
            abort(403);
        }
        
        $booking->delete();
        
        return redirect()->route('bookings.index')
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
