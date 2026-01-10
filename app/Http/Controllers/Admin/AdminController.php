<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use App\Models\News;
use App\Models\Review;
use App\Models\Role;
use App\Models\User;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\View\View;

class AdminController extends Controller
{
    /**
     * Показать главную страницу админ панели (Dashboard)
     */
    public function dashboard(Request $request): View
    {
        // Общая статистика
        $totalUsers = User::count();
        $totalBookings = Booking::count();
        $totalRevenue = Booking::where('status', Booking::STATUS_COMPLETED)->sum('total_price');
        $totalMessages = Message::count();
        
        // Статистика пользователей по ролям
        $usersByRole = Role::withCount('users')->get();
        
        // Статистика заявок по статусам
        $bookingsByStatus = [
            'pending' => Booking::whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])->count(),
            'confirmed' => Booking::where('status', Booking::STATUS_CONFIRMED)->count(),
            'completed' => Booking::where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled' => Booking::where('status', Booking::STATUS_CANCELLED)->count(),
        ];
        
        // Статистика контента
        $contentStats = [
            'news' => News::count(),
            'reviews' => Review::count(),
        ];
        
        // Последние пользователи
        $recentUsers = User::with('roles')->latest()->limit(10)->get();
        
        // Последние заявки
        $recentBookings = Booking::with(['user', 'manager'])->latest()->limit(10)->get();
        
        // Заявки без менеджера
        $unassignedBookings = Booking::whereNull('manager_id')->count();
        
        // Менеджеры
        $managers = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->withCount('managedBookings')->get();
        
        return view('admin.dashboard', compact(
            'totalUsers',
            'totalBookings',
            'totalRevenue',
            'totalMessages',
            'usersByRole',
            'bookingsByStatus',
            'contentStats',
            'recentUsers',
            'recentBookings',
            'unassignedBookings',
            'managers'
        ));
    }
    
    /**
     * Показать список пользователей
     */
    public function users(Request $request): View
    {
        $query = User::with('roles');
        
        // Поиск
        if ($request->has('search') && $request->search) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('email', 'like', "%{$search}%")
                    ->orWhere('phone', 'like', "%{$search}%");
            });
        }
        
        // Фильтр по роли
        if ($request->has('role') && $request->role !== 'all') {
            $query->whereHas('roles', function ($q) use ($request) {
                $q->where('name', $request->role);
            });
        }
        
        $users = $query->latest()->paginate(20);
        
        // Все роли для фильтра
        $roles = Role::all();
        
        return view('admin.users', compact('users', 'roles'));
    }
    
    /**
     * Показать страницу управления ролями конкретного пользователя
     */
    public function userRoles(Request $request, $userId): View
    {
        $user = User::with('roles')->findOrFail($userId);
        $allRoles = Role::all();
        
        return view('admin.user-roles', compact('user', 'allRoles'));
    }
    
    /**
     * Назначить роль пользователю
     */
    public function assignRole(Request $request, $userId): RedirectResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,role',
        ]);
        
        $user = User::findOrFail($userId);
        $user->assignRole($request->role);
        
        return back()->with('success', 'Роль успешно назначена');
    }
    
    /**
     * Удалить роль у пользователя
     */
    public function removeRole(Request $request, $userId, $roleId): RedirectResponse
    {
        $user = User::findOrFail($userId);
        $role = Role::findOrFail($roleId);
        
        $user->removeRole($role->role);
        
        return back()->with('success', 'Роль успешно удалена');
    }
    
    /**
     * Показать список всех заявок для назначения менеджеров
     */
    public function bookings(Request $request): View
    {
        $query = Booking::with(['user', 'manager']);
        
        // Фильтр по статусу
        if ($request->has('status') && $request->status !== 'all') {
            $query->where('status', $request->status);
        }
        
        // Фильтр по менеджеру
        if ($request->has('manager') && $request->manager !== 'all') {
            if ($request->manager === 'unassigned') {
                $query->whereNull('manager_id');
            } else {
                $query->where('manager_id', $request->manager);
            }
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
        
        $bookings = $query->latest()->paginate(20);
        
        // Список менеджеров
        $managers = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->get();
        
        // Счетчики
        $statusCounts = [
            'all' => Booking::count(),
            'pending' => Booking::whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])->count(),
            'confirmed' => Booking::where('status', Booking::STATUS_CONFIRMED)->count(),
            'completed' => Booking::where('status', Booking::STATUS_COMPLETED)->count(),
            'cancelled' => Booking::where('status', Booking::STATUS_CANCELLED)->count(),
            'unassigned' => Booking::whereNull('manager_id')->count(),
        ];
        
        return view('admin.bookings', compact('bookings', 'managers', 'statusCounts'));
    }
    
    /**
     * Показать управление контентом
     */
    public function content(Request $request): View
    {
        // Статистика контента
        $newsCount = News::count();
        $reviewsCount = Review::count();
        $pendingReviews = Review::where('status', 'pending')->count();
        
        // Последние новости
        $recentNews = News::latest()->limit(10)->get();
        
        // Последние отзывы
        $recentReviews = Review::with('user')->latest()->limit(10)->get();
        
        return view('admin.content', compact(
            'newsCount',
            'reviewsCount',
            'pendingReviews',
            'recentNews',
            'recentReviews'
        ));
    }
    
    /**
     * Показать системные настройки
     */
    public function settings(Request $request): View
    {
        // Информация о системе
        $systemInfo = [
            'php_version' => phpversion(),
            'laravel_version' => app()->version(),
            'environment' => config('app.env'),
            'debug_mode' => config('app.debug'),
            'cache_driver' => config('cache.default'),
            'session_driver' => config('session.driver'),
            'queue_driver' => config('queue.default'),
        ];
        
        // Статистика кэша
        $cacheStats = [
            'enabled' => config('cache.default') !== 'array',
            'driver' => config('cache.default'),
        ];
        
        return view('admin.settings', compact('systemInfo', 'cacheStats'));
    }
    
    /**
     * Очистить кэш приложения
     */
    public function clearCache(Request $request): RedirectResponse
    {
        Cache::flush();
        
        return back()->with('success', 'Кэш успешно очищен');
    }
    
    /**
     * Удалить пользователя
     */
    public function deleteUser(Request $request, $userId): RedirectResponse
    {
        $user = User::findOrFail($userId);
        
        // Защита от удаления собственного аккаунта
        if ($user->id === $request->user()->id) {
            return back()->with('error', 'Вы не можете удалить свой собственный аккаунт');
        }
        
        // Удаление пользователя (каскадное удаление заявок и сообщений через модель)
        $user->delete();
        
        return back()->with('success', 'Пользователь успешно удален');
    }
}
