<?php

namespace App\Http\Controllers\Message;

use App\Events\NewMessageReceived;
use App\Http\Controllers\Controller;
use App\Models\Message;
use App\Models\Booking;
use App\Models\User;
use App\Support\Chat\ChatMessagePresenter;
use App\Support\Notifications\MessageNotificationVisibility;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;

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
        $this->authorizeBookingRead($booking);

        $messages = Message::byBooking($bookingId)
            ->with(['sender:id,name', 'receiver:id,name'])
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

        // Явный минимальный контракт (SEC-FIX-01): без профилей участников.
        return response()->json(ChatMessagePresenter::presentMany($messages));
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

        // Первичная проверка — быстрый отказ до загрузки файла; окончательная проверка прав
        // и получателя повторяется ниже под блокировкой строки заявки (AOA-01-I4).
        $booking = Booking::findOrFail($validated['booking_id']);
        $this->authorizeBookingWrite($booking);
        $this->assertReceiverIsParticipant($booking, (int) $validated['receiver_id']);

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

            // Файл сохранён вне блокировки (запись на диск не держит строку заявки).
            // Права и получатель перечитываются по заблокированной заявке: если за время
            // запроса ответственного сменили, сообщение не создаётся.
            $message = DB::transaction(function () use ($booking, $validated): Message {
                $locked = Booking::query()->lockForUpdate()->findOrFail($booking->id);

                // AOA-05-I3: отправитель и получатель блокируются (разделяемо) до записи —
                // охраняемое удаление учётной записи берёт исключительную блокировку той же
                // строки и либо видит это сообщение, либо дожидается его фиксации.
                $participantIds = array_values(array_unique([(int) $validated['sender_id'], (int) $validated['receiver_id']]));
                sort($participantIds);

                if (User::query()->whereIn('id', $participantIds)->orderBy('id')->sharedLock()->pluck('id')->count() !== count($participantIds)) {
                    abort(404);
                }

                $this->authorizeBookingWrite($locked);
                $this->assertReceiverIsParticipant($locked, (int) $validated['receiver_id']);

                return Message::create($validated);
            });
        } catch (\Throwable $e) {
            // Убираем осиротевший приватный файл, если сообщение не сохранилось.
            if (is_string($storedPath) && $storedPath !== '') {
                Storage::disk('local')->delete($storedPath);
            }

            // Отказ по правам и неверный получатель — штатные ответы 403/422, а не сбой сохранения.
            if (! $e instanceof HttpExceptionInterface && ! $e instanceof ValidationException) {
                Log::error('Message store failed', [
                    'booking_id' => $validated['booking_id'],
                    'sender_id'  => Auth::id(),
                    'exception'  => get_class($e),
                ]);
            }

            throw $e;
        }

        // Полные модели нужны слушателю уведомлений (e-mail, настройки получателя);
        // в JSON-ответ они попадают только через ChatMessagePresenter.
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
            // Явный минимальный контракт (SEC-FIX-01): без профилей участников.
            return response()->json([
                'success' => true,
                'message' => ChatMessagePresenter::present($message),
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
        $user = Auth::user();

        // Счётчик учитывает только заявки, доступные пользователю СЕЙЧАС (AOA-01-I2B, G8):
        // после переназначения прежний ответственный не получает сведений о чужой переписке.
        // Правило общее с дашбордом и сайдбаром (Message::scopeAccessibleTo, AOA-02).
        $count = Message::byReceiver($user->id)->unread()->accessibleTo($user)->count();
        
        return response()->json(['count' => $count]);
    }

    /**
     * Удалить сообщение (администратор или отправитель, который всё ещё участник переписки)
     */
    public function destroy(Message $message)
    {
        $user = Auth::user();

        if ($message->sender_id !== $user->id && !$user->isAdmin()) {
            abort(403);
        }

        $attachmentPath = $message->hasAttachment() ? $message->attachment_url : null;

        // Сначала удаляем запись, затем физический файл: сбой БД не должен
        // оставить активную запись с уже удалённым вложением. Снимок текста в уведомлениях
        // затирается в той же транзакции (AOA-01-I6): либо удалено всё, либо ничего.
        // Файл вложения удаляется после коммита и откатить его нельзя.
        DB::transaction(function () use ($message, $user): void {
            // Контракт администратора не меняется (удаляет любое сообщение). Остальные — только
            // свои и только пока участвуют в переписке (AOA-02): прежний ответственный или
            // менеджер без роли после переназначения не меняют историю чужой заявки. Права
            // перечитываются под блокировкой заявки, как при отправке (AOA-01-I4).
            if (! $user->isAdmin()) {
                $booking = Booking::withTrashed()->lockForUpdate()->find($message->booking_id);

                if ($booking === null) {
                    abort(404);
                }

                $this->authorizeBookingWrite($booking);
            }

            $message->delete();
            MessageNotificationVisibility::redactDeleted((int) $message->id);
        });

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

        $this->authorizeBookingRead($booking);

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
     * receiver_id должен быть реальным участником заявки (владелец или текущий
     * ответственный), но не самим отправителем.
     */
    private function assertReceiverIsParticipant(Booking $booking, int $receiverId): void
    {
        $senderId = (int) Auth::id();
        $participants = [(int) $booking->user_id];
        if ($booking->manager_id !== null) {
            $participants[] = (int) $booking->manager_id;
        }
        $allowedReceiverIds = array_values(
            array_filter($participants, fn (int $id): bool => $id !== $senderId)
        );
        if (! in_array($receiverId, $allowedReceiverIds, true)) {
            throw ValidationException::withMessages([
                'receiver_id' => ['Выбранный получатель не является участником этой заявки.'],
            ]);
        }
    }

    /**
     * Проверка прав доступа к заявке на чтение переписки (E3-A2: Admin
     * может наблюдать за любой заявкой в режиме read-only).
     */
    private function authorizeBookingRead(Booking $booking)
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

    /**
     * Проверка прав доступа к заявке на отправку сообщений. В отличие от
     * чтения, Admin здесь не получает безусловный доступ — писать может
     * только Admin, лично назначенный на заявку (booking.manager_id ===
     * его id), иначе наблюдатель мог бы обойти скрытый в разметке composer
     * прямым запросом к API.
     */
    private function authorizeBookingWrite(Booking $booking)
    {
        $user = Auth::user();

        if (($user->isAdmin() || $user->isManager()) && $booking->manager_id === $user->id) {
            return;
        }

        if ($user->isTourist() && $booking->user_id === $user->id) {
            return;
        }

        abort(403, 'У вас нет доступа к этой заявке');
    }
}
