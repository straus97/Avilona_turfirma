<?php

namespace App\Http\Controllers\Cabinet;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

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
}
