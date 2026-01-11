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
    public function bookings(): View
    {
        $user = Auth::user();
        
        if ($user->hasAnyRole(['admin', 'manager'])) {
            $bookings = Booking::with(['user', 'manager'])
                ->latest()
                ->paginate(15);
        } else {
            $bookings = Booking::where('user_id', $user->id)
                ->with(['manager'])
                ->latest()
                ->paginate(15);
        }
        
        return view('cabinet.tourist.bookings.index', compact('bookings'));
    }
    
    /**
     * Чат
     */
    public function chat(Request $request, $bookingId = null): View
    {
        $user = $request->user();
        $bookings = Booking::where('user_id', $user->id)
            ->whereNotNull('manager_id')
            ->with('manager')
            ->get();

        $messages = collect();
        $currentBooking = null;

        if ($bookingId) {
            $currentBooking = Booking::where('user_id', $user->id)
                ->where('id', $bookingId)
                ->with(['manager', 'messages.sender'])
                ->firstOrFail();
            $messages = $currentBooking->messages()->with('sender')->latest()->get();

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
        $bookings = Booking::where('user_id', $user->id)
            ->whereHas('bookingDocuments')
            ->with('bookingDocuments')
            ->latest()
            ->get();
        return view('cabinet.tourist.documents.bookings', compact('bookings'));
    }
    
    /**
     * Бонусная программа
     */
    public function bonusProgram(): View
    {
        $user = Auth::user();
        $bonusAccount = BonusAccount::firstOrCreate(['user_id' => $user->id]);
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
        
        Auth::user()->update($validated);
        
        return redirect()->route('cabinet.profile')->with('status', 'Профиль успешно обновлен!');
    }
    
    /**
     * Настройки
     */
    public function settings(): View
    {
        return view('cabinet.tourist.settings.index');
    }
}
