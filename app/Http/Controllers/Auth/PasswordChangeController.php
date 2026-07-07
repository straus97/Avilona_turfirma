<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Validation\Rules\Password;

class PasswordChangeController extends Controller
{
    /**
     * Показать форму смены пароля
     */
    public function show()
    {
        $user = Auth::user();

        if (!$user || !$user->password_change_required) {
            return redirect()->route('cabinet.dashboard');
        }

        return view('auth.change-password');
    }
    
    /**
     * Обработать смену пароля
     */
    public function update(Request $request)
    {
        $user = Auth::user();

        if (!$user || !$user->password_change_required) {
            return redirect()->route('cabinet.dashboard');
        }

        $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Password::defaults()],
        ], [
            'current_password.required' => 'Введите текущий пароль',
            'current_password.current_password' => 'Текущий пароль неверен',
            'password.required' => 'Введите новый пароль',
            'password.confirmed' => 'Пароли не совпадают',
        ]);
        
        // Обновляем пароль
        $user->password = Hash::make($request->password);
        $user->password_change_required = false;
        $user->temp_password = null; // Очищаем временный пароль
        
        // Подтверждаем email автоматически
        if (!$user->email_verified_at) {
            $user->email_verified_at = now();
        }
        
        $user->save();
        
        return redirect()->route('cabinet.dashboard')
            ->with('success', 'Пароль успешно изменен! Ваш email подтвержден. Добро пожаловать!');
    }
}
