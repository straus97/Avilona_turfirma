<?php

namespace App\Http\Controllers\Manager;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Message;
use App\Models\Article;
use App\Models\Reviews;
use App\Models\UserDocument;
use App\Models\User;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Данные для графика по статусам
        $chartLabels = ['Новые', 'В работе', 'Подтверждены', 'Отменены', 'Завершены'];
        $chartData = [
            Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_NEW)->count(),
            Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_PROGRESS)->count(),
            Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_CONFIRMED)->count(),
            Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_CANCELLED)->count(),
            Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_COMPLETED)->count(),
        ];

        // Данные для графика динамики (последние 6 месяцев)
        $startMonth = Carbon::now()->subMonths(5)->startOfMonth();
        $monthlyBookings = Booking::where('manager_id', $manager->id)
            ->where('created_at', '>=', $startMonth)
            ->selectRaw("DATE_FORMAT(created_at, '%Y-%m') as month, COUNT(*) as count")
            ->groupBy('month')
            ->orderBy('month')
            ->get()
            ->keyBy('month');

        $bookingsChartLabels = [];
        $bookingsChartData = [];
        for ($i = 5; $i >= 0; $i--) {
            $month = Carbon::now()->subMonths($i);
            $key = $month->format('Y-m');
            $bookingsChartLabels[] = $month->format('m.Y');
            $bookingsChartData[] = $monthlyBookings->has($key) ? $monthlyBookings[$key]->count : 0;
        }
        
        return view('manager.dashboard', compact(
            'manager',
            'assignedBookings',
            'pendingBookings',
            'confirmedBookings',
            'completedBookings',
            'unreadMessages',
            'recentBookings',
            'totalClients',
            'totalBookings',
            'chartLabels',
            'chartData',
            'bookingsChartLabels',
            'bookingsChartData'
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
            'new' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_NEW)->count(),
            'progress' => Booking::where('manager_id', $manager->id)->where('status', Booking::STATUS_PROGRESS)->count(),
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
            
            $messages = $currentBooking->messages()->with('sender')->orderBy('created_at', 'asc')->get();
            
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
            ->selectRaw('destination_country, destination_city, COUNT(*) as count')
            ->whereNotNull('destination_country')
            ->groupBy('destination_country', 'destination_city')
            ->orderByDesc('count')
            ->limit(10)
            ->get()
            ->map(function ($item) {
                $item->destination = $item->destination_country;
                if (!empty($item->destination_city)) {
                    $item->destination .= ' / ' . $item->destination_city;
                }
                return $item;
            });
        
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

    /**
     * Профиль менеджера
     */
    public function profile(): View
    {
        return view('manager.profile');
    }

    /**
     * Настройки менеджера
     */
    public function settings(): View
    {
        return view('manager.settings');
    }

    /**
     * Обновление уведомлений менеджера
     */
    public function updateNotifications(Request $request): RedirectResponse
    {
        $user = Auth::user();

        $settings = [
            'email_notifications' => $request->has('email_notifications'),
            'booking_updates' => $request->has('booking_updates'),
            'new_messages' => $request->has('new_messages'),
        ];

        $user->notification_settings = json_encode($settings);
        $user->save();

        return redirect()->route('cabinet.manager.settings')->with('status', 'Настройки уведомлений обновлены.');
    }

    /**
     * Обновление профиля менеджера
     */
    public function updateProfile(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'email' => 'required|email|max:255|unique:users,email,' . Auth::id(),
            'phone' => 'nullable|string|max:20',
            'birth_date' => 'nullable|date',
            'passport_number' => 'nullable|string|max:50',
            'passport_issued_date' => 'nullable|date',
            'passport_issued_by' => 'nullable|string|max:500',
        ]);

        $user = Auth::user();
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

        return redirect()->route('cabinet.manager.profile')->with('status', 'Профиль обновлен.');
    }

    /**
     * Смена пароля менеджера
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

        $user = Auth::user();
        $user->password = Hash::make($request->password);
        $user->password_change_required = false;
        $user->temp_password = null;
        $user->save();

        return redirect()->route('cabinet.manager.settings')->with('status', 'Пароль успешно изменен.');
    }

    /**
     * Загрузка аватара менеджера
     */
    public function uploadAvatar(Request $request): RedirectResponse
    {
        $request->validate([
            'avatar' => 'required|image|max:2048',
        ]);

        $file = $request->file('avatar');
        $path = $file->store('avatars', 'public');

        $user = Auth::user();
        $user->avatar_path = $path;
        $user->save();

        return redirect()->route('cabinet.manager.profile')->with('status', 'Аватар загружен.');
    }

    /**
     * Документы менеджера
     */
    public function documents(): View
    {
        $user = Auth::user();
        $documents = UserDocument::where('user_id', $user->id)->latest()->get();
        return view('manager.documents', compact('documents'));
    }

    /**
     * Загрузка документа менеджера
     */
    public function uploadDocument(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'document_type' => 'nullable|in:passport,foreign_passport,visa,birth_certificate,other',
            'file' => 'required|file|mimes:pdf,doc,docx,jpg,jpeg,png|max:10240',
        ]);

        $path = null;

        try {
            $file = $request->file('file');

            if (!$file || !$file->isValid()) {
                return redirect()->route('cabinet.manager.documents')
                    ->with('error', 'Ошибка загрузки файла. Попробуйте еще раз.');
            }

            $path = $file->store('documents/personal', 'local');

            if (!$path) {
                return redirect()->route('cabinet.manager.documents')
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

            return redirect()->route('cabinet.manager.documents')
                ->with('status', 'Документ загружен.');
        } catch (\Throwable $e) {
            if ($path) {
                Storage::disk('local')->delete($path);
            }

            \Log::error('Ошибка загрузки документа менеджера: ' . $e->getMessage());

            return redirect()->route('cabinet.manager.documents')
                ->with('error', 'Ошибка загрузки документа. Попробуйте еще раз.');
        }
    }

    /**
     * Защищённая загрузка документа менеджера
     */
    public function downloadDocument(UserDocument $document): StreamedResponse
    {
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
     * Удаление документа менеджера
     */
    public function deleteDocument(UserDocument $document): RedirectResponse
    {
        if ($document->user_id !== Auth::id()) {
            abort(403);
        }

        Storage::disk('local')->delete($document->file_path);
        $document->delete();

        return redirect()->route('cabinet.manager.documents')->with('status', 'Документ удален.');
    }

    /**
     * Удаление аккаунта менеджера
     */
    public function destroyAccount(Request $request): RedirectResponse
    {
        $request->validate([
            'password' => 'required|current_password',
        ]);

        $user = Auth::user();

        Booking::where('manager_id', $user->id)->update(['manager_id' => null]);
        $documents = UserDocument::where('user_id', $user->id)->get();
        foreach ($documents as $document) {
            Storage::disk('local')->delete($document->file_path);
            $document->delete();
        }

        Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        $user->delete();

        return redirect()->route('home.index')->with('status', 'Аккаунт удален.');
    }

    /**
     * Раздел "Мои комиссии"
     */
    public function finance(): View
    {
        $manager = Auth::user();

        $completedRevenue = Booking::where('manager_id', $manager->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->sum('total_price');

        $totalPaid = Booking::where('manager_id', $manager->id)->sum('paid_amount');
        $totalOutstanding = Booking::where('manager_id', $manager->id)->sum('total_price') - $totalPaid;

        $recentBookings = Booking::where('manager_id', $manager->id)
            ->with('user')
            ->latest()
            ->limit(10)
            ->get();

        return view('manager.finance', compact('completedRevenue', 'totalPaid', 'totalOutstanding', 'recentBookings'));
    }

    /**
     * Раздел "База знаний"
     */
    public function knowledge(): View
    {
        return $this->content();
    }

    /**
     * Контент (статьи + отзывы)
     */
    public function content(): View
    {
        $articlesCount = Article::count();
        $reviewsCount = Reviews::count();
        $pendingReviews = Reviews::where('is_published', false)->count();

        $recentArticles = Article::latest()->limit(10)->get();
        $recentReviews = Reviews::latest()->limit(10)->get();

        return view('manager.content', compact(
            'articlesCount',
            'reviewsCount',
            'pendingReviews',
            'recentArticles',
            'recentReviews'
        ));
    }

    /**
     * Список статей
     */
    public function articles(): View
    {
        $articles = Article::latest()->paginate(20);
        return view('manager.articles.index', compact('articles'));
    }

    public function createArticle(): View
    {
        return view('manager.articles.create');
    }

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

        return redirect()->route('cabinet.manager.articles')
            ->with('success', 'Статья создана');
    }

    public function editArticle(Article $article): View
    {
        return view('manager.articles.edit', compact('article'));
    }

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

        return redirect()->route('cabinet.manager.articles')
            ->with('success', 'Статья обновлена');
    }

    public function deleteArticle(Article $article): RedirectResponse
    {
        $article->delete();
        return back()->with('success', 'Статья удалена');
    }

    public function editReview(Reviews $review): View
    {
        return view('manager.reviews.edit', compact('review'));
    }

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

        return redirect()->route('cabinet.manager.content')
            ->with('success', 'Отзыв обновлен');
    }
}
