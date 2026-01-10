<?php

namespace App\Policies;

use App\Models\Booking;
use App\Models\User;

class BookingPolicy
{
    /**
     * Просмотр заявки
     */
    public function view(User $user, Booking $booking): bool
    {
        // Админ видит все
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Менеджер видит свои заявки
        if ($user->hasRole('manager') && $booking->manager_id === $user->id) {
            return true;
        }
        
        // Турист видит свои заявки
        if ($user->hasRole('tourist') && $booking->user_id === $user->id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Редактирование заявки
     */
    public function update(User $user, Booking $booking): bool
    {
        // Админ может редактировать все
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Менеджер может редактировать свои заявки
        if ($user->hasRole('manager') && $booking->manager_id === $user->id) {
            return true;
        }
        
        // Турист НЕ может редактировать (только отменять)
        return false;
    }
    
    /**
     * Отмена заявки
     */
    public function cancel(User $user, Booking $booking): bool
    {
        // Админ может отменить любую
        if ($user->hasRole('admin')) {
            return true;
        }
        
        // Турист может отменить ТОЛЬКО если заявка еще не взята в работу
        if ($user->hasRole('tourist') && $booking->user_id === $user->id) {
            // Можно отменить только если статус "новая" и нет назначенного менеджера
            return $booking->status === Booking::STATUS_NEW && !$booking->manager_id;
        }
        
        // Менеджер может отменить свою заявку
        if ($user->hasRole('manager') && $booking->manager_id === $user->id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Подтверждение заявки
     */
    public function confirm(User $user, Booking $booking): bool
    {
        // Только админ и менеджер
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('manager') && $booking->manager_id === $user->id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Завершение заявки
     */
    public function complete(User $user, Booking $booking): bool
    {
        // Только админ и менеджер
        if ($user->hasRole('admin')) {
            return true;
        }
        
        if ($user->hasRole('manager') && $booking->manager_id === $user->id) {
            return true;
        }
        
        return false;
    }
    
    /**
     * Назначение менеджера
     */
    public function assignManager(User $user, Booking $booking): bool
    {
        // Только админ
        return $user->hasRole('admin');
    }
    
    /**
     * Удаление заявки
     */
    public function delete(User $user, Booking $booking): bool
    {
        // Только админ
        return $user->hasRole('admin');
    }
}
