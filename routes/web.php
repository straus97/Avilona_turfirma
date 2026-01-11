<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Home'], function () {
    //меню сайта
    Route::get('/', "IndexController")->name("home.index")->middleware('cache.response');

    Route::get('/reviews/{id_reviews}', "ReviewsController")->name("reviews.show")->middleware('cache.response');
});

Route::group(['namespace' => 'Tour'], function () {
    Route::get('/tours', 'IndexController')->name('tours.index');
});

// Маршруты смены пароля (без проверки password.change)
Route::middleware('auth')->group(function () {
    Route::get('/password/change', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'show'])->name('password.change');
    Route::post('/password/change', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'update'])->name('password.update');
});

// Заявки (доступны только авторизованным пользователям)
Route::middleware(['auth', 'password.change'])->group(function () {
    Route::resource('bookings', \App\Http\Controllers\Booking\BookingController::class);
    Route::post('bookings/{booking}/assign-manager', [\App\Http\Controllers\Booking\BookingController::class, 'assignManager'])
        ->name('bookings.assign-manager')->middleware('role:admin');
    Route::post('bookings/{booking}/confirm', [\App\Http\Controllers\Booking\BookingController::class, 'confirm'])
        ->name('bookings.confirm')->middleware('role:manager,admin');
    Route::post('bookings/{booking}/cancel', [\App\Http\Controllers\Booking\BookingController::class, 'cancel'])
        ->name('bookings.cancel');
    Route::post('bookings/{booking}/complete', [\App\Http\Controllers\Booking\BookingController::class, 'complete'])
        ->name('bookings.complete')->middleware('role:manager,admin');
    
    // Сообщения
    Route::get('messages', [\App\Http\Controllers\Message\MessageController::class, 'index'])->name('messages.index');
    Route::post('messages', [\App\Http\Controllers\Message\MessageController::class, 'store'])->name('messages.store');
    Route::post('messages/{message}/read', [\App\Http\Controllers\Message\MessageController::class, 'markAsRead'])->name('messages.read');
    Route::get('messages/unread-count', [\App\Http\Controllers\Message\MessageController::class, 'unreadCount'])->name('messages.unread-count');
    Route::delete('messages/{message}', [\App\Http\Controllers\Message\MessageController::class, 'destroy'])->name('messages.destroy');
});

Route::group(['namespace' => 'Company'], function () {
    Route::get('/company/about_company', "AboutController")->name("about_company.index")->middleware('cache.response');
    Route::get('/company/employees', "EmployeesController")->name("employees.index")->middleware('cache.response');
    Route::get('/company/awards', "AwardsController")->name("awards.index")->middleware('cache.response');
});

Route::group(['namespace' => 'Countries'], function () {
    Route::get('/countries', 'IndexController')->name('countries.index')->middleware('cache.response');
    Route::get('/countries/{slug}', 'ImageController')->name('countries.show_countries_image')->middleware(
        'cache.response'
    );
});

Route::group(['namespace' => 'Destination'], function () {
    Route::get('/destinations', "IndexController")->name("destination.index")->middleware('cache.response');
    Route::get('/destinations/{slug}', "ImageController")->name("destinations.show_destinations_image")->middleware(
        'cache.response'
    );
});

Route::group(['namespace' => 'Contact'], function () {
    Route::get('/contacts', "IndexController")->name("contact.index")->middleware('cache.response');
//Отправка формы "контактная информация" на почту
    Route::post('/contact/send_home', 'SendHomeController')->name('contact.send_home'); //отправка с главной страницы
    Route::post('/contact/send_contact', 'SendContactController')->name(
        'contact.send_contact'
    ); //отправка с контактной страницы
});

Route::group(['namespace' => 'Review'], function () {
    Route::get('/reviews', "IndexController")->name("review.index")->middleware('cache.response');
    Route::post('/reviews/create', "CreateController")->name("review_create.index");
});

Route::group(['namespace' => 'HelpfulInformation'], function () {
    Route::get('/helpful_information/interesting_articles', "ArticlesController")->name(
        "interesting_articles.index"
    )->middleware('cache.response');
    Route::get('/helpful_information/interesting_articles/{slug}', "InterestingNewsController")->name(
        "helpful_information.show_interesting_news"
    )->middleware('cache.response');

    Route::get('/helpful_information/news', "HelpfulNewsController")->name("helpful_news.index")->middleware(
        'cache.response'
    );
    Route::get('/helpful_information/news/{slug}', "HelpfulNewsIdController")->name(
        "helpful_news_id.index"
    )->middleware('cache.response');
    Route::get('/helpful_information/news/rss', "RssController")->name("news.rss")->middleware('cache.response');

    Route::get('/helpful_information/for_our_clients', "ClientsController")->name("for_our_clients.index")->middleware(
        'cache.response'
    );
    Route::get('/helpful_information/for_our_clients/{slug}', "SpecialController")->name(
        "helpful_information.show_special"
    )->middleware('cache.response');

    Route::get('/helpful_information/travel_dictionary', "DictionaryController")->name(
        "travel_dictionary.index"
    )->middleware('cache.response');
});

Route::group(['namespace' => 'Captcha'], function () {
    Route::get('/reload-captcha', "ReloadCaptchaController")->name("captcha_reload.index")->middleware(
        'cache.response'
    );
});

// Маршруты для всех авторизованных пользователей (tourist, manager, admin)
Route::middleware(['auth', 'password.change', 'verified', 'role:tourist,manager,admin'])->prefix('profile')->name('profile.')->group(function () {
    // Главная страница личного кабинета
    Route::get('/dashboard', [ProfileController::class, 'dashboard'])->name('dashboard');
    
    // Заявки туриста
    Route::get('/bookings', [ProfileController::class, 'bookings'])->name('bookings');
    
    // Чат с менеджером
    Route::get('/chat/{bookingId?}', [ProfileController::class, 'chat'])->name('chat');
    
    // Документы
    Route::get('/documents', [ProfileController::class, 'documents'])->name('documents');
    Route::post('/documents/{booking}/upload', [ProfileController::class, 'uploadDocument'])->name('upload-document');
    
    // Настройки профиля
    Route::get('/settings', [ProfileController::class, 'edit'])->name('edit');
    Route::patch('/settings', [ProfileController::class, 'update'])->name('update');
    Route::delete('/settings', [ProfileController::class, 'destroy'])->name('destroy');

    // Старые маршруты для обратной совместимости
    Route::get('/account', [ProfileController::class, 'dashboard'])->name('account.legacy');
    Route::get('/message', function () {
        return redirect()->route('profile.chat');
    })->name('message.legacy');
});

// Редирект со старого маршрута account на новый
Route::middleware(['auth'])->get('/account', function () {
    return redirect()->route('profile.dashboard');
})->name('account');

Route::middleware(['auth'])->get('/message', function () {
    return redirect()->route('profile.chat');
})->name('message');

// Маршруты только для менеджеров и администраторов
Route::middleware(['auth', 'password.change', 'role:manager,admin'])->prefix('manager')->name('manager.')->group(function () {
    // Главная страница панели менеджера
    Route::get('/dashboard', [\App\Http\Controllers\Manager\ManagerController::class, 'dashboard'])->name('dashboard');
    
    // Список клиентов
    Route::get('/clients', [\App\Http\Controllers\Manager\ManagerController::class, 'clients'])->name('clients');
    
    // Управление заявками
    Route::get('/bookings', [\App\Http\Controllers\Manager\ManagerController::class, 'bookings'])->name('bookings');
    
    // Чат с клиентами
    Route::get('/chat/{bookingId?}', [\App\Http\Controllers\Manager\ManagerController::class, 'chat'])->name('chat');
    
    // Статистика
    Route::get('/statistics', [\App\Http\Controllers\Manager\ManagerController::class, 'statistics'])->name('statistics');
});

// Маршруты только для администраторов
Route::middleware(['auth', 'password.change', 'role:admin'])->prefix('admin')->name('admin.')->group(function () {
    // Главная страница админ панели
    Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
    
    // Управление пользователями
    Route::get('/users', [\App\Http\Controllers\Admin\AdminController::class, 'users'])->name('users');
    Route::get('/users/{user}/roles', [\App\Http\Controllers\Admin\AdminController::class, 'userRoles'])->name('user-roles');
    Route::post('/users/{user}/assign-role', [\App\Http\Controllers\Admin\AdminController::class, 'assignRole'])->name('assign-role');
    Route::delete('/users/{user}/roles/{role}', [\App\Http\Controllers\Admin\AdminController::class, 'removeRole'])->name('remove-role');
    Route::delete('/users/{user}', [\App\Http\Controllers\Admin\AdminController::class, 'deleteUser'])->name('delete-user');
    
    // Управление заявками
    Route::get('/bookings', [\App\Http\Controllers\Admin\AdminController::class, 'bookings'])->name('bookings');
    
    // Управление контентом
    Route::get('/content', [\App\Http\Controllers\Admin\AdminController::class, 'content'])->name('content');
    
    // Системные настройки
    Route::get('/settings', [\App\Http\Controllers\Admin\AdminController::class, 'settings'])->name('settings');
    Route::post('/settings/clear-cache', [\App\Http\Controllers\Admin\AdminController::class, 'clearCache'])->name('clear-cache');
});

require __DIR__ . '/auth.php';
