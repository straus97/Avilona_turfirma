<?php

namespace App\Http\Controllers\Message;

use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Storage;

class MessageController extends Controller
{
    public function __construct()
    {
        $this->middleware('auth');
    }

    /**
     * Получить сообщения по заявке
     */
    public function index(Request $request)
    {
        $bookingId = $request->query('booking_id');
        
        if (!$bookingId) {
            return response()->json(['error' => 'Booking ID required'], 400);
        }
        
        $booking = Booking::findOrFail($bookingId);
        $this->authorizeBooking($booking);
        
        $messages = Message::byBooking($bookingId)
            ->with(['sender', 'receiver'])
            ->orderBy('created_at', 'asc')
            ->get();
        
        // Отмечаем непрочитанные сообщения как прочитанные
        Message::byBooking($bookingId)
            ->byReceiver(Auth::id())
            ->unread()
            ->update([
                'is_read' => true,
                'read_at' => now(),
            ]);
        
        return response()->json($messages);
    }

    /**
     * Отправить сообщение
     */
    public function store(Request $request)
    {
        $validated = $request->validate([
            'booking_id' => 'required|exists:bookings,id',
            'receiver_id' => 'required|exists:users,id',
            'message' => 'required_without:attachment|string|max:5000',
            'attachment' => 'nullable|file|max:10240', // 10MB
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);
        $this->authorizeBooking($booking);
        
        $validated['sender_id'] = Auth::id();
        
        // Загрузка файла
        if ($request->hasFile('attachment')) {
            $path = $request->file('attachment')->store('messages', 'public');
            $validated['attachment_url'] = $path;
        }
        
        $message = Message::create($validated);
        $message->load(['sender', 'receiver']);
        
        if ($request->expectsJson()) {
            return response()->json([
                'success' => true,
                'message' => $message,
            ]);
        }

        return back();
    }

    /**
     * Отметить сообщение как прочитанное
     */
    public function markAsRead(Message $message)
    {
        if ($message->receiver_id !== Auth::id()) {
            abort(403);
        }
        
        $message->markAsRead();
        
        return response()->json(['success' => true]);
    }

    /**
     * Получить количество непрочитанных сообщений
     */
    public function unreadCount()
    {
        $count = Message::byReceiver(Auth::id())
            ->unread()
            ->count();
        
        return response()->json(['count' => $count]);
    }

    /**
     * Удалить сообщение (только отправитель или админ)
     */
    public function destroy(Message $message)
    {
        $user = Auth::user();
        
        if ($message->sender_id !== $user->id && !$user->isAdmin()) {
            abort(403);
        }
        
        // Удаляем файл если есть
        if ($message->hasAttachment()) {
            Storage::disk('public')->delete($message->attachment_url);
        }
        
        $message->delete();
        
        return response()->json(['success' => true]);
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
