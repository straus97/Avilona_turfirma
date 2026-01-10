<?php

namespace App\Http\Controllers;

use App\Http\Requests\ProfileUpdateRequest;
use App\Models\Booking;
use App\Models\Message;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class ProfileController extends Controller
{
    /**
     * Показать главную страницу личного кабинета (Dashboard)
     */
    public function dashboard(Request $request): View
    {
        $user = $request->user();
        
        // Получаем статистику для туриста
        $bookingsCount = Booking::where('user_id', $user->id)->count();
        $pendingBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS])
            ->count();
        $confirmedBookings = Booking::where('user_id', $user->id)
            ->where('status', Booking::STATUS_CONFIRMED)
            ->count();
        $activeBookings = Booking::where('user_id', $user->id)
            ->whereIn('status', [Booking::STATUS_NEW, Booking::STATUS_PROGRESS, Booking::STATUS_CONFIRMED])
            ->count();
        $completedBookings = Booking::where('user_id', $user->id)
            ->where('status', Booking::STATUS_COMPLETED)
            ->count();
        
        // Последние заявки
        $recentBookings = Booking::where('user_id', $user->id)
            ->with(['tour', 'manager'])
            ->latest()
            ->limit(5)
            ->get();
        
        // Непрочитанные сообщения
        $unreadMessages = Message::where('receiver_id', $user->id)
            ->where('is_read', false)
            ->count();
        
        return view('profile.dashboard', compact(
            'user',
            'bookingsCount',
            'pendingBookings',
            'confirmedBookings',
            'activeBookings',
            'completedBookings',
            'recentBookings',
            'unreadMessages'
        ));
    }
    
    /**
     * Показать список заявок туриста
     */
    public function bookings(Request $request): View
    {
        $user = $request->user();
        
        $bookings = Booking::where('user_id', $user->id)
            ->with(['tour', 'manager'])
            ->latest()
            ->paginate(10);
        
        return view('profile.bookings', compact('bookings', 'user'));
    }
    
    /**
     * Показать чат с менеджером
     */
    public function chat(Request $request, $bookingId = null): View
    {
        $user = $request->user();
        
        // Получаем все заявки пользователя с менеджерами
        $bookings = Booking::where('user_id', $user->id)
            ->whereNotNull('manager_id')
            ->with('manager')
            ->get();
        
        // Если указана заявка, получаем сообщения по ней
        $messages = collect();
        $currentBooking = null;
        
        if ($bookingId) {
            $currentBooking = Booking::where('user_id', $user->id)
                ->where('id', $bookingId)
                ->with(['manager', 'messages.sender'])
                ->firstOrFail();
            
            $messages = $currentBooking->messages()->with('sender')->latest()->get();
            
            // Отметить сообщения как прочитанные
            Message::where('booking_id', $bookingId)
                ->where('receiver_id', $user->id)
                ->where('is_read', false)
                ->update(['is_read' => true]);
        }
        
        return view('profile.chat', compact('bookings', 'messages', 'currentBooking', 'user'));
    }
    
    /**
     * Показать документы туриста
     */
    public function documents(Request $request): View
    {
        $user = $request->user();
        
        // Получаем все заявки пользователя
        $bookings = Booking::where('user_id', $user->id)
            ->with('tour')
            ->latest()
            ->get();
        
        return view('profile.documents', compact('bookings', 'user'));
    }
    
    /**
     * Загрузить документ
     */
    public function uploadDocument(Request $request, $bookingId): RedirectResponse
    {
        $request->validate([
            'document' => 'required|file|mimes:pdf,jpg,jpeg,png,doc,docx|max:10240', // 10MB max
        ]);
        
        $booking = Booking::where('user_id', $request->user()->id)
            ->findOrFail($bookingId);
        
        if ($request->hasFile('document')) {
            $file = $request->file('document');
            $path = $file->store('documents/bookings/' . $bookingId, 'public');
            
            // Добавляем документ к существующим
            $documents = $booking->documents ?? [];
            $documents[] = [
                'name' => $file->getClientOriginalName(),
                'path' => $path,
                'uploaded_at' => now()->toDateTimeString(),
            ];
            
            $booking->documents = $documents;
            $booking->save();
            
            return back()->with('success', 'Документ успешно загружен');
        }
        
        return back()->with('error', 'Ошибка загрузки документа');
    }
    
    /**
     * Показать страницу настроек профиля
     */
    public function edit(Request $request): View
    {
        return view('profile.settings', [
            'user' => $request->user(),
        ]);
    }

    /**
     * Обновить данные профиля
     */
    public function update(ProfileUpdateRequest $request): RedirectResponse
    {
        $request->user()->fill($request->validated());
        if ($request->user()->isDirty('email')) {
            $request->user()->email_verified_at = null;
        }
        $request->user()->save();
        return Redirect::route('profile.edit')->with('status', 'profile-updated');
    }

    /**
     * Удалить аккаунт
     */
    public function destroy(Request $request): RedirectResponse
    {
        $request->validateWithBag('userDeletion', [
            'password' => ['required', 'current_password'],
        ]);
        $user = $request->user();
        Auth::logout();
        $user->delete();
        $request->session()->invalidate();
        $request->session()->regenerateToken();
        return Redirect::to('/');
    }
}
