<?php

namespace Tests\Feature\Concerns;

use App\Models\Booking;
use App\Models\IncomingInquiry;
use App\Models\Message;
use App\Models\Role;
use App\Models\User;
use App\Notifications\NewMessageDatabaseNotification;
use Illuminate\Notifications\DatabaseNotification;

/** Синтетические данные для тестов AOA-01-I2B (клиенты, заявки, обращения, уведомления). */
trait BuildsAoaI2bFixtures
{
    protected function userWith(string $role, array $attributes = []): User
    {
        $user = User::factory()->create($attributes);
        $roleModel = Role::query()->firstOrCreate(['name' => $role], ['description' => Role::availableRoles()[$role] ?? $role]);
        $user->roles()->attach($roleModel->id);

        return $user;
    }

    protected function bookingOf(User $client, ?User $manager, string $status = Booking::STATUS_PROGRESS, array $overrides = []): Booking
    {
        return Booking::withoutEvents(fn (): Booking => Booking::query()->create(array_merge([
            'user_id' => $client->id,
            'manager_id' => $manager?->id,
            'status' => $status,
            'departure_city' => 'Москва',
            'destination_country' => 'Турция',
            'destination_city' => 'Анталья',
            'start_date' => now()->addMonths(2)->toDateString(),
            'nights' => 7,
            'adults' => 2,
            'children' => 0,
            'total_price' => 150000,
        ], $overrides)));
    }

    protected function inquiryFor(?User $assignee, ?User $client = null, array $overrides = []): IncomingInquiry
    {
        $inquiry = new IncomingInquiry();
        $inquiry->forceFill(array_merge([
            'provider' => 'tourvisor',
            'external_type' => 0,
            'external_id' => (string) random_int(100000, 999999),
            'state' => IncomingInquiry::STATE_IMPORTED,
            'webhook_count' => 1,
            'first_notified_at' => now(),
            'last_notified_at' => now(),
            'imported_at' => now(),
            'client_name' => 'Синтетический Заявитель',
            'client_phone' => '+7 (900) 123-45-67',
            'client_email' => 'inquiry.person@example.test',
            'operator_name' => 'Sunmar',
            'departure_city' => 'Москва',
            'destination_country' => 'Таиланд',
            'price' => '100000',
            'currency' => 'RUB',
            'nights' => 7,
            'normalizer_version' => 1,
            'workflow_state' => $assignee ? IncomingInquiry::WORKFLOW_IN_PROGRESS : IncomingInquiry::WORKFLOW_NEW,
            'assigned_to' => $assignee?->id,
            'client_user_id' => $client?->id,
        ], $overrides))->save();

        return $inquiry->refresh();
    }

    /** Сохраняет уведомление о сообщении по заявке адресату $to (как делает слушатель). */
    protected function messageNotice(Booking $booking, User $from, User $to, string $text): DatabaseNotification
    {
        $message = Message::query()->create([
            'booking_id' => $booking->id,
            'sender_id' => $from->id,
            'receiver_id' => $to->id,
            'message' => $text,
            'is_read' => false,
        ]);

        $to->notify(new NewMessageDatabaseNotification($message->load('sender')));

        return $to->notifications()->get()
            ->first(fn (DatabaseNotification $n): bool => ($n->data['message_id'] ?? null) === $message->id);
    }
}
