<?php

namespace Database\Factories;

use App\Models\IncomingInquiry;
use App\Models\Inquiry;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * Фабрика для синтетических тестов (AOA-03-I1). Не задаёт реальных персональных данных.
 *
 * Нужный origin выбирается состоянием (managerCreated / adminCreated / externalIntake);
 * фабрика без состояния нарушает инвариант «внешнее обращение имеет источник» и
 * намеренно не сохраняется.
 *
 * @extends \Illuminate\Database\Eloquent\Factories\Factory<\App\Models\Inquiry>
 */
class InquiryFactory extends Factory
{
    protected $model = Inquiry::class;

    public function definition(): array
    {
        return [
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE,
            'status' => Inquiry::STATUS_NEW,
        ];
    }

    /** Создано менеджером: принадлежит создателю и ведётся им. */
    public function managerCreated(User $manager): static
    {
        return $this->state(fn () => [
            'origin' => Inquiry::ORIGIN_MANAGER_CREATED,
            'contact_channel' => Inquiry::CHANNEL_PHONE,
            'status' => Inquiry::STATUS_IN_PROGRESS,
            'incoming_inquiry_id' => null,
            'created_by' => $manager->id,
            'assigned_to' => $manager->id,
            'assigned_at' => now(),
        ]);
    }

    /** Создано администратором и назначено указанному сотруднику (по умолчанию — ему самому). */
    public function adminCreated(User $admin, ?User $assignee = null): static
    {
        return $this->state(fn () => [
            'origin' => Inquiry::ORIGIN_ADMIN_CREATED,
            'contact_channel' => Inquiry::CHANNEL_PHONE,
            'status' => Inquiry::STATUS_IN_PROGRESS,
            'incoming_inquiry_id' => null,
            'created_by' => $admin->id,
            'assigned_to' => ($assignee ?? $admin)->id,
            'assigned_at' => now(),
        ]);
    }

    /** Внешнее обращение: ничьё, пока администратор не распределит. */
    public function externalIntake(IncomingInquiry $source): static
    {
        return $this->state(fn () => [
            'origin' => Inquiry::ORIGIN_EXTERNAL_INTAKE,
            'status' => Inquiry::STATUS_NEW,
            'incoming_inquiry_id' => $source->id,
            'created_by' => null,
            'assigned_to' => null,
        ]);
    }

    /** Ведётся указанным сотрудником (для внешнего обращения после распределения). */
    public function assignedTo(User $employee): static
    {
        return $this->state(fn () => [
            'status' => Inquiry::STATUS_IN_PROGRESS,
            'assigned_to' => $employee->id,
            'assigned_at' => now(),
        ]);
    }

    /** Закрыто без бронирования. */
    public function closed(?User $by = null): static
    {
        return $this->state(fn () => [
            'status' => Inquiry::STATUS_CLOSED,
            'closed_at' => now(),
            'closed_by' => $by?->id,
            'close_reason' => 'Синтетическое закрытие для теста',
        ]);
    }
}
