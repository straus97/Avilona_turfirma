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
    Route::post('/password/change', [\App\Http\Controllers\Auth\PasswordChangeController::class, 'update'])->name('password.change.update');
});

// API для получения курортов по стране
Route::get('/api/destination-cities', [\App\Http\Controllers\Api\DestinationCityController::class, 'getCitiesByCountry'])
    ->name('api.destination-cities');

// Новый личный кабинет (единая система для всех ролей)
Route::middleware(['auth', 'password.change'])->prefix('cabinet')->name('cabinet.')->group(function () {
    // Общие маршруты
    Route::get('/', [\App\Http\Controllers\Cabinet\CabinetController::class, 'dashboard'])->name('dashboard');
    
    // Турист
    Route::middleware('role:tourist,manager,admin')->group(function () {
        Route::get('/bookings', [\App\Http\Controllers\Cabinet\CabinetController::class, 'bookings'])->name('bookings');
        Route::get('/chat/{bookingId?}', [\App\Http\Controllers\Cabinet\CabinetController::class, 'chat'])->name('chat');
        Route::get('/documents/personal', [\App\Http\Controllers\Cabinet\CabinetController::class, 'personalDocuments'])->name('documents.personal');
        Route::get('/documents/bookings', [\App\Http\Controllers\Cabinet\CabinetController::class, 'bookingDocuments'])->name('documents.bookings');
        Route::get('/documents/bookings/{booking}/{document}/download', [\App\Http\Controllers\Cabinet\CabinetController::class, 'downloadBookingDocument'])->name('documents.bookings.download');
        Route::get('/bonus', [\App\Http\Controllers\Cabinet\CabinetController::class, 'bonusProgram'])->name('bonus');
        Route::get('/wishlist', [\App\Http\Controllers\Cabinet\CabinetController::class, 'wishlist'])->name('wishlist');
        Route::get('/profile', [\App\Http\Controllers\Cabinet\CabinetController::class, 'profile'])->name('profile');
        Route::patch('/profile', [\App\Http\Controllers\Cabinet\CabinetController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/update-passport', [\App\Http\Controllers\Cabinet\CabinetController::class, 'updatePassport'])->name('profile.update-passport');
        Route::post('/profile/upload-avatar', [\App\Http\Controllers\Cabinet\CabinetController::class, 'uploadAvatar'])->name('profile.upload-avatar');
        
        Route::get('/settings', [\App\Http\Controllers\Cabinet\CabinetController::class, 'settings'])->name('settings');
        Route::post('/settings/notifications', [\App\Http\Controllers\Cabinet\CabinetController::class, 'updateNotifications'])->name('settings.notifications');
        Route::delete('/settings/delete-account', [\App\Http\Controllers\Cabinet\CabinetController::class, 'destroyAccount'])->name('settings.destroy-account');
        
        // Документы
        Route::post('/documents/personal/upload', [\App\Http\Controllers\Cabinet\CabinetController::class, 'uploadPersonalDocument'])->name('documents.personal.upload');
        Route::get('/documents/personal/{document}/download', [\App\Http\Controllers\Cabinet\CabinetController::class, 'downloadPersonalDocument'])->name('documents.personal.download');
        Route::delete('/documents/personal/{document}', [\App\Http\Controllers\Cabinet\CabinetController::class, 'deletePersonalDocument'])->name('documents.personal.delete');
    });
    
    // Менеджер
    Route::middleware('role:manager,admin')->prefix('manager')->name('manager.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Manager\ManagerController::class, 'dashboard'])->name('dashboard');
        Route::get('/clients', [\App\Http\Controllers\Manager\ManagerController::class, 'clients'])->name('clients');
        Route::get('/bookings', [\App\Http\Controllers\Manager\ManagerController::class, 'bookings'])->name('bookings');
        Route::get('/chat/{bookingId?}', [\App\Http\Controllers\Manager\ManagerController::class, 'chat'])->name('chat');
        Route::get('/statistics', [\App\Http\Controllers\Manager\ManagerController::class, 'statistics'])->name('statistics');
        Route::get('/profile', [\App\Http\Controllers\Manager\ManagerController::class, 'profile'])->name('profile');
        Route::patch('/profile', [\App\Http\Controllers\Manager\ManagerController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/avatar', [\App\Http\Controllers\Manager\ManagerController::class, 'uploadAvatar'])->name('profile.avatar');
        Route::get('/settings', [\App\Http\Controllers\Manager\ManagerController::class, 'settings'])->name('settings');
        Route::post('/settings/password', [\App\Http\Controllers\Manager\ManagerController::class, 'updatePassword'])->name('settings.password');
        Route::post('/settings/notifications', [\App\Http\Controllers\Manager\ManagerController::class, 'updateNotifications'])->name('settings.notifications');
        Route::delete('/settings/account', [\App\Http\Controllers\Manager\ManagerController::class, 'destroyAccount'])->name('destroy-account');
        Route::get('/documents', [\App\Http\Controllers\Manager\ManagerController::class, 'documents'])->name('documents');
        Route::post('/documents/upload', [\App\Http\Controllers\Manager\ManagerController::class, 'uploadDocument'])->name('documents.upload');
        Route::get('/documents/{document}/download', [\App\Http\Controllers\Manager\ManagerController::class, 'downloadDocument'])->name('documents.download');
        Route::delete('/documents/{document}', [\App\Http\Controllers\Manager\ManagerController::class, 'deleteDocument'])->name('documents.delete');
        Route::get('/finance', [\App\Http\Controllers\Manager\ManagerController::class, 'finance'])->name('finance');
        Route::get('/knowledge', [\App\Http\Controllers\Manager\ManagerController::class, 'knowledge'])->name('knowledge');
        Route::get('/content', [\App\Http\Controllers\Manager\ManagerController::class, 'content'])->name('content');
        Route::get('/articles', [\App\Http\Controllers\Manager\ManagerController::class, 'articles'])->name('articles');
        Route::get('/articles/create', [\App\Http\Controllers\Manager\ManagerController::class, 'createArticle'])->name('articles.create');
        Route::post('/articles', [\App\Http\Controllers\Manager\ManagerController::class, 'storeArticle'])->name('articles.store');
        Route::get('/articles/{article}/edit', [\App\Http\Controllers\Manager\ManagerController::class, 'editArticle'])->name('articles.edit');
        Route::put('/articles/{article}', [\App\Http\Controllers\Manager\ManagerController::class, 'updateArticle'])->name('articles.update');
        Route::delete('/articles/{article}', [\App\Http\Controllers\Manager\ManagerController::class, 'deleteArticle'])->name('articles.delete');
        Route::get('/reviews/{review}/edit', [\App\Http\Controllers\Manager\ManagerController::class, 'editReview'])->name('reviews.edit');
        Route::put('/reviews/{review}', [\App\Http\Controllers\Manager\ManagerController::class, 'updateReview'])->name('reviews.update');
    });
    
    // Админ
    Route::middleware('role:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::get('/dashboard', [\App\Http\Controllers\Admin\AdminController::class, 'dashboard'])->name('dashboard');
        Route::get('/profile', [\App\Http\Controllers\Admin\AdminController::class, 'profile'])->name('profile');
        Route::patch('/profile', [\App\Http\Controllers\Admin\AdminController::class, 'updateProfile'])->name('profile.update');
        Route::post('/profile/avatar', [\App\Http\Controllers\Admin\AdminController::class, 'uploadAvatar'])->name('profile.avatar');

        Route::get('/users', [\App\Http\Controllers\Admin\AdminController::class, 'users'])->name('users');
        Route::get('/users/{user}/roles', [\App\Http\Controllers\Admin\AdminController::class, 'userRoles'])->name('user-roles');
        Route::post('/users/{user}/assign-role', [\App\Http\Controllers\Admin\AdminController::class, 'assignRole'])->name('assign-role');
        Route::delete('/users/{user}/roles/{role}', [\App\Http\Controllers\Admin\AdminController::class, 'removeRole'])->name('remove-role');
        Route::post('/users/{user}/update-role', [\App\Http\Controllers\Admin\AdminController::class, 'updateUserRole'])->name('user-update-role');
        Route::get('/users/{user}/documents/{document}/download', [\App\Http\Controllers\Admin\AdminController::class, 'downloadUserDocument'])->name('user-document.download');
        Route::get('/users/{user}', [\App\Http\Controllers\Admin\AdminController::class, 'userShow'])->name('user-show');
        Route::delete('/users/{user}', [\App\Http\Controllers\Admin\AdminController::class, 'deleteUser'])->name('delete-user');
        Route::get('/bookings', [\App\Http\Controllers\Admin\AdminController::class, 'bookings'])->name('bookings');
        Route::get('/roles', [\App\Http\Controllers\Admin\AdminController::class, 'roles'])->name('roles');
        Route::get('/chats/{bookingId?}', [\App\Http\Controllers\Admin\AdminController::class, 'chats'])->name('chats');
        Route::get('/content', [\App\Http\Controllers\Admin\AdminController::class, 'content'])->name('content');
        Route::get('/articles', [\App\Http\Controllers\Admin\AdminController::class, 'articles'])->name('articles');
        Route::get('/articles/create', [\App\Http\Controllers\Admin\AdminController::class, 'createArticle'])->name('articles.create');
        Route::post('/articles', [\App\Http\Controllers\Admin\AdminController::class, 'storeArticle'])->name('articles.store');
        Route::get('/articles/{article}/edit', [\App\Http\Controllers\Admin\AdminController::class, 'editArticle'])->name('articles.edit');
        Route::put('/articles/{article}', [\App\Http\Controllers\Admin\AdminController::class, 'updateArticle'])->name('articles.update');
        Route::delete('/articles/{article}', [\App\Http\Controllers\Admin\AdminController::class, 'deleteArticle'])->name('articles.delete');
        Route::get('/reviews/{review}/edit', [\App\Http\Controllers\Admin\AdminController::class, 'editReview'])->name('reviews.edit');
        Route::put('/reviews/{review}', [\App\Http\Controllers\Admin\AdminController::class, 'updateReview'])->name('reviews.update');
        Route::get('/finance', [\App\Http\Controllers\Admin\AdminController::class, 'finance'])->name('finance');
        Route::get('/bonus', [\App\Http\Controllers\Admin\AdminController::class, 'bonus'])->name('bonus');
        Route::get('/settings', [\App\Http\Controllers\Admin\AdminController::class, 'settings'])->name('settings');
        Route::post('/settings/password', [\App\Http\Controllers\Admin\AdminController::class, 'updatePassword'])->name('settings.password');
        Route::post('/settings/notifications', [\App\Http\Controllers\Admin\AdminController::class, 'updateNotifications'])->name('settings.notifications');
        Route::post('/settings/clear-cache', [\App\Http\Controllers\Admin\AdminController::class, 'clearCache'])->name('clear-cache');
        Route::get('/logs', [\App\Http\Controllers\Admin\AdminController::class, 'logs'])->name('logs');
        Route::delete('/profile/account', [\App\Http\Controllers\Admin\AdminController::class, 'destroyAccount'])->name('destroy-account');
    });
});

// Заявки (доступны только авторизованным пользователям)
Route::middleware(['auth', 'password.change'])->group(function () {
    // Редирект /bookings на кабинет
    Route::get('bookings', function() {
        return redirect()->route('cabinet.bookings');
    });
    
    // CRUD для заявок (только создание и просмотр доступны через старые routes)
    Route::get('bookings/create', [\App\Http\Controllers\Booking\BookingController::class, 'create'])->name('bookings.create');
    Route::post('bookings', [\App\Http\Controllers\Booking\BookingController::class, 'store'])->name('bookings.store');
    Route::get('bookings/{booking}', [\App\Http\Controllers\Booking\BookingController::class, 'show'])->name('bookings.show');
    Route::get('bookings/{booking}/edit', [\App\Http\Controllers\Booking\BookingController::class, 'edit'])->name('bookings.edit');
    Route::put('bookings/{booking}', [\App\Http\Controllers\Booking\BookingController::class, 'update'])->name('bookings.update');
    Route::delete('bookings/{booking}', [\App\Http\Controllers\Booking\BookingController::class, 'destroy'])->name('bookings.destroy');
    
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
    Route::get('messages/{message}/attachment', [\App\Http\Controllers\Message\MessageController::class, 'downloadAttachment'])->name('messages.attachment');
    Route::delete('messages/{message}', [\App\Http\Controllers\Message\MessageController::class, 'destroy'])->name('messages.destroy');

    // Управление документами по заявкам (только менеджер/админ)
    Route::middleware('role:manager,admin')->group(function () {
        Route::post('bookings/{booking}/documents', [\App\Http\Controllers\Booking\BookingController::class, 'storeDocument'])->name('bookings.documents.store');
        Route::get('bookings/{booking}/documents/{document}/download', [\App\Http\Controllers\Booking\BookingController::class, 'downloadDocument'])->name('bookings.documents.download');
        Route::delete('bookings/{booking}/documents/{document}', [\App\Http\Controllers\Booking\BookingController::class, 'destroyDocument'])->name('bookings.documents.destroy');
    });
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
    Route::get('/helpful_information/news/rss', "RssController")->name("news.rss")->middleware('cache.response');
    Route::get('/helpful_information/news/{slug}', "HelpfulNewsIdController")->name(
        "helpful_news_id.index"
    )->middleware('cache.response');

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

// СТАРЫЕ МАРШРУТЫ - РЕДИРЕКТЫ НА НОВЫЙ КАБИНЕТ
Route::middleware(['auth'])->group(function () {
    Route::get('/profile/dashboard', function () {
        return redirect()->route('cabinet.dashboard');
    });
    Route::get('/profile/settings', function () {
        return redirect()->route('cabinet.settings');
    });
    Route::get('/profile/bookings', function () {
        return redirect()->route('cabinet.bookings');
    });
    Route::get('/profile/chat/{bookingId?}', function ($bookingId = null) {
        return redirect()->route('cabinet.chat', $bookingId);
    });
    Route::get('/profile/documents', function () {
        return redirect()->route('cabinet.documents.personal');
    });
    Route::get('/account', function () {
        return redirect()->route('cabinet.dashboard');
    });
});

// Маршруты только для менеджеров и администраторов
Route::middleware(['auth', 'password.change', 'role:manager,admin'])->prefix('manager')->name('manager.')->group(function () {
    Route::get('/dashboard', function () {
        return redirect()->route('cabinet.manager.dashboard');
    })->name('dashboard');
    Route::get('/clients', function () {
        return redirect()->route('cabinet.manager.clients');
    })->name('clients');
    Route::get('/bookings', function () {
        return redirect()->route('cabinet.manager.bookings');
    })->name('bookings');
    Route::get('/chat/{bookingId?}', function ($bookingId = null) {
        return redirect()->route('cabinet.manager.chat', ['bookingId' => $bookingId]);
    })->name('chat');
    Route::get('/statistics', function () {
        return redirect()->route('cabinet.manager.statistics');
    })->name('statistics');
    Route::get('/finance', function () {
        return redirect()->route('cabinet.manager.finance');
    })->name('finance');
    Route::get('/knowledge', function () {
        return redirect()->route('cabinet.manager.knowledge');
    })->name('knowledge');
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
