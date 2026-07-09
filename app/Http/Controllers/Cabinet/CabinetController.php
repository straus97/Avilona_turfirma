<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use App\Models\UserDocument;
use App\Models\BookingDocument;
use App\Models\BonusAccount;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CabinetController extends Controller
{
    protected function redirectIfNotTourist(?string $section = null, array $params = []): ?RedirectResponse
    {
        $user = Auth::user();

        if ($user->hasAnyRole(['manager'])) {
            return match ($section) {
                'bookings' => redirect()->route('cabinet.manager.bookings'),
                'chat' => redirect()->route('cabinet.manager.chat', $params),
                'profile' => redirect()->route('cabinet.manager.profile'),
                'settings' => redirect()->route('cabinet.manager.settings'),
                default => redirect()->route('cabinet.manager.dashboard'),
            };
        }

        if ($user->hasAnyRole(['admin'])) {
            return match ($section) {
                'bookings' => redirect()->route('cabinet.admin.bookings'),
                'settings' => redirect()->route('cabinet.admin.settings'),
                default => redirect()->route('cabinet.admin.dashboard'),
            };
        }

        return null;
    }
    /**
     * Dashboard (главная страница кабинета)
     */
    public function dashboard(): View|RedirectResponse
    {
        $user = Auth::user();
        
        // Определяем роль и перенаправляем на соответствующий dashboard
        if ($user->hasAnyRole(['admin'])) {
            return $this->adminDashboard();
        } elseif ($user->hasAnyRole(['manager'])) {
            return $this->managerDashboard();
        } else {
            return $this->touristDashboard();
        }
    }
    
    /**
     * Dashboard туриста
     */
    protected function touristDashboard(): View
    {
        $user = Auth::user();
        
        // Статистика
        $bookingsCount = Booking::where('user_id', $user->id)->count();
        $activeBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])
            ->count();
        $completedBookings = Booking::where('user_id', $user->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->count();
        
        // Последние заявки
        $latestBookings = Booking::where('user_id', $user->id)
            ->with(['manager'])
            ->latest()
            ->take(3)
            ->get();
        
        // Непрочитанные сообщения
        $unreadMessagesCount = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        // Ближайшая поездка
        $upcomingTrip = Booking::where('user_id', $user->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->where('start_date', '>=', now())
            ->orderBy('start_date')
            ->first();
        
        return view('cabinet.tourist.dashboard', compact(
            'bookingsCount',
            'activeBookings',
            'completedBookings',
            'latestBookings',
            'unreadMessagesCount',
            'upcomingTrip'
        ));
    }
    
    /**
     * Dashboard менеджера
     */
    protected function managerDashboard(): RedirectResponse
    {
        return redirect()->route('cabinet.manager.dashboard');
    }
    
    /**
     * Dashboard админа
     */
    protected function adminDashboard(): RedirectResponse
    {
        return redirect()->route('cabinet.admin.dashboard');
    }
    
    /**
     * Список заявок
     */
    public function bookings(Request $request): View|RedirectResponse
    {
        $user = Auth::user();

        if ($redirect = $this->redirectIfNotTourist('bookings')) {
            return $redirect;
        }
        
        $query = Booking::query();
        
        if ($user->hasAnyRole(['admin', 'manager'])) {
            $query->with(['user', 'manager']);
        } else {
            $query->where('user_id', $user->id)->with(['manager']);
        }
        
        // Фильтры
        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }
        
        if ($request->filled('country')) {
            $query->where('destination_country', 'like', '%' . $request->country . '%');
        }
        
        if ($request->filled('date_from')) {
            $query->whereDate('start_date', '>=', $request->date_from);
        }
        
        $bookings = $query->latest()->paginate(15)->withQueryString();
        
        // Статистика (без учета фильтров)
        $totalCount = Booking::where('user_id', $user->id)->count();
        $activeCount = Booking::where('user_id', $user->id)
            ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])
            ->count();
        $confirmedCount = Booking::where('user_id', $user->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->count();
        $completedCount = Booking::where('user_id', $user->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->count();
        
        return view('cabinet.tourist.bookings.index', compact('bookings', 'totalCount', 'activeCount', 'confirmedCount', 'completedCount'));
    }
    
    /**
     * Чат
     */
    public function chat(Request $request, $bookingId = null): View|RedirectResponse
    {
        $user = $request->user();

        if ($redirect = $this->redirectIfNotTourist('chat', ['bookingId' => $bookingId])) {
            return $redirect;
        }
        
        // Получаем заявки с менеджерами и считаем непрочитанные для каждой
        $bookings = Booking::where('user_id', $user->id)
            ->whereNotNull('manager_id')
            ->with(['manager', 'messages' => function($query) use ($user) {
                $query->where('receiver_id', $user->id)->where('is_read', false);
            }])
            ->get()
            ->map(function($booking) {
                $booking->unread_count = $booking->messages->count();
                return $booking;
            });

        $messages = collect();
        $currentBooking = null;

        if ($bookingId) {
            $currentBooking = Booking::where('user_id', $user->id)
                ->where('id', $bookingId)
                ->with(['manager', 'messages.sender'])
                ->firstOrFail();
            $messages = $currentBooking->messages()->with('sender')->orderBy('created_at', 'asc')->get();

            // Отмечаем сообщения как прочитанные
            Message::where('booking_id', $bookingId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }

        return view('cabinet.tourist.chat.index', compact('bookings', 'messages', 'currentBooking'));
    }
    
    /**
     * Личные документы
     */
    public function personalDocuments(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        $user = Auth::user();
        $type = request('type', 'all');
        
        $query = UserDocument::where('user_id', $user->id);
        
        if ($type !== 'all') {
            $query->where('document_type', $type);
        }
        
        $documents = $query->latest()->get();
        return view('cabinet.tourist.documents.personal', compact('documents'));
    }
    
    /**
     * Документы по заявкам
     */
    public function bookingDocuments(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        $user = Auth::user();
        $bookingsWithDocuments = Booking::where('user_id', $user->id)
            ->whereHas('bookingDocuments')
            ->with('bookingDocuments')
            ->latest()
            ->get();

        return view('cabinet.tourist.documents.bookings', compact('bookingsWithDocuments'));
    }

    /**
     * Защищённая загрузка документа по заявке
     */
    public function downloadBookingDocument(
        Booking $booking,
        BookingDocument $document
    ): StreamedResponse|RedirectResponse {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        if ($booking->user_id !== Auth::id()) {
            abort(403);
        }

        if ($document->booking_id !== $booking->id) {
            abort(404);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $this->bookingDocumentDownloadName($document)
        );
    }

    private function bookingDocumentDownloadName(
        BookingDocument $document
    ): string {
        $downloadName = trim((string) $document->title)
            ?: 'booking-document';

        $downloadName = preg_replace(
            '/[\/\\\\]+/',
            '-',
            $downloadName
        ) ?: 'booking-document';

        $extension = strtolower(
            (string) $document->file_type
        );

        if ($extension !== '') {
            $extension = '.' . ltrim($extension, '.');

            if (
                !str_ends_with(
                    strtolower($downloadName),
                    $extension
                )
            ) {
                $downloadName .= $extension;
            }
        }

        return $downloadName;
    }
    
    /**
     * Бонусная программа
     */
    public function bonusProgram(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        $user = Auth::user();
        $bonusAccount = BonusAccount::firstOrCreate(
            ['user_id' => $user->id],
            [
                'balance' => 0,
                'level' => 'newbie',
                'total_earned' => 0,
                'total_spent' => 0,
                'referral_code' => strtoupper(substr(md5($user->id . time()), 0, 8))
            ]
        );
        $transactions = $bonusAccount->transactions()->latest()->paginate(10);
        $referralsCount = 0; // TODO: реализовать подсчет рефералов
        
        return view('cabinet.tourist.bonus.index', compact('bonusAccount', 'transactions', 'referralsCount'));
    }
    
    /**
     * Избранное
     */
    public function wishlist(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        $user = Auth::user();
        $wishlistItems = collect(); // TODO: реализовать wishlist
        return view('cabinet.tourist.wishlist.index', compact('wishlistItems'));
    }
    
    /**
     * Профиль
     */
    public function profile(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist('profile')) {
            return $redirect;
        }

        return view('cabinet.tourist.profile.edit');
    }
    
    /**
     * Обновление профиля
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist('profile')) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string|max:500',
        ]);
        
        $user = Auth::user();
        $emailChanged = $validated['email'] !== $user->email;

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->birth_date = $validated['birth_date'] ?? null;
        $user->gender = $validated['gender'] ?? null;
        $user->address = $validated['address'] ?? null;

        if ($emailChanged) {
            $user->email_verified_at = null;
        }

        $user->save();

        if ($emailChanged) {
            $user->sendEmailVerificationNotification();
            Auth::logout();
            $request->session()->invalidate();
            $request->session()->regenerateToken();

            return redirect()->route('login')
                ->with('status', 'Email изменен. Подтвердите новый адрес и войдите снова.');
        }

        return redirect()->route('cabinet.profile')->with('status', 'Профиль успешно обновлен!');
    }
    
    /**
     * Настройки
     */
    public function settings(): View|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist('settings')) {
            return $redirect;
        }

        return view('cabinet.tourist.settings.index');
    }
    
    /**
     * Обновление паспортных данных
     */
    public function updatePassport(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist('profile')) {
            return $redirect;
        }

        $validated = $request->validate([
            'passport_number' => 'nullable|string|max:50',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string|max:500',
        ]);
        
        $user = Auth::user();
        $user->passport_number = $validated['passport_number'] ?? null;
        $user->passport_issued_date = $validated['passport_issued_date'] ?? null;
        $user->passport_issued_by = $validated['passport_issued_by'] ?? null;
        $user->save();
        
        return redirect()->route('cabinet.profile')->with('status', 'Паспортные данные обновлены!');
    }
    
    /**
     * Загрузка аватара
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist('profile')) {
            return $redirect;
        }

        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        $user = Auth::user();
        $user->avatar_path = $path;
        $user->save();

        return redirect()->route('cabinet.profile')->with('status', 'Аватар загружен!');
    }
    
    /**
     * Обновление настроек уведомлений
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist('settings')) {
            return $redirect;
        }

        $user = Auth::user();
        
        // Сохраняем настройки в JSON
        $settings = [
            'email_notifications' => $request->has('email_notifications'),
            'booking_updates' => $request->has('booking_updates'),
            'new_messages' => $request->has('new_messages'),
            'trip_reminders' => $request->has('trip_reminders'),
            'promotions' => $request->has('promotions'),
        ];
        
        $user->notification_settings = json_encode($settings);
        $user->save();
        
        return redirect()->route('cabinet.settings')->with('status', 'Настройки уведомлений обновлены!');
    }
    
    /**
     * Загрузка личного документа
     */
    public function uploadPersonalDocument(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'nullable|in:passport,foreign_passport,visa,birth_certificate,other',
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240', // 10MB
        ]);

        $path = null;

        try {
            $file = $request->file('file');

            if (!$file || !$file->isValid()) {
                return redirect()->route('cabinet.documents.personal')
                    ->with('error', 'Ошибка загрузки файла. Попробуйте еще раз.');
            }

            $path = $file->store('documents/personal', 'local');

            if (!$path) {
                return redirect()->route('cabinet.documents.personal')
                    ->with('error', 'Не удалось сохранить файл.');
            }

            UserDocument::create([
                'user_id' => Auth::id(),
                'name' => $validated['name'],
                'document_type' => $validated['document_type'] ?? 'other',
                'file_path' => $path,
                'file_type' => $file->getClientOriginalExtension(),
                'file_size' => $file->getSize(),
            ]);

            return redirect()->route('cabinet.documents.personal')
                ->with('status', 'Документ успешно загружен!');
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            \Log::error('Ошибка загрузки документа: ' . $e->getMessage());

            return redirect()->route('cabinet.documents.personal')
                ->with('error', 'Ошибка загрузки документа. Попробуйте еще раз.');
        }
    }

    /**
     * Защищённая загрузка личного документа
     */
    public function downloadPersonalDocument(UserDocument $document): StreamedResponse|RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        if ($document->user_id !== Auth::id()) {
            abort(403);
        }

        if (!Storage::disk('local')->exists($document->file_path)) {
            abort(404);
        }

        return Storage::disk('local')->download(
            $document->file_path,
            $this->documentDownloadName($document)
        );
    }

    private function documentDownloadName(UserDocument $document): string
    {
        $downloadName = trim((string) $document->name) ?: 'document';
        $downloadName = preg_replace('/[\/\\\\]+/', '-', $downloadName) ?: 'document';

        $extension = strtolower((string) $document->file_type);

        if ($extension !== '') {
            $extension = '.' . ltrim($extension, '.');

            if (!str_ends_with(strtolower($downloadName), $extension)) {
                $downloadName .= $extension;
            }
        }

        return $downloadName;
    }
    
    /**
     * Удаление личного документа
     */
    public function deletePersonalDocument(UserDocument $document): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        // Проверка прав доступа
        if ($document->user_id !== Auth::id()) {
            abort(403);
        }
        
        // Удаление файла
        Storage::disk('local')->delete($document->file_path);
        
        // Удаление записи
        $document->delete();
        
        return redirect()->route('cabinet.documents.personal')->with('status', 'Документ удален!');
    }
    
    /**
     * Удаление аккаунта
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        if ($redirect = $this->redirectIfNotTourist()) {
            return $redirect;
        }

        $request->validate([
            'password' => 'required|current_password',
        ]);
        
        $user = Auth::user();
        
        // Удаление связанных данных
        $user->bookings()->delete();
        $user->userDocuments()->delete();
        $user->bonusAccount()->delete();
        
        // Выход из системы
        Auth::logout();
        
        // Удаление пользователя
        $user->delete();
        
        return redirect()->route('home.index')->with('status', 'Ваш аккаунт успешно удален.');
    }
}
