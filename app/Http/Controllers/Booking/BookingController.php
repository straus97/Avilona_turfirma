<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

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
        
        // Получаем уникальные страны назначения
        $destinationCountries = Tour::select('destination_country')
            ->distinct()
            ->orderBy('destination_country')
            ->pluck('destination_country');
        
        // Получаем уникальные города/курорты назначения
        $destinationCities = Tour::select('destination_city')
            ->whereNotNull('destination_city')
            ->distinct()
            ->orderBy('destination_city')
            ->pluck('destination_city');
        
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
        
        return view('bookings.create', compact('tour', 'departureCities', 'destinationCountries', 'destinationCities', 'clients'));
    }

    /**
     * Сохранение заявки
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'tour_id' => 'nullable|exists:tours,id',
            'departure_city' => 'required|string|max:255',
            'destination_country' => 'required|string|max:255',
            'destination_city' => 'nullable|string|max:255',
            'start_date' => 'required|date|after:today',
            'nights' => 'required|integer|min:1|max:30',
            'adults' => 'required|integer|min:1|max:10',
            'children' => 'integer|min:0|max:10',
            'tourists_data' => 'nullable|array',
            'notes' => 'nullable|string',
        ]);

        $validated['user_id'] = Auth::id();
        $validated['status'] = Booking::STATUS_NEW;

        // Если указан тур, берем цену из него
        if ($request->tour_id) {
            $tour = Tour::find($request->tour_id);
            if ($tour) {
                $validated['total_price'] = $tour->price * ($validated['adults'] + ($validated['children'] ?? 0));
            }
        }

        $booking = Booking::create($validated);

        return redirect()->route('bookings.show', $booking)
            ->with('success', 'Заявка успешно создана! Наш менеджер свяжется с вами в ближайшее время.');
    }

    /**
     * Просмотр заявки
     */
    public function show(Booking $booking)
    {
        $this->authorizeBooking($booking);
        
        $booking->load(['user', 'tour', 'manager', 'messages.sender', 'messages.receiver']);
        
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
        
        $booking->assignManager($request->manager_id);
        
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
