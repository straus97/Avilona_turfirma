<?php

namespace App\Http\Controllers\Review;

use App\Http\Controllers\Controller;
use App\Http\Requests\Review\CreateRequest;
use App\Models\Reviews;
use Illuminate\Support\Facades\Cache;

class CreateController extends Controller
{
    public function __invoke(CreateRequest $request)
    {
        // Валидация данных
        $validatedData = $request->validated();
        // Создание нового объекта модели Review и сохранение данных
        $review = new Reviews();
        $review->name = $validatedData['name'];
        $review->title = $validatedData['subject'];
        $review->content = $validatedData['message'];
        $review->image = 0;
        $review->is_published = 0;
        $review->save();
        // Очистка кеша для страницы отзывов
        Cache::tags(['reviews'])->flush();
        // Перенаправление пользователя на страницу с сообщением об успехе
        return redirect()->route('review.index')->with('success', 'Отзыв успешно отправлен!');
    }
}
