<?php

namespace App\Http\Controllers\Message;

use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Booking;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;

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

        // Точечно добавляем защищённую ссылку на вложение только в этот JSON-ответ.
        $messages->append('attachment_download_url');

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
            'attachment' => 'nullable|file|mimes:pdf,doc,docx,xls,xlsx,jpg,jpeg,png,gif,bmp,webp|max:10240', // 10MB
        ]);

        $booking = Booking::findOrFail($validated['booking_id']);
        $this->authorizeBooking($booking);

        // Validate that receiver_id is an actual booking participant (not the sender)
        $senderId = (int) Auth::id();
        $participants = [(int) $booking->user_id];
        if ($booking->manager_id !== null) {
            $participants[] = (int) $booking->manager_id;
        }
        $allowedReceiverIds = array_values(
            array_filter($participants, fn (int $id): bool => $id !== $senderId)
        );
        if (! in_array((int) $validated['receiver_id'], $allowedReceiverIds, true)) {
            throw ValidationException::withMessages([
                'receiver_id' => ['The selected receiver is not a participant in this booking.'],
            ]);
        }

        $validated['sender_id'] = Auth::id();

        // messages.message — NOT NULL без значения по умолчанию; при отправке
        // только вложения текст отсутствует, поэтому нормализуем его в пустую строку.
        $validated['message'] = $validated['message'] ?? '';

        $storedPath = null;

        try {
            // Загрузка файла на приватный диск (не публичный /storage).
            if ($request->hasFile('attachment')) {
                $file = $request->file('attachment');

                if (! $file->isValid()) {
                    throw new \RuntimeException('Uploaded attachment is not valid');
                }

                $storedPath = $file->store('messages', 'local');

                if (! $storedPath) {
                    throw new \RuntimeException('Attachment could not be stored on disk');
                }

                $validated['attachment_url'] = $storedPath;
            }

            $message = Message::create($validated);
        } catch (\Throwable $e) {
            // Убираем осиротевший приватный файл, если сообщение не сохранилось.
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('local')->delete($storedPath);
            }

            Log::error('Message store failed', [
                'booking_id' => $validated['booking_id'],
                'sender_id'  => Auth::id(),
                'exception'  => get_class($e),
            ]);

            throw $e;
        }

        $message->load(['sender', 'receiver']);

        // Сообщение уже сохранено: сбой уведомления не должен откатывать запись,
        // удалять вложение или возвращать пользователю ошибку отправки.
        try {
            event(new NewMessageReceived($message));
        } catch (\Throwable $e) {
            Log::error('NewMessageReceived dispatch failed', [
                'message_id' => $message->id,
                'exception'  => get_class($e),
            ]);
        }

        if ($request->expectsJson()) {
            // Точечно добавляем защищённую ссылку на вложение только в этот JSON-ответ.
            $message->append('attachment_download_url');

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

        $attachmentPath = $message->hasAttachment() ? $message->attachment_url : null;

        // Сначала удаляем запись, затем физический файл: сбой БД не должен
        // оставить активную запись с уже удалённым вложением.
        $message->delete();

        if ($attachmentPath !== null) {
            Storage::disk('local')->delete($attachmentPath);
        }

        return response()->json(['success' => true]);
    }

    /**
     * Скачать вложение сообщения (только участники переписки по заявке)
     */
    public function downloadAttachment(Message $message): StreamedResponse
    {
        $booking = $message->booking;

        if ($booking === null) {
            abort(404);
        }

        $this->authorizeBooking($booking);

        if (! $message->hasAttachment()) {
            abort(404);
        }

        if (! Storage::disk('local')->exists($message->attachment_url)) {
            abort(404);
        }

        $extension = pathinfo($message->attachment_url, PATHINFO_EXTENSION);
        $downloadName = 'attachment-' . $message->id
            . ($extension !== '' ? '.' . $extension : '');

        return Storage::disk('local')->download($message->attachment_url, $downloadName);
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
