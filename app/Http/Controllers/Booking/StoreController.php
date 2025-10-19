<?php

namespace App\Http\Controllers\Booking;

use App\Http\Controllers\Controller;
use App\Models\Booking;
use App\Models\Tour;
use App\Models\User;
use App\Models\Role;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class StoreController extends Controller
{
    /**
     * Создание заявки на тур
     */
    public function __invoke(Request $request)
    {
        // Валидация
        $validated = $request->validate([
            'tour_id' => 'required|exists:tours,id',
            'name' => 'required_without:user_id|string|max:255',
            'email' => 'required_without:user_id|email|max:255',
            'phone' => 'required_without:user_id|string|max:20',
            'notes' => 'nullable|string|max:1000',
        ]);
        
        // Получаем тур
        $tour = Tour::findOrFail($validated['tour_id']);
        
        // Определяем пользователя
        if (auth()->check()) {
            // Авторизованный пользователь
            $user = auth()->user();
        } else {
            // Неавторизованный - создаем или находим пользователя
            $user = User::firstOrCreate(
                ['email' => $validated['email']],
                [
                    'name' => $validated['name'],
                    'phone' => $validated['phone'],
                    'password' => Hash::make(rand(100000, 999999)), // Временный пароль
                    'is_active' => true,
                ]
            );
            
            // Назначаем роль турист, если ее нет
            if (!$user->hasRole(Role::TOURIST)) {
                $user->assignRole(Role::TOURIST);
            }
        }
        
        // Создаем заявку
        $booking = Booking::create([
            'user_id' => $user->id,
            'tour_id' => $tour->id,
            'status' => Booking::STATUS_NEW,
            'departure_city' => $tour->departure_city,
            'destination_country' => $tour->destination_country,
            'destination_city' => $tour->destination_city,
            'start_date' => $tour->start_date,
            'nights' => $tour->nights,
            'adults' => 2, // По умолчанию 2 взрослых (можно расширить форму)
            'children' => 0,
            'total_price' => $tour->price * 2,
            'notes' => $validated['notes'] ?? null,
        ]);
        
        // TODO: Отправить уведомление менеджерам
        // TODO: Отправить email пользователю
        
        return redirect()->back()->with('success', 'Ваша заявка успешно отправлена! Наши менеджеры свяжутся с вами в ближайшее время.');
    }
}
