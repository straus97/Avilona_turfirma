<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Article;
use App\Models\BonusAccount;
use App\Models\BonusTransaction;
use App\Models\Reviews;
use App\Models\Role;
use App\Models\User;
use App\Models\UserDocument;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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
            'articles' => Article::count(),
            'reviews' => Reviews::count(),
        ];
        
        // Последние пользователи
        $recentUsers = User::with('roles')->latest()->limit(10)->get();
        
        // Последние заявки
        $recentBookings = Booking::with(['user', 'manager'])->latest()->limit(10)->get();
        
        // Заявки без менеджера
        $unassignedBookings = Booking::whereNull('manager_id')->count();
        
        // Новые и в работе заявки
        $pendingBookings = Booking::whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])->count();
        
        // Менеджеры
        $managers = User::whereHas('roles', function ($query) {
            $query->where('name', 'manager');
        })->withCount('managedBookings')->get();
        
        return view('admin.dashboard', compact(
            'totalUsers',
            'totalBookings',
            'pendingBookings',
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
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($userId);
        $role = Role::where('name', $request->role)->firstOrFail();

        $actorId = $request->user()->id;
        $targetUserId = $user->id;

        $changes = $user->roles()->syncWithoutDetaching([$role->id]);

        if (!empty($changes['attached'])) {
            Log::warning('Admin assigned user role', [
                'actor_id' => $actorId,
                'target_user_id' => $targetUserId,
                'assigned_role' => $role->name,
            ]);
        }

        return back()->with('success', 'Роль успешно назначена');
    }
    
    /**
     * Удалить роль у пользователя
     */
    public function removeRole(Request $request, $userId, $roleId): RedirectResponse
    {
        $user = User::findOrFail($userId);
        $role = Role::findOrFail($roleId);

        $actorId = $request->user()->id;
        $targetUserId = $user->id;
        $removedRole = $role->name;

        $detachCount = $user->roles()->detach($role->id);

        if ($detachCount > 0) {
            Log::warning('Admin removed user role', [
                'actor_id' => $actorId,
                'target_user_id' => $targetUserId,
                'removed_role' => $removedRole,
            ]);
        }

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
                    ->orWhere('destination_country', 'like', "%{$search}%")
                    ->orWhere('destination_city', 'like', "%{$search}%")
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
        $articlesCount = Article::count();
        $reviewsCount = Reviews::count();
        $pendingReviews = Reviews::where('is_published', false)->count();
        
        // Последние статьи
        $recentArticles = Article::latest()->limit(10)->get();
        
        // Последние отзывы
        $recentReviews = Reviews::latest()->limit(10)->get();
        
        return view('admin.content', compact(
            'articlesCount',
            'reviewsCount',
            'pendingReviews',
            'recentArticles',
            'recentReviews'
        ));
    }

    /**
     * Список статей (интересные статьи)
     */
    public function articles(Request $request): View
    {
        $articles = Article::latest()->paginate(20);

        return view('admin.articles.index', compact('articles'));
    }

    /**
     * Создание статьи
     */
    public function createArticle(Request $request): View
    {
        return view('admin.articles.create');
    }

    /**
     * Сохранение статьи
     */
    public function storeArticle(Request $request): RedirectResponse
    {
        $input = $request->all();
        $rawSlug = trim((string) ($input['slug'] ?? ''));
        $input['slug'] = $rawSlug !== '' ? $rawSlug : Str::slug((string) ($input['title'] ?? ''));

        $validated = validator($input, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|url',
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')],
        ])->validate();

        Article::create($validated);

        return redirect()->route('cabinet.admin.articles')
            ->with('success', 'Статья создана');
    }

    /**
     * Редактирование статьи
     */
    public function editArticle(Request $request, Article $article): View
    {
        return view('admin.articles.edit', compact('article'));
    }

    /**
     * Обновление статьи
     */
    public function updateArticle(Request $request, Article $article): RedirectResponse
    {
        $input = $request->all();
        $rawSlug = trim((string) ($input['slug'] ?? ''));
        $input['slug'] = $rawSlug !== '' ? $rawSlug : Str::slug((string) ($input['title'] ?? ''));

        $validated = validator($input, [
            'title' => 'required|string|max:255',
            'content' => 'required|string',
            'image' => 'nullable|url',
            'slug' => ['required', 'string', 'max:255', Rule::unique('articles', 'slug')->ignore($article->id)],
        ])->validate();

        $article->update($validated);

        return redirect()->route('cabinet.admin.articles')
            ->with('success', 'Статья обновлена');
    }

    /**
     * Удаление статьи
     */
    public function deleteArticle(Request $request, Article $article): RedirectResponse
    {
        $article->delete();

        return back()->with('success', 'Статья удалена');
    }

    /**
     * Редактирование отзыва
     */
    public function editReview(Request $request, Reviews $review): View
    {
        return view('admin.reviews.edit', compact('review'));
    }

    /**
     * Обновление отзыва
     */
    public function updateReview(Request $request, Reviews $review): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'title' => 'nullable|string|max:255',
            'content' => 'required|string',
            'gender' => 'nullable|in:boy,girl',
            'is_published' => 'nullable|boolean',
        ]);

        $validated['is_published'] = $request->boolean('is_published');

        $gender = $validated['gender'] ?? null;
        if (!$gender) {
            $gender = str_contains((string) $review->image, 'user-girl') ? 'girl' : 'boy';
        }

        $validated['image'] = $gender === 'girl'
            ? url('/img/user-girl.png')
            : url('/img/user-boy.png');

        $validated['title'] = $validated['title'] ? trim($validated['title']) : '';

        unset($validated['gender']);

        $review->update($validated);

        Cache::forget('home_reviews');
        for ($page = 1; $page <= 10; $page++) {
            Cache::forget('reviews_page_' . $page);
        }

        return redirect()->route('cabinet.admin.content')
            ->with('success', 'Отзыв обновлен');
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
     * Обновление уведомлений администратора
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = $request->user();

        $settings = [
            'email_notifications' => $request->has('email_notifications'),
            'booking_updates' => $request->has('booking_updates'),
            'new_messages' => $request->has('new_messages'),
        ];

        $user->notification_settings = json_encode($settings);
        $user->save();

        return redirect()->route('cabinet.admin.settings')->with('success', 'Настройки уведомлений обновлены.');
    }

    /**
     * Смена пароля администратора
     */
    public function updatePassword(Request $request): RedirectResponse
    {
        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Введите текущий пароль',
            'current_password.current_password' => 'Текущий пароль неверен',
            'password.required' => 'Введите новый пароль',
            'password.confirmed' => 'Пароли не совпадают',
        ]);

        $user = $request->user();
        $user->password = Hash::make($request->password);
        $user->password_change_required = false;
        $user->temp_password = null;
        $user->save();

        return redirect()->route('cabinet.admin.settings')->with('success', 'Пароль успешно изменен.');
    }

    /**
     * Профиль администратора
     */
    public function profile(Request $request): View
    {
        return view('admin.profile');
    }

    /**
     * Обновление профиля администратора
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . $request->user()->id,
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'passport_number' => 'nullable|string|max:50',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string|max:500',
        ]);

        $user = $request->user();
        $emailChanged = $validated['email'] !== $user->email;

        $user->name = $validated['name'];
        $user->email = $validated['email'];
        $user->phone = $validated['phone'] ?? null;
        $user->birth_date = $validated['birth_date'] ?? null;
        $user->passport_number = $validated['passport_number'] ?? null;
        $user->passport_issued_date = $validated['passport_issued_date'] ?? null;
        $user->passport_issued_by = $validated['passport_issued_by'] ?? null;

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

        return redirect()->route('cabinet.admin.profile')->with('status', 'Профиль обновлен.');
    }

    /**
     * Загрузка аватара администратора
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        $user = $request->user();
        $user->avatar_path = $path;
        $user->save();

        return redirect()->route('cabinet.admin.profile')->with('status', 'Аватар загружен.');
    }

    /**
     * Удаление аккаунта администратора
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = $request->user();
        $actorId = $user->id;

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $deleted = $user->delete();

        if ($deleted === true) {
            Log::warning('Admin deleted own account', [
                'actor_id' => $actorId,
            ]);
        }

        return redirect()->route('home.index')->with('status', 'Аккаунт удален.');
    }

    /**
     * Карточка пользователя
     */
    public function userShow(Request $request, $userId): View
    {
        $user = User::with(['roles', 'userDocuments', 'bookings'])->findOrFail($userId);
        $roles = Role::all();

        return view('admin.user-show', compact('user', 'roles'));
    }

    /**
     * Защищённая загрузка личного документа пользователя
     */
    public function downloadUserDocument(
        User $user,
        UserDocument $document
    ): StreamedResponse {
        if ($document->user_id !== $user->id) {
            abort(404);
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
     * Все чаты по заявкам
     */
    public function chats(Request $request, $bookingId = null): View
    {
        $bookings = Booking::with(['user', 'manager'])
            ->latest()
            ->get();

        $currentBooking = null;
        $messages = collect();

        if ($bookingId) {
            $currentBooking = Booking::with(['user', 'manager', 'messages.sender'])
                ->where('id', $bookingId)
                ->firstOrFail();

            $messages = $currentBooking->messages()
                ->with(['sender', 'receiver'])
                ->orderBy('created_at', 'asc')
                ->get();
        }

        $unreadByBooking = [];
        foreach ($bookings as $booking) {
            $managerUnread = 0;
            $touristUnread = 0;

            if ($booking->manager_id) {
                $managerUnread = Message::where('booking_id', $booking->id)
                    ->where('receiver_id', $booking->manager_id)
                    ->where('is_read', false)
                    ->count();
            }

            $touristUnread = Message::where('booking_id', $booking->id)
                ->where('receiver_id', $booking->user_id)
                ->where('is_read', false)
                ->count();

            $unreadByBooking[$booking->id] = [
                'manager' => $managerUnread,
                'tourist' => $touristUnread,
            ];
        }

        return view('admin.chats', compact('bookings', 'currentBooking', 'messages', 'unreadByBooking'));
    }

    /**
     * Быстрая смена роли пользователя (одна основная роль)
     */
    public function updateUserRole(Request $request, $userId): RedirectResponse
    {
        $request->validate([
            'role' => 'required|exists:roles,name',
        ]);

        $user = User::findOrFail($userId);
        $role = Role::where('name', $request->role)->firstOrFail();

        $actorId = $request->user()->id;
        $targetUserId = $user->id;

        $changes = $user->roles()->sync([$role->id]);

        if (!empty($changes['attached']) || !empty($changes['detached'])) {
            $addedRoles = !empty($changes['attached']) ? [$role->name] : [];

            $removedRoles = empty($changes['detached'])
                ? []
                : Role::query()
                    ->whereIn('id', $changes['detached'])
                    ->orderBy('name')
                    ->pluck('name')
                    ->all();

            Log::warning('Admin updated user role', [
                'actor_id' => $actorId,
                'target_user_id' => $targetUserId,
                'added_roles' => $addedRoles,
                'removed_roles' => $removedRoles,
                'resulting_roles' => [$role->name],
            ]);
        }

        return back()->with('success', 'Роль пользователя обновлена');
    }

    /**
     * Роли и права (раздел объединен с пользователями)
     */
    public function roles(Request $request): RedirectResponse
    {
        return redirect()->route('cabinet.admin.users');
    }

    /**
     * Финансы
     */
    public function finance(): View
    {
        $completedRevenue = Booking::where('status', Booking::STATUS_COMPLETED)->sum('total_price');
        $totalPaid = Booking::sum('paid_amount');
        $totalOutstanding = Booking::sum('total_price') - $totalPaid;

        $monthlyStats = Booking::selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count, SUM(total_price) as revenue, SUM(paid_amount) as paid")
            ->groupBy('month')
            ->orderBy('month', 'desc')
            ->limit(6)
            ->get()
            ->reverse();

        $managerStats = User::whereHas('roles', function ($q) {
            $q->where('name', 'manager');
        })->withCount(['managedBookings as completed_bookings_count' => function ($q) {
            $q->where('status', Booking::STATUS_COMPLETED);
        }])->get()->map(function ($manager) {
            $manager->completed_revenue = Booking::where('manager_id', $manager->id)
                ->where('status', Booking::STATUS_COMPLETED)
                ->sum('total_price');
            return $manager;
        });

        $recentBookings = Booking::with(['user', 'manager'])
            ->latest()
            ->limit(10)
            ->get();

        return view('admin.finance', compact(
            'completedRevenue',
            'totalPaid',
            'totalOutstanding',
            'monthlyStats',
            'managerStats',
            'recentBookings'
        ));
    }

    /**
     * Бонусная программа
     */
    public function bonus(): View
    {
        $accounts = BonusAccount::with('user')
            ->latest()
            ->paginate(20);

        $transactions = BonusTransaction::with(['bonusAccount.user', 'booking'])
            ->latest()
            ->limit(20)
            ->get();

        $totalBalance = BonusAccount::sum('balance');
        $totalEarned = BonusAccount::sum('total_earned');
        $totalSpent = BonusAccount::sum('total_spent');

        return view('admin.bonus', compact('accounts', 'transactions', 'totalBalance', 'totalEarned', 'totalSpent'));
    }

    /**
     * Логи
     */
    public function logs(): View
    {
        $path = storage_path('logs/laravel.log');
        $lines = [];
        $maxLines = 200;
        $tailBytes = 512 * 1024; // 512 KB

        if (file_exists($path)) {
            try {
                $size = filesize($path);
                $start = max(0, $size - $tailBytes);

                $handle = fopen($path, 'rb');
                if ($handle) {
                    fseek($handle, $start);
                    $chunk = stream_get_contents($handle);
                    fclose($handle);

                    $allLines = preg_split("/\r\n|\r|\n/", (string) $chunk);
                    if ($start > 0 && isset($allLines[0])) {
                        array_shift($allLines);
                    }
                    $lines = array_slice($allLines, -$maxLines);
                }
            } catch (\Throwable $e) {
                $lines = ["Не удалось прочитать лог: " . $e->getMessage()];
            }
        }

        return view('admin.logs', compact('lines', 'path'));
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

        $actorId = $request->user()->id;
        $targetUserId = $user->id;
        $targetUserRoles = $user->roles()
            ->orderBy('name')
            ->pluck('name')
            ->all();

        // Удаление пользователя (каскадное удаление заявок и сообщений через модель)
        $user->delete();

        Log::warning('Admin deleted user account', [
            'actor_id' => $actorId,
            'target_user_id' => $targetUserId,
            'target_user_roles' => $targetUserRoles,
        ]);

        return back()->with('success', 'Пользователь успешно удален');
    }
}
