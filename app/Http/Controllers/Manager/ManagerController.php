<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\View\View;

class ManagerController extends Controller
{
    /**
     * Показать главную страницу панели менеджера (Dashboard)
     */
    public function dashboard(Request $request): View
    {
        $manager = $request->user();
        
        // Статистика для менеджера
        $assignedBookings = Booking::where('manager_id', $manager->id)->count();
        $pendingBookings = Booking::where('manager_id', $manager->id)
            ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])
            ->count();
        $confirmedBookings = Booking::where('manager_id', $manager->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->count();
        $completedBookings = Booking::where('manager_id', $manager->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->count();
        
        // Непрочитанные сообщения
        $unreadMessages = Message::where('receiver_id', $manager->id)
            ->where('is_read', false)
            ->count();
        
        // Последние заявки
        $recentBookings = Booking::where('manager_id', $manager->id)
            ->with(['user', 'tour'])
            ->latest()
            ->limit(10)
            ->get();
        
        // Клиенты
        $totalClients = Booking::where('manager_id', $manager->id)
            ->distinct('user_id')
            ->count('user_id');
        
        // Общее количество заявок
        $totalBookings = $assignedBookings;
        
        return view('manager.dashboard', compact(
            'manager',
            'assignedBookings',
            'pendingBookings',
            'confirmedBookings',
            'completedBookings',
            'unreadMessages',
            'recentBookings',
            'totalClients',
            'totalBookings'
        ));
    }
    
    /**
     * Показать список клиентов менеджера
     */
    public function clients(Request $request): View
    {
        $manager = $request->user();
        
        // Получаем уникальных клиентов через заявки
        $clientIds = Booking::where('manager_id', $manager->id)
            ->distinct()
            ->pluck('user_id');
        
        $clients = User::whereIn('id', $clientIds)
            ->withCount([
                'bookings' => function ($query) use ($manager) {
                    $query->where('manager_id', $manager->id);
                }
            ])
            ->paginate(20);
        
        // Для каждого клиента получаем информацию о заявках
        foreach ($clients as $client) {
            $client->active_bookings = Booking::where('user_id', $client->id)
                ->where('manager_id', $manager->id)
                ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS, Booking::STATUS_CONFIRMED])
                ->count();
            
            $client->latest_booking = Booking::where('user_id', $client->id)
                ->where('manager_id', $manager->id)
                ->latest()
                ->first();
        }
        
        return view('manager.clients', compact('clients', 'manager'));
    }
    
    /**
     * Показать список заявок менеджера
     */
    public function bookings(Request $request): View
    {
        $manager = $request->user();
        
        $query = Booking::where('manager_id', $manager->id)
            ->with(['user', 'tour']);
        
        // Фильтрация по статусу
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Поиск
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('tour_name', 'like', "%{$search}%")
                    ->orWhere('destination', 'like', "%{$search}%")
                    ->orWhereHas('user', function ($userQuery) use ($search) {
                        $userQuery->where('name', 'like', "%{$search}%")
                            ->orWhere('email', 'like', "%{$search}%");
                    });
            });
        }
        
        $bookings = $query->latest()->paginate(15);
        
        // Статистика для фильтров
        $statusCounts = [
            'all' => Booking::where('manager_id', $manager->id)->count(),
            'pending' => Booking::where('manager_id', $manager->id)->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])->count(),
            'confirmed' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_CONFIRMED)->count(),
            'completed' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_CANCELLED)->count(),
        ];
        
        return view('manager.bookings', compact('bookings', 'manager', 'statusCounts'));
    }
    
    /**
     * Показать чат с клиентами
     */
    public function chat(Request $request, $bookingId = null): View
    {
        $manager = $request->user();
        
        // Получаем все заявки менеджера
        $bookings = Booking::where('manager_id', $manager->id)
            ->with('user')
            ->get();
        
        // Если указана заявка, получаем сообщения по ней
        $messages = collect();
        $currentBooking = null;
        
        if ($bookingId) {
            $currentBooking = Booking::where('manager_id', $manager->id)
                ->where('id', $bookingId)
                ->with(['user', 'messages.sender'])
                ->firstOrFail();
            
            $messages = $currentBooking->messages()->with('sender')->latest()->get();
            
            // Отметить сообщения как прочитанные
            Message::where('booking_id', $bookingId)
                ->where('receiver_id', $manager->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }
        
        return view('manager.chat', compact('bookings', 'messages', 'currentBooking', 'manager'));
    }
    
    /**
     * Показать статистику работы менеджера
     */
    public function statistics(Request $request): View
    {
        $manager = $request->user();
        
        // Общая статистика
        $totalBookings = Booking::where('manager_id', $manager->id)->count();
        $totalRevenue = Booking::where('manager_id', $manager->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->sum('total_price');
        
        // Статистика по статусам
        $statusStats = [
            'pending' => Booking::where('manager_id', $manager->id)->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])->count(),
            'confirmed' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_CONFIRMED)->count(),
            'completed' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_CANCELLED)->count(),
        ];
        
        // Статистика по месяцам (текущий год)
        $monthlyStats = Booking::where('manager_id', $manager->id)
            ->whereYear('created_at', date('Y'))
            ->selectRaw('MONTH(created_at) as month, COUNT(*) as count, SUM(total_price) as revenue')
            ->groupBy('month')
            ->get()
            ->keyBy('month');
        
        // Заполняем пустые месяцы
        $monthlyData = [];
        for ($i = 1; $i <= 12; $i++) {
            $monthlyData[$i] = [
                'count' => $monthlyStats->has($i) ? $monthlyStats[$i]->count : 0,
                'revenue' => $monthlyStats->has($i) ? $monthlyStats[$i]->revenue : 0,
            ];
        }
        
        // Топ направлений
        $topDestinations = Booking::where('manager_id', $manager->id)
            ->selectRaw('destination, COUNT(*) as count')
            ->whereNotNull('destination')
            ->groupBy('destination')
            ->orderByDesc('count')
            ->limit(10)
            ->get();
        
        // Последние активности
        $recentActivities = Booking::where('manager_id', $manager->id)
            ->with('user')
            ->latest('updated_at')
            ->limit(15)
            ->get();
        
        return view('manager.statistics', compact(
            'manager',
            'totalBookings',
            'totalRevenue',
            'statusStats',
            'monthlyData',
            'topDestinations',
            'recentActivities'
        ));
    }
}
