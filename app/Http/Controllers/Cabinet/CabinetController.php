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

class CabinetController extends Controller
{
    /**
     * Dashboard (главная страница кабинета)
     */
    public function dashboard(): View
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
    protected function managerDashboard(): View
    {
        $user = Auth::user();
        
        // Статистика
        $totalBookings = Booking::where('manager_id', $user->id)->count();
        $newBookings = Booking::where('manager_id', $user->id)
            ->where('status', Booking::STATUS_NEW)
            ->count();
        $activeBookings = Booking::where('manager_id', $user->id)
            ->where('status', Booking::STATUS_PROGRESS)
            ->count();
        
        return view('cabinet.manager.dashboard', compact(
            'totalBookings',
            'newBookings',
            'activeBookings'
        ));
    }
    
    /**
     * Dashboard админа
     */
    protected function adminDashboard(): View
    {
        // Общая статистика
        $totalUsers = \App\Models\User::count();
        $totalBookings = Booking::count();
        $newBookings = Booking::where('status', Booking::STATUS_NEW)->count();
        $activeBookings = Booking::where('status', Booking::STATUS_PROGRESS)->count();
        
        return view('cabinet.admin.dashboard', compact(
            'totalUsers',
            'totalBookings',
            'newBookings',
            'activeBookings'
        ));
    }
    
    /**
     * Список заявок
     */
    public function bookings(Request $request): View
    {
        $user = Auth::user();
        
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
    public function chat(Request $request, $bookingId = null): View
    {
        $user = $request->user();
        
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
    public function personalDocuments(): View
    {
        $user = Auth::user();
        $documents = UserDocument::where('user_id', $user->id)->latest()->get();
        return view('cabinet.tourist.documents.personal', compact('documents'));
    }
    
    /**
     * Документы по заявкам
     */
    public function bookingDocuments(): View
    {
        $user = Auth::user();
        $bookingsWithDocuments = Booking::where('user_id', $user->id)
            ->whereHas('bookingDocuments')
            ->with('bookingDocuments')
            ->latest()
            ->get();
        return view('cabinet.tourist.documents.bookings', compact('bookingsWithDocuments'));
    }
    
    /**
     * Бонусная программа
     */
    public function bonusProgram(): View
    {
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
    public function wishlist(): View
    {
        $user = Auth::user();
        $wishlistItems = collect(); // TODO: реализовать wishlist
        return view('cabinet.tourist.wishlist.index', compact('wishlistItems'));
    }
    
    /**
     * Профиль
     */
    public function profile(): View
    {
        return view('cabinet.tourist.profile.edit');
    }
    
    /**
     * Обновление профиля
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'gender' => 'nullable|in:male,female',
            'address' => 'nullable|string|max:500',
        ]);
        
        $user = Auth::user();
        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->birth_date = $validated['birth_date'] ?? null;
        $user->gender = $validated['gender'] ?? null;
        $user->address = $validated['address'] ?? null;
        $user->save();
        
        return redirect()->route('cabinet.profile')->with('status', 'Профиль успешно обновлен!');
    }
    
    /**
     * Настройки
     */
    public function settings(): View
    {
        return view('cabinet.tourist.settings.index');
    }
    
    /**
     * Обновление паспортных данных
     */
    public function updatePassport(Request $request): RedirectResponse
    {
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
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);
        
        // TODO: реализовать загрузку аватара
        return redirect()->route('cabinet.profile')->with('status', 'Аватар загружен!');
    }
    
    /**
     * Обновление настроек уведомлений
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = Auth::user();
        
        // Сохраняем настройки в JSON (можно создать отдельную таблицу notification_settings)
        $settings = [
            'email_notifications' => $request->has('email_notifications'),
            'booking_updates' => $request->has('booking_updates'),
            'new_messages' => $request->has('new_messages'),
            'trip_reminders' => $request->has('trip_reminders'),
            'promotions' => $request->has('promotions'),
        ];
        
        // Сохраняем в поле notification_settings (нужно добавить в миграцию)
        // Пока сохраним временно, позже создадим таблицу
        
        return redirect()->route('cabinet.settings')->with('status', 'Настройки уведомлений обновлены!');
    }
    
    /**
     * Загрузка личного документа
     */
    public function uploadPersonalDocument(Request $request): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'file' => 'required|file|max:10240', // 10MB
        ]);
        
        $file = $request->file('file');
        $path = $file->store('documents/personal', 'public');
        
        UserDocument::create([
            'user_id' => Auth::id(),
            'name' => $request->name,
            'file_path' => $path,
            'file_type' => $file->getClientOriginalExtension(),
            'file_size' => $file->getSize(),
        ]);
        
        return redirect()->route('cabinet.documents.personal')->with('status', 'Документ успешно загружен!');
    }
    
    /**
     * Удаление личного документа
     */
    public function deletePersonalDocument(UserDocument $document): RedirectResponse
    {
        // Проверка прав доступа
        if ($document->user_id !== Auth::id()) {
            abort(403);
        }
        
        // Удаление файла
        Storage::disk('public')->delete($document->file_path);
        
        // Удаление записи
        $document->delete();
        
        return redirect()->route('cabinet.documents.personal')->with('status', 'Документ удален!');
    }
    
    /**
     * Удаление аккаунта
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
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
