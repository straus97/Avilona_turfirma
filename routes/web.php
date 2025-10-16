<?php

use App\Http\Controllers\ProfileController;
use Illuminate\Support\Facades\Route;

Route::group(['namespace' => 'Home'], function () {
    //меню сайта
    Route::get('/', "IndexController")->name("home.index")->middleware('cache.response');

    Route::get('/reviews/{id_reviews}', "ReviewsController")->name("reviews.show")->middleware('cache.response');
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

//dashvoard доступен только для auth и verified пользователей
//Route::get('/dashboard', function () {
//    return view('dashboard');
//})->middleware(['auth', 'verified'])->name('dashboard');

Route::middleware(['auth', 'verified', 'role:user,admin'])->group(function () {
    Route::get('/profile/settings', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile/settings', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile/settings', [ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/profile/account', function () {
        return view('profile.account');
    })->name('account');

    Route::get('/profile/message', function () {
        return view('profile.message');
    })->name('message');
});

require __DIR__ . '/auth.php';
