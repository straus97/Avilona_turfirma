<?php

use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Публичные маршруты сайта (NOV-PUBLIC-01)
|--------------------------------------------------------------------------
|
| Здесь — только маршруты, доступные анонимному посетителю и не требующие
| кабинета/авторизации. Файл подключается из routes/web.php, а при
| RELEASE_PUBLIC_ONLY=true RouteServiceProvider регистрирует ТОЛЬКО его:
| все остальные маршруты (вход, регистрация, кабинеты, чат, документы, API,
| webhook Tourvisor, отправка отзыва) тогда вообще не существуют (404).
| Новый маршрут по умолчанию приватный: чтобы он попал в публичный релиз,
| его нужно явно добавить сюда и в tests/Feature/PublicReleaseProfileTest.php.
|
*/

Route::group(['namespace' => 'Home'], function () {
    //меню сайта
    Route::get('/', "IndexController")->name("home.index");
});

Route::group(['namespace' => 'Tour'], function () {
    Route::get('/tours', 'IndexController')->name('tours.index');
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
    // E2-A6-I1: публичные POST-эндпоинты обратной связи ограничены частотой
    // (Laravel-native throttle) поверх капчи. GET /contacts не затрагивается,
    // остальные публичные маршруты — тоже. Лимит подобран так, чтобы обычный
    // посетитель (в т.ч. повторная отправка после ошибки валидации) его не
    // достигал, а автоматизированный флуд — достигал.
    Route::post('/contact/send_home', 'SendHomeController')
        ->name('contact.send_home')
        ->middleware('throttle:8,1'); //отправка с главной страницы
    Route::post('/contact/send_contact', 'SendContactController')
        ->name('contact.send_contact')
        ->middleware('throttle:8,1'); //отправка с контактной страницы
});

Route::group(['namespace' => 'Review'], function () {
    Route::get('/reviews', "IndexController")->name("review.index");
});

Route::group(['namespace' => 'HelpfulInformation'], function () {
    Route::get('/helpful_information/interesting_articles', "ArticlesController")->name(
        "interesting_articles.index"
    );
    Route::get('/helpful_information/interesting_articles/{slug}', "InterestingNewsController")->name(
        "helpful_information.show_interesting_news"
    );

    Route::get('/helpful_information/news', "HelpfulNewsController")->name("helpful_news.index");
    Route::get('/helpful_information/news/rss', "RssController")->name("news.rss");
    Route::get('/helpful_information/news/{slug}', "HelpfulNewsIdController")->name(
        "helpful_news_id.index"
    );

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

// Информация об использовании cookie (Stage 13: cookie consent)
Route::get('/cookies', function () {
    return view('legal.cookies');
})->name('cookies.info');

// Информация о согласии на обработку персональных данных (Stage 13: personal data consent)
Route::view('/personal-data-consent', 'legal.personal-data-consent')->name('personal_data_consent.info');

// Согласие на обработку персональных данных при направлении отзыва (Stage 13 C2)
Route::view('/reviews/personal-data-consent', 'legal.review-personal-data-consent')->name('review_personal_data_consent.info');

// Согласие на публикацию отзыва и имени (Stage 13 C2)
Route::view('/reviews/publication-consent', 'legal.review-publication-consent')->name('review_publication_consent.info');


Route::group(['namespace' => 'Captcha'], function () {
    // E1-FINAL-08: намеренно без 'cache.response' — ответ содержит одноразовый
    // токен капчи (Captcha::src(...) . time()) и не должен переотдаваться из
    // кэша ответов. CacheResponse глобально не меняется — снят только здесь.
    Route::get('/reload-captcha', "ReloadCaptchaController")->name("captcha_reload.index");
});
